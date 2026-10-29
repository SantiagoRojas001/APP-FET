<?php

namespace App\Services;

use App\Enums\EstadoPostulacion;
use App\Enums\EstadoVacante;
use App\Enums\EstadoVinculacion;
use App\Enums\Rol;
use App\Exceptions\ReglaNegocioException;
use App\Models\Estudiante;
use App\Models\PeriodoAcademico;
use App\Models\Postulacion;
use App\Models\Usuario;
use App\Models\Vacante;
use App\Models\Vinculacion;
use App\Notifications\EstudianteSeleccionado;
use App\Notifications\PracticaAutorizada;
use App\Notifications\SeleccionNoAutorizada;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Flujo de una postulación: el estudiante aplica y su hoja de vida llega
 * directo a la empresa. Si la empresa lo selecciona, se avisa a los docentes;
 * el docente autoriza la práctica, se crea la vinculación con él como tutor,
 * se envía la carta de bienvenida y la hoja de vida deja de circular.
 */
class PostulacionService
{
    public function __construct(private readonly ElegibilidadService $elegibilidad) {}

    public function postular(Estudiante $estudiante, Vacante $vacante): Postulacion
    {
        $vacante->loadMissing('empresa');

        if ($vacante->estado !== EstadoVacante::Abierta || ! $vacante->empresa->estaValidada()) {
            throw new ReglaNegocioException('Esta vacante ya no recibe postulaciones.');
        }

        if ($motivo = $this->elegibilidad->motivoParaNoPostular($estudiante, $vacante)) {
            throw new ReglaNegocioException($motivo);
        }

        if ($estudiante->vinculaciones()->where('estado', EstadoVinculacion::Activa)->exists()) {
            throw new ReglaNegocioException('Ya tienes una vinculación activa. Finalízala antes de postularte de nuevo.');
        }

        if ($vacante->postulaciones()->where('estudiante_id', $estudiante->id)->exists()) {
            throw new ReglaNegocioException('Ya te postulaste a esta vacante.');
        }

        $postulacion = $vacante->postulaciones()->create(['estudiante_id' => $estudiante->id]);
        Auditoria::registrar('postulacion.enviada', $postulacion);

        return $postulacion;
    }

    /** La empresa selecciona al estudiante (pasa a autorización docente) o lo descarta. */
    public function decidirComoEmpresa(Postulacion $postulacion, bool $seleccionar): Postulacion
    {
        $postulacion = DB::transaction(function () use ($postulacion, $seleccionar) {
            $postulacion = $this->bloquear($postulacion, EstadoPostulacion::Enviada);

            if ($seleccionar && Vinculacion::query()->where('estudiante_id', $postulacion->estudiante_id)->where('estado', EstadoVinculacion::Activa)->exists()) {
                throw new ReglaNegocioException('El estudiante ya tiene una vinculación activa con otra empresa.');
            }

            $postulacion->update([
                'estado' => $seleccionar ? EstadoPostulacion::Seleccionada : EstadoPostulacion::Descartada,
                'decidida_en' => now(),
            ]);
            Auditoria::registrar($seleccionar ? 'postulacion.seleccionada' : 'postulacion.descartada', $postulacion);

            return $postulacion;
        });

        if ($seleccionar) {
            $postulacion->load('vacante.empresa', 'estudiante');
            Notification::send(
                Usuario::query()->where('rol', Rol::Docente)->where('activo', true)->get(),
                new EstudianteSeleccionado($postulacion),
            );
        }

        return $postulacion;
    }

    /**
     * El docente autoriza la práctica de un estudiante seleccionado: se crea
     * la vinculación (con él como tutor), se cierran las demás postulaciones
     * abiertas del estudiante y se envía la carta de bienvenida. Si no la
     * autoriza, debe explicar el motivo.
     */
    public function autorizarComoDocente(Postulacion $postulacion, Usuario $docente, bool $autorizar, ?string $observacion, ?Carbon $fechaInicio = null): Postulacion
    {
        if (! $autorizar && blank($observacion)) {
            throw new ReglaNegocioException('Escribe el motivo para que el estudiante y la empresa lo conozcan.');
        }

        [$postulacion, $vinculacion] = DB::transaction(function () use ($postulacion, $docente, $autorizar, $observacion, $fechaInicio) {
            $postulacion = $this->bloquear($postulacion, EstadoPostulacion::Seleccionada);
            $postulacion->load('vacante', 'estudiante');

            if (! $autorizar) {
                $postulacion->update(['estado' => EstadoPostulacion::Rechazada, 'observacion' => $observacion, 'revisada_en' => now()]);
                Auditoria::registrar('postulacion.no_autorizada', $postulacion, ['observacion' => $observacion]);

                return [$postulacion, null];
            }

            $estudiante = $postulacion->estudiante;

            if ($estudiante->vinculaciones()->where('estado', EstadoVinculacion::Activa)->exists()) {
                throw new ReglaNegocioException('El estudiante ya tiene una vinculación activa con otra empresa.');
            }

            $postulacion->update(['estado' => EstadoPostulacion::Aceptada, 'observacion' => $observacion, 'revisada_en' => now()]);
            $inicio = $fechaInicio ?? today();

            $vinculacion = Vinculacion::create([
                'estudiante_id' => $estudiante->id,
                'empresa_id' => $postulacion->vacante->empresa_id,
                'vacante_id' => $postulacion->vacante_id,
                'tutor_id' => $docente->id,
                'tipo' => $postulacion->vacante->modalidad,
                'semestre_al_vincular' => $estudiante->semestre_actual,
                'fecha_inicio' => $inicio->toDateString(),
                'periodo_id' => PeriodoAcademico::abiertoEn($inicio)?->id,
            ]);

            // Con la práctica autorizada, la hoja de vida deja de circular: se cierran las demás postulaciones.
            $estudiante->postulaciones()
                ->whereKeyNot($postulacion->id)
                ->whereIn('estado', [EstadoPostulacion::Enviada, EstadoPostulacion::Seleccionada])
                ->update([
                    'estado' => EstadoPostulacion::Descartada,
                    'observacion' => 'El estudiante inició su práctica en otra empresa.',
                    'decidida_en' => now(),
                ]);

            Auditoria::registrar('postulacion.autorizada', $postulacion, ['vinculacion_id' => $vinculacion->id]);

            return [$postulacion, $vinculacion];
        });

        $postulacion->load('vacante.empresa.usuario', 'estudiante.usuario');

        if ($vinculacion) {
            $vinculacion->load('estudiante.programa', 'empresa', 'vacante', 'tutor');
            $postulacion->estudiante->usuario->notify(new PracticaAutorizada($vinculacion, paraEstudiante: true));
            $postulacion->vacante->empresa->usuario->notify(new PracticaAutorizada($vinculacion, paraEstudiante: false));
        } else {
            $aviso = new SeleccionNoAutorizada($postulacion);
            $postulacion->estudiante->usuario->notify($aviso);
            $postulacion->vacante->empresa->usuario->notify($aviso);
        }

        return $postulacion;
    }

    /**
     * Relee la postulación con bloqueo de fila y verifica que siga en el
     * estado esperado, para que dos decisiones simultáneas no se pisen.
     */
    private function bloquear(Postulacion $postulacion, EstadoPostulacion $esperado): Postulacion
    {
        $actual = Postulacion::query()->lockForUpdate()->findOrFail($postulacion->id);

        if ($actual->estado !== $esperado) {
            throw new ReglaNegocioException("Esta postulación ya fue decidida ({$actual->estado->label()}).");
        }

        return $actual;
    }
}
