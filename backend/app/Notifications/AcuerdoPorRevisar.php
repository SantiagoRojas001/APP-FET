<?php

namespace App\Notifications;

use App\Models\Acuerdo;

/**
 * A la administración: una empresa subió su acuerdo y espera aprobación.
 */
class AcuerdoPorRevisar extends Aviso
{
    public function __construct(private readonly Acuerdo $acuerdo) {}

    protected function titulo(): string
    {
        return 'Acuerdo por aprobar';
    }

    protected function mensaje(): string
    {
        return "{$this->acuerdo->empresa->razon_social} subió su acuerdo (vigencia del {$this->acuerdo->vigencia_inicio->format('d/m/Y')} "
            ."al {$this->acuerdo->vigencia_fin->format('d/m/Y')}). Revísalo para que pueda asignar citas.";
    }

    protected function enlace(): string
    {
        return '/acuerdos';
    }
}
