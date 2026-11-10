<?php

namespace App\Notifications;

use App\Models\SolicitudConvenio;

/**
 * A la administración: una empresa sin convenio pidió uno para registrarse.
 */
class SolicitudConvenioRecibida extends Aviso
{
    public function __construct(private readonly SolicitudConvenio $solicitud) {}

    protected function titulo(): string
    {
        return 'Solicitud de convenio';
    }

    protected function mensaje(): string
    {
        return "{$this->solicitud->razon_social} (NIT {$this->solicitud->nit}) pide un convenio con la FET para registrarse. Envíale el convenio desde Convenios.";
    }

    protected function enlace(): string
    {
        return '/convenios';
    }
}
