<?php

namespace App\Notifications;

use App\Models\Estudiante;
use App\Models\Vacante;

/**
 * A la empresa: un docente le recomienda un estudiante para una vacante.
 */
class EstudianteRecomendado extends Aviso
{
    public function __construct(private readonly Vacante $vacante, private readonly Estudiante $estudiante) {}

    protected function titulo(): string
    {
        return 'La FET te recomienda un estudiante';
    }

    protected function mensaje(): string
    {
        return "Un docente recomienda a {$this->estudiante->nombre_completo} para «{$this->vacante->titulo}». "
            .'Lo verás primero en Hojas de vida.';
    }

    protected function enlace(): string
    {
        return '/hojas-vida';
    }
}
