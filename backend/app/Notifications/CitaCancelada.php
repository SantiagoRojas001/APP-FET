<?php

namespace App\Notifications;

use App\Models\Cita;
use App\Services\CitaService;

/**
 * Al estudiante: la empresa canceló la cita.
 */
class CitaCancelada extends Aviso
{
    public function __construct(private readonly Cita $cita) {}

    protected function titulo(): string
    {
        return "Cita cancelada con {$this->cita->empresa->razon_social}";
    }

    protected function mensaje(): string
    {
        $motivo = $this->cita->observacion ? " Motivo: {$this->cita->observacion}" : '';

        return "{$this->cita->empresa->razon_social} canceló la cita del ".app(CitaService::class)->fechaLocal($this->cita).".{$motivo}";
    }

    protected function enlace(): string
    {
        return '/citas';
    }
}
