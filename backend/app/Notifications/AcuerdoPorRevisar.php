<?php

namespace App\Notifications;

use App\Models\Acuerdo;

/**
 * A la administración: una empresa subió su convenio y espera aprobación.
 */
class AcuerdoPorRevisar extends Aviso
{
    public function __construct(private readonly Acuerdo $acuerdo) {}

    protected function titulo(): string
    {
        return 'Convenio por aprobar';
    }

    protected function mensaje(): string
    {
        return "{$this->acuerdo->empresa->razon_social} subió su convenio (vigencia del {$this->acuerdo->vigencia_inicio->format('d/m/Y')} "
            ."al {$this->acuerdo->vigencia_fin->format('d/m/Y')}). Revísalo para que pueda asignar citas.";
    }

    protected function enlace(): string
    {
        return '/convenios';
    }
}
