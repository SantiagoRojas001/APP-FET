<?php

namespace App\Services;

use App\Enums\EstadoCita;
use App\Enums\EstadoVinculacion;
use App\Enums\Modalidad;
use App\Enums\Rol;
use App\Exceptions\ReglaNegocioException;
use App\Models\Cita;
use App\Models\Empresa;
use App\Models\Estudiante;
use App\Models\PeriodoAcademico;
use App\Models\Usuario;
use App\Models\Vinculacion;
use App\Notifications\CartaPorEnviar;
use App\Notifications\CartaPresentacionEnviada;
use App\Notifications\CitaAsignada;
use App\Notifications\CitaCancelada;
use App\Notifications\PracticaAutorizada;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * De la cita a la práctica:
 *
 * 1. Una empresa con acuerdo aprobado y vigente asigna una cita a un
 *    estudiante habilitado cuya hoja de vida aprobó un docente. El
 *    estudiante recibe la empresa, la dirección, la fecha y la hora; puede
 *    tener varias citas.
 * 2. Según la cita, la empresa acepta al estudiante (con la fecha de inicio)
 *    o lo rechaza.
 * 3. Si lo acepta, el director del programa envía la carta de presentación:
 *    empieza la práctica con el docente que aprobó la hoja de vida como
 *    tutor, el estudiante recibe la carta de bienvenida, su hoja de vida se
 *    deshabilita y sus demás procesos se cierran.
 */
class CitaService
{
    public function __construct(private readonly ElegibilidadService $elegibilidad) {}

    /**
     * @param  array{fecha_hora: Carbon, direccion: string, indicaciones?: string|null, vacante_id?: int|null}  $datos
     */
    public function asignar(Empresa $empresa, Estudiante $estudiante, array $datos): Cita
    {
        if (! $empresa->tieneAcuerdoVigente()) {
            throw new ReglaNegocioException('Para asignar citas necesitas un acuerdo aprobado y vigente con la FET.');
        }

        $estudiante->loadMissing('hojaVida', 'vinculacionActiva');

        if (! $this->elegibilidad->habilitadoParaPractica($estudiante) || ! $estudiante->hojaVida?->aprobada() || $estudiante->vinculacionActiva) {
            throw new ReglaNegocioException('La hoja de vida de este estudiante no está disponible.');
        }

        if ($empresa->citas()->where('estudiante_id', $estudiante->id)->whereIn('estado', EstadoCita::abiertos())->exists()) {
            throw new ReglaNegocioException('Ya tienes un proceso abierto con este estudiante.');
        }

        if (($datos['vacante_id'] ?? null) && ! $empresa->vacantes()->whereKey($datos['vacante_id'])->exists()) {
            throw new ReglaNegocioException('La vacante no es de tu empresa.');
        }

        $cita = $empresa->citas()->create([...$datos, 'estudiante_id' => $estudiante->id]);
        Auditoria::registrar('cita.asignada', $cita);

        $cita->load('empresa', 'estudiante.usuario');
        $cita->estudiante->usuario->notify(new CitaAsignada($cita));

        return $cita;
    }

    public function cancelar(Cita $cita, ?string $motivo): Cita
    {
        $cita = DB::transaction(function () use ($cita, $motivo) {
            $cita = $this->bloquear($cita, EstadoCita::Programada);
            $cita->update(['estado' => EstadoCita::Cancelada, 'observacion' => $motivo]);

            return $cita;
        });

        Auditoria::registrar('cita.cancelada', $cita, array_filter(['motivo' => $motivo]));
        $cita->load('empresa', 'estudiante.usuario');
        $cita->estudiante->usuario->notify(new CitaCancelada($cita));

        return $cita;
    }

    /**
     * Según la cita, la empresa acepta al estudiante (pasa al director para la
     * carta de presentación) o lo rechaza.
     */
    public function decidir(Cita $cita, bool $aceptar, ?string $observacion, ?Carbon $fechaInicio = null): Cita
    {
        $cita = DB::transaction(function () use ($cita, $aceptar, $observacion, $fechaInicio) {
            $cita = $this->bloquear($cita, EstadoCita::Programada);

            if ($cita->fecha_hora->isFuture()) {
                throw new ReglaNegocioException('Podrás decidir después de la cita ('.$this->fechaLocal($cita).').');
            }

            if ($aceptar && $this->tieneVinculacionActiva($cita->estudiante_id)) {
                throw new ReglaNegocioException('El estudiante ya inició su práctica en otra empresa.');
            }

            $cita->update([
                'estado' => $aceptar ? EstadoCita::Aceptado : EstadoCita::Rechazado,
                'observacion' => $observacion,
                'decidida_en' => now(),
                'fecha_inicio_practica' => $aceptar ? ($fechaInicio ?? today())->toDateString() : null,
            ]);

            return $cita;
        });

        Auditoria::registrar($aceptar ? 'cita.aceptado' : 'cita.rechazado', $cita, array_filter(['observacion' => $observacion]));

        if ($aceptar) {
            $cita->load('empresa', 'estudiante.programa');
            $directores = $this->usuarios(Rol::Director, $cita->estudiante->programa_id);
            Notification::send($directores->isNotEmpty() ? $directores : $this->usuarios(Rol::Admin), new CartaPorEnviar($cita));
        }

        return $cita;
    }

    /**
     * El director envía la carta de presentación a la empresa y con ella
     * empieza la práctica.
     */
    public function presentar(Cita $cita, Usuario $director): Cita
    {
        $cita = DB::transaction(function () use ($cita, $director) {
            $cita = $this->bloquear($cita, EstadoCita::Aceptado);
            $cita->load('estudiante.hojaVida', 'vacante');
            $estudiante = $cita->estudiante;

            if ($this->tieneVinculacionActiva($estudiante->id)) {
                throw new ReglaNegocioException('El estudiante ya inició su práctica en otra empresa.');
            }

            $inicio = $cita->fecha_inicio_practica ?? today();
            $vinculacion = Vinculacion::create([
                'estudiante_id' => $estudiante->id,
                'empresa_id' => $cita->empresa_id,
                'vacante_id' => $cita->vacante_id,
                // Tutor: el docente que aprobó la hoja de vida.
                'tutor_id' => $estudiante->hojaVida?->revisada_por,
                'tipo' => $cita->vacante?->modalidad ?? $estudiante->modalidad_grado ?? Modalidad::Pasantia,
                'semestre_al_vincular' => $estudiante->semestre_actual,
                'fecha_inicio' => $inicio->toDateString(),
                'periodo_id' => PeriodoAcademico::abiertoEn($inicio)?->id,
            ]);

            $cita->update([
                'estado' => EstadoCita::Presentado,
                'presentada_por' => $director->id,
                'presentada_en' => now(),
                'vinculacion_id' => $vinculacion->id,
            ]);

            // Con la práctica iniciada, la hoja de vida deja de circular: se cierran sus demás procesos.
            Cita::query()
                ->where('estudiante_id', $estudiante->id)
                ->whereKeyNot($cita->id)
                ->whereIn('estado', EstadoCita::abiertos())
                ->update(['estado' => EstadoCita::Cancelada, 'observacion' => 'El estudiante inició su práctica en otra empresa.']);

            return $cita;
        });

        Auditoria::registrar('cita.carta_presentacion', $cita, ['vinculacion_id' => $cita->vinculacion_id]);

        $cita->load('empresa.usuario', 'estudiante.usuario', 'estudiante.programa', 'vinculacion.estudiante.programa', 'vinculacion.empresa', 'vinculacion.vacante', 'vinculacion.tutor', 'director');
        $cita->empresa->usuario->notify(new CartaPresentacionEnviada($cita));
        $cita->estudiante->usuario->notify(new PracticaAutorizada($cita->vinculacion));

        return $cita;
    }

    /** Fecha y hora de la cita en la hora de Colombia, para los mensajes. */
    public function fechaLocal(Cita $cita): string
    {
        return $cita->fecha_hora->timezone(config('fet.zona_horaria'))->format('d/m/Y \a \l\a\s g:i a');
    }

    /** Relee la cita con bloqueo de fila y exige que siga en el estado esperado. */
    private function bloquear(Cita $cita, EstadoCita $esperado): Cita
    {
        $actual = Cita::query()->lockForUpdate()->findOrFail($cita->id);

        if ($actual->estado !== $esperado) {
            throw new ReglaNegocioException("Esta cita ya cambió de estado ({$actual->estado->label()}).");
        }

        return $actual;
    }

    private function tieneVinculacionActiva(int $estudianteId): bool
    {
        return Vinculacion::query()->where('estudiante_id', $estudianteId)->where('estado', EstadoVinculacion::Activa)->exists();
    }

    /** @return Collection<int, Usuario> */
    private function usuarios(Rol $rol, ?int $programaId = null)
    {
        return Usuario::query()
            ->where('rol', $rol)
            ->where('activo', true)
            ->when($programaId, fn ($q) => $q->where('programa_id', $programaId))
            ->get();
    }
}
