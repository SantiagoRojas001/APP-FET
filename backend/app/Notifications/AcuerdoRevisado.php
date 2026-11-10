<?php

namespace App\Notifications;

use App\Enums\EstadoAcuerdo;
use App\Models\Acuerdo;

/**
 * A la empresa: la FET aprobó o rechazó su convenio.
 */
class AcuerdoRevisado extends Aviso
{
    public function __construct(private readonly Acuerdo $acuerdo) {}

    protected function titulo(): string
    {
        return $this->aprobado() ? 'Tu convenio fue aprobado' : 'Tu convenio fue rechazado';
    }

    protected function mensaje(): string
    {
        return $this->aprobado()
            ? 'La FET aprobó tu convenio. Ya puedes revisar las hojas de vida publicadas y asignar citas a los estudiantes.'
            : "La FET rechazó tu convenio. Motivo: {$this->acuerdo->observacion}. Puedes subir uno corregido.";
    }

    protected function enlace(): string
    {
        return '/convenio';
    }

    private function aprobado(): bool
    {
        return $this->acuerdo->estado === EstadoAcuerdo::Aprobado;
    }
}
