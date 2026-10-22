<?php

namespace App\Services;

use App\Enums\EstadoPostulacion;
use App\Enums\EstadoVacante;
use App\Enums\EstadoVinculacion;
use App\Exceptions\ReglaNegocioException;
use App\Models\Estudiante;
use App\Models\Postulacion;
use App\Models\Vacante;
use App\Models\Vinculacion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Flujo de una postulación: el estudiante aplica, el docente revisa los
 * requisitos y, si la aprueba, la empresa acepta o descarta. Al aceptar se
 * crea la vinculación.
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

        return $vacante->postulaciones()->create(['estudiante_id' => $estudiante->id]);
    }

    public function revisarComoDocente(Postulacion $postulacion, bool $aprobar, ?string $observacion): Postulacion
    {
        return DB::transaction(function () use ($postulacion, $aprobar, $observacion) {
            $postulacion = $this->bloquear($postulacion, EstadoPostulacion::Enviada);

            if (! $aprobar && blank($observacion)) {
                throw new ReglaNegocioException('Escribe el motivo del rechazo para que el estudiante lo conozca.');
            }

            $postulacion->update([
                'estado' => $aprobar ? EstadoPostulacion::Aprobada : EstadoPostulacion::Rechazada,
                'observacion' => $observacion,
            ]);

            return $postulacion;
        });
    }

    public function decidirComoEmpresa(Postulacion $postulacion, bool $aceptar, ?Carbon $fechaInicio = null): Postulacion
    {
        return DB::transaction(function () use ($postulacion, $aceptar, $fechaInicio) {
            $postulacion = $this->bloquear($postulacion, EstadoPostulacion::Aprobada);
            $postulacion->load('vacante', 'estudiante');

            if (! $aceptar) {
                $postulacion->update(['estado' => EstadoPostulacion::Descartada]);

                return $postulacion;
            }

            $estudiante = $postulacion->estudiante;

            if ($estudiante->vinculaciones()->where('estado', EstadoVinculacion::Activa)->exists()) {
                throw new ReglaNegocioException('El estudiante ya tiene una vinculación activa con otra empresa.');
            }

            $postulacion->update(['estado' => EstadoPostulacion::Aceptada]);

            Vinculacion::create([
                'estudiante_id' => $estudiante->id,
                'empresa_id' => $postulacion->vacante->empresa_id,
                'vacante_id' => $postulacion->vacante_id,
                'tipo' => $postulacion->vacante->modalidad,
                'semestre_al_vincular' => $estudiante->semestre_actual,
                'fecha_inicio' => ($fechaInicio ?? today())->toDateString(),
            ]);

            return $postulacion;
        });
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
