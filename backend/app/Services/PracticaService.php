<?php

namespace App\Services;

use App\Enums\EstadoRevision;
use App\Enums\EstadoVinculacion;
use App\Enums\TipoEvaluacion;
use App\Exceptions\ReglaNegocioException;
use App\Models\EvidenciaSemanal;
use App\Models\Usuario;
use App\Models\Vinculacion;
use App\Notifications\EvidenciaObservada;
use App\Notifications\EvidenciaPorAprobar;
use App\Notifications\HorasCumplidas;
use App\Notifications\PracticaCulminada;
use App\Notifications\PracticanteCumplioHoras;
use App\Notifications\PracticaTerminada;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Avance y cierre de una práctica:
 *
 * - El estudiante reporta sus horas por semana con la evidencia de lo que
 *   hizo. La empresa aprueba cada semana (o todas las pendientes de una vez)
 *   o la devuelve con un comentario para que el estudiante la corrija.
 * - Solo las horas aprobadas cuentan. Al alcanzar las del programa se avisa,
 *   pero se pueden seguir reportando semanas.
 * - La práctica culmina sola cuando, con las horas cumplidas, la empresa
 *   responde la evaluación del practicante.
 * - El docente puede finalizarla antes si acumula varios reportes de
 *   problemas; si el estudiante no completó las horas, debe justificarlo.
 */
class PracticaService
{
    public function horasAprobadas(Vinculacion $vinculacion): float
    {
        return (float) $vinculacion->evidencias()->where('estado', EstadoRevision::Aprobado)->sum('horas');
    }

    public function horasRequeridas(Vinculacion $vinculacion): int
    {
        return $vinculacion->loadMissing('estudiante.programa')->estudiante->programa->horas_requeridas;
    }

    /**
     * @param  array{semana_inicio: string, horas: float|string, actividades: string}  $datos
     */
    public function registrarEvidencia(Vinculacion $vinculacion, array $datos, ?UploadedFile $archivo): EvidenciaSemanal
    {
        $semana = Carbon::parse($datos['semana_inicio'])->startOfWeek();

        if ($semana->lt($vinculacion->fecha_inicio->copy()->startOfWeek())) {
            throw new ReglaNegocioException('La semana es anterior al inicio de tu práctica ('.$vinculacion->fecha_inicio->format('d/m/Y').').');
        }

        if ($vinculacion->evidencias()->whereDate('semana_inicio', $semana)->exists()) {
            throw new ReglaNegocioException('Ya reportaste esa semana. Si te la devolvieron, corrígela desde la lista.');
        }

        $evidencia = $vinculacion->evidencias()->create([
            ...$datos,
            'semana_inicio' => $semana->toDateString(),
            'archivo_url' => $archivo?->store("evidencias/vinculacion-{$vinculacion->id}", 'local'),
        ]);

        $vinculacion->loadMissing('empresa.usuario', 'estudiante');
        $vinculacion->empresa->usuario->notify(new EvidenciaPorAprobar($vinculacion));

        return $evidencia;
    }

    /**
     * El estudiante corrige una semana que la empresa devolvió: vuelve a
     * quedar por revisar.
     *
     * @param  array{horas: float|string, actividades: string}  $datos
     */
    public function corregirEvidencia(EvidenciaSemanal $evidencia, array $datos, ?UploadedFile $archivo): EvidenciaSemanal
    {
        if ($evidencia->estado !== EstadoRevision::Observado) {
            throw new ReglaNegocioException('Solo se corrigen las semanas que la empresa devolvió con observaciones.');
        }

        if ($archivo) {
            if ($evidencia->archivo_url) {
                Storage::disk('local')->delete($evidencia->archivo_url);
            }
            $datos['archivo_url'] = $archivo->store("evidencias/vinculacion-{$evidencia->vinculacion_id}", 'local');
        }

        $evidencia->update([...$datos, 'estado' => EstadoRevision::Pendiente]);

        return $evidencia;
    }

    /** La empresa aprueba la semana o la devuelve con un comentario para corregirla. */
    public function revisarEvidencia(EvidenciaSemanal $evidencia, Usuario $revisor, bool $aprobar, ?string $comentario): EvidenciaSemanal
    {
        if ($evidencia->estado !== EstadoRevision::Pendiente) {
            throw new ReglaNegocioException("Esta semana ya fue revisada ({$evidencia->estado->label()}).");
        }

        $evidencia->update([
            'estado' => $aprobar ? EstadoRevision::Aprobado : EstadoRevision::Observado,
            'comentario' => $comentario,
            'revisada_por' => $revisor->id,
            'revisada_en' => now(),
        ]);
        Auditoria::registrar($aprobar ? 'evidencia.aprobada' : 'evidencia.observada', $evidencia, array_filter(['comentario' => $comentario]));

        $vinculacion = $evidencia->vinculacion;

        if ($aprobar) {
            $this->verificarHoras($vinculacion);
        } else {
            $vinculacion->load('estudiante.usuario', 'empresa');
            $vinculacion->estudiante->usuario->notify(new EvidenciaObservada($vinculacion, $evidencia));
        }

        return $evidencia;
    }

    /** Aprueba de una vez todas las semanas pendientes. Devuelve cuántas aprobó. */
    public function aprobarPendientes(Vinculacion $vinculacion, Usuario $revisor): int
    {
        $aprobadas = $vinculacion->evidencias()->where('estado', EstadoRevision::Pendiente)->update([
            'estado' => EstadoRevision::Aprobado,
            'revisada_por' => $revisor->id,
            'revisada_en' => now(),
        ]);

        if ($aprobadas) {
            Auditoria::registrar('evidencia.aprobadas_en_bloque', $vinculacion, ['semanas' => $aprobadas]);
            $this->verificarHoras($vinculacion);
        }

        return $aprobadas;
    }

    /**
     * Cierra la práctica cuando la empresa ya evaluó al practicante y las
     * horas están cumplidas: no hace falta un paso de aprobación aparte.
     */
    public function culminarSiCorresponde(Vinculacion $vinculacion): void
    {
        $culminada = DB::transaction(function () use ($vinculacion) {
            $actual = Vinculacion::query()->lockForUpdate()->findOrFail($vinculacion->id);

            if (! $actual->estaActiva() || ! $actual->cumplioHoras()
                || ! $actual->evaluaciones()->where('tipo', TipoEvaluacion::EmpresaAPracticante)->exists()) {
                return null;
            }

            $actual->update([
                'estado' => EstadoVinculacion::Finalizada,
                'fecha_fin' => today(),
                'reconocida_como_practica' => true,
                'finalizada_por' => $actual->empresa()->value('usuario_id'),
            ]);

            return $actual;
        });

        if (! $culminada) {
            return;
        }

        $culminada->load('estudiante.usuario', 'empresa');
        Auditoria::registrar('vinculacion.culminada', $culminada);
        $culminada->estudiante->usuario->notify(new PracticaCulminada($culminada));
    }

    public function finalizarComoDocente(Vinculacion $vinculacion, Usuario $docente, ?string $justificacion): Vinculacion
    {
        $vinculacion = DB::transaction(function () use ($vinculacion, $docente, $justificacion) {
            $vinculacion = $this->bloquearActiva($vinculacion);
            $minimo = config('fet.reportes_para_finalizar');

            if ($vinculacion->reportes()->count() < $minimo) {
                throw new ReglaNegocioException("Solo puedes finalizar una práctica que acumule al menos {$minimo} reportes de problemas.");
            }

            if (! $vinculacion->cumplioHoras() && blank($justificacion)) {
                throw new ReglaNegocioException('El estudiante no ha completado las horas: justifica por qué finalizas la práctica.');
            }

            $vinculacion->update([
                'estado' => $vinculacion->cumplioHoras() ? EstadoVinculacion::Finalizada : EstadoVinculacion::Cancelada,
                'fecha_fin' => today(),
                'reconocida_como_practica' => $vinculacion->cumplioHoras(),
                'observacion_final' => $justificacion,
                'finalizada_por' => $docente->id,
                'tutor_id' => $vinculacion->tutor_id ?? $docente->id,
            ]);

            return $vinculacion;
        });

        $vinculacion->load('estudiante.usuario', 'empresa.usuario');
        Auditoria::registrar('vinculacion.finalizada_por_docente', $vinculacion, [
            'estado' => $vinculacion->estado->value,
            'justificacion' => $justificacion,
        ]);

        $aviso = new PracticaTerminada($vinculacion);
        $vinculacion->estudiante->usuario->notify($aviso);
        $vinculacion->empresa->usuario->notify($aviso);

        return $vinculacion;
    }

    /**
     * La primera vez que las horas alcanzan las requeridas, deja constancia y
     * avisa al estudiante, a la empresa y al docente tutor.
     */
    private function verificarHoras(Vinculacion $vinculacion): void
    {
        if ($vinculacion->cumplioHoras() || $this->horasAprobadas($vinculacion) < $this->horasRequeridas($vinculacion)) {
            return;
        }

        $vinculacion->update(['horas_cumplidas_en' => now()]);
        $vinculacion->load('estudiante.usuario', 'empresa.usuario', 'tutor');
        Auditoria::registrar('vinculacion.horas_cumplidas', $vinculacion);

        $vinculacion->estudiante->usuario->notify(new HorasCumplidas($vinculacion, $this->horasRequeridas($vinculacion)));
        $vinculacion->empresa->usuario->notify(new PracticanteCumplioHoras($vinculacion));
        $vinculacion->tutor?->notify(new PracticanteCumplioHoras($vinculacion));
    }

    /** Relee la vinculación con bloqueo de fila y exige que siga activa. */
    private function bloquearActiva(Vinculacion $vinculacion): Vinculacion
    {
        $actual = Vinculacion::query()->lockForUpdate()->findOrFail($vinculacion->id);

        if (! $actual->estaActiva()) {
            throw new ReglaNegocioException("Esta práctica ya está cerrada ({$actual->estado->label()}).");
        }

        return $actual;
    }
}
