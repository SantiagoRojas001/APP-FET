<?php

namespace App\Notifications;

use App\Models\Cita;
use App\Services\CitaService;

/**
 * Al estudiante: una empresa interesada le asignó una cita, con la empresa,
 * la dirección, la fecha y la hora.
 */
class CitaAsignada extends Aviso
{
    public function __construct(private readonly Cita $cita) {}

    protected function titulo(): string
    {
        return "Tienes una cita con {$this->cita->empresa->razon_social}";
    }

    protected function mensaje(): string
    {
        $fecha = app(CitaService::class)->fechaLocal($this->cita);
        $indicaciones = $this->cita->indicaciones ? " Indicaciones: {$this->cita->indicaciones}" : '';

        return "{$this->cita->empresa->razon_social} revisó tu hoja de vida y te asignó una cita el {$fecha} "
            ."en {$this->cita->direccion}.{$indicaciones}";
    }

    protected function enlace(): string
    {
        return '/citas';
    }
}
