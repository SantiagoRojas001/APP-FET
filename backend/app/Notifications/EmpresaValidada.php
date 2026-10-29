<?php

namespace App\Notifications;

/**
 * A la empresa: la FET validó su registro y ya puede ingresar.
 */
class EmpresaValidada extends Aviso
{
    protected function titulo(): string
    {
        return 'Tu empresa fue validada';
    }

    protected function mensaje(): string
    {
        return 'La FET validó el registro de tu empresa. Ya puedes ingresar a APP FET, publicar vacantes y recibir hojas de vida.';
    }

    protected function enlace(): string
    {
        return '/login';
    }
}
