<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\Estudiante;
use App\Models\Recomendacion;
use App\Models\Usuario;
use App\Models\Vacante;
use App\Notifications\EstudianteRecomendado;

/**
 * Los docentes recomiendan estudiantes para las vacantes de las empresas. La
 * empresa los ve primero entre las hojas de vida publicadas.
 */
class RecomendacionService
{
    public function __construct(private readonly ElegibilidadService $elegibilidad) {}

    public function recomendar(Vacante $vacante, Estudiante $estudiante, Usuario $docente, ?string $comentario): Recomendacion
    {
        $estudiante->loadMissing('hojaVida', 'vinculacionActiva');

        if (! $this->elegibilidad->habilitadoParaPractica($estudiante) || ! $estudiante->hojaVida?->aprobada() || $estudiante->vinculacionActiva) {
            throw new ReglaNegocioException('Solo se recomiendan estudiantes habilitados, con hoja de vida aprobada y sin práctica en curso.');
        }

        if ($vacante->recomendaciones()->where('estudiante_id', $estudiante->id)->exists()) {
            throw new ReglaNegocioException('Este estudiante ya está recomendado para la vacante.');
        }

        $recomendacion = $vacante->recomendaciones()->create([
            'estudiante_id' => $estudiante->id,
            'docente_id' => $docente->id,
            'comentario' => $comentario,
        ]);
        Auditoria::registrar('recomendacion.creada', $recomendacion);

        $vacante->loadMissing('empresa.usuario');
        $vacante->empresa->usuario->notify(new EstudianteRecomendado($vacante, $estudiante));

        return $recomendacion;
    }
}
