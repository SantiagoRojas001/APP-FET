<?php

namespace App\Notifications;

use App\Models\Cita;

/**
 * Al estudiante y a la empresa: el docente no aprobó la práctica.
 */
class SeleccionNoAutorizada extends Aviso
{
    public function __construct(private readonly Cita $cita) {}

    protected function titulo(): string
    {
        return 'La práctica no fue aprobada';
    }

    protected function mensaje(): string
    {
        return "La FET no aprobó la práctica de {$this->cita->estudiante->nombre_completo} en "
            ."{$this->cita->empresa->razon_social}. Motivo: {$this->cita->observacion}";
    }
}
