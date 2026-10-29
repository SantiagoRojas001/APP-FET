<?php

namespace App\Notifications;

use App\Enums\EstadoAcuerdo;
use App\Models\Acuerdo;

/**
 * A la empresa: la FET aprobó o rechazó su acuerdo.
 */
class AcuerdoRevisado extends Aviso
{
    public function __construct(private readonly Acuerdo $acuerdo) {}

    protected function titulo(): string
    {
        return $this->aprobado() ? 'Tu acuerdo fue aprobado' : 'Tu acuerdo fue rechazado';
    }

    protected function mensaje(): string
    {
        return $this->aprobado()
            ? 'La FET aprobó tu acuerdo. Ya puedes revisar las hojas de vida publicadas y asignar citas a los estudiantes.'
            : "La FET rechazó tu acuerdo. Motivo: {$this->acuerdo->observacion}. Puedes subir uno corregido.";
    }

    protected function enlace(): string
    {
        return '/acuerdo';
    }

    private function aprobado(): bool
    {
        return $this->acuerdo->estado === EstadoAcuerdo::Aprobado;
    }
}
