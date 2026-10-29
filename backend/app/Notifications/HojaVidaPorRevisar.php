<?php

namespace App\Notifications;

use App\Models\Estudiante;

/**
 * A los docentes: un estudiante envió su hoja de vida a revisión.
 */
class HojaVidaPorRevisar extends Aviso
{
    public function __construct(private readonly Estudiante $estudiante) {}

    protected function titulo(): string
    {
        return 'Hoja de vida por revisar';
    }

    protected function mensaje(): string
    {
        return "{$this->estudiante->nombre_completo} ({$this->estudiante->programa->nombre}) envió su hoja de vida a revisión. "
            .'Apruébala para que la vean las empresas o devuélvela con recomendaciones.';
    }

    protected function enlace(): string
    {
        return '/revision';
    }
}
