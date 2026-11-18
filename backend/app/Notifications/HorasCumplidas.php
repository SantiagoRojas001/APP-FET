<?php

namespace App\Notifications;

use App\Models\Vinculacion;

/**
 * Al estudiante: la empresa aprobó las horas mínimas. Puede seguir reportando
 * semanas hasta que su docente tutor cierre la práctica.
 */
class HorasCumplidas extends Aviso
{
    public function __construct(private readonly Vinculacion $vinculacion, private readonly int $horasRequeridas) {}

    protected function titulo(): string
    {
        return 'Cumpliste las horas de tu programa';
    }

    protected function mensaje(): string
    {
        return "La empresa aprobó las {$this->horasRequeridas} horas requeridas por tu programa. Puedes seguir reportando semanas: "
            .'tu docente tutor cerrará la práctica cuando estén las notas.';
    }

    protected function enlace(): string
    {
        return '/evidencias';
    }
}
