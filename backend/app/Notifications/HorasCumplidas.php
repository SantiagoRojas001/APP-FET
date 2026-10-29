<?php

namespace App\Notifications;

use App\Models\Vinculacion;

/**
 * Al estudiante: la empresa aprobó las horas que exige su programa. Puede
 * seguir reportando semanas hasta que la empresa lo evalúe.
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
            .'tu proceso culmina cuando la empresa responda tu evaluación.';
    }

    protected function enlace(): string
    {
        return '/evidencias';
    }
}
