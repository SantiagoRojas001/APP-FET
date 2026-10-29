<?php

namespace App\Http\Requests\Auth;

/**
 * Activación de una cuenta invitada: además de la contraseña, la persona
 * acepta la política de tratamiento de datos (Ley 1581 de 2012).
 */
class ActivarCuentaRequest extends RestablecerContrasenaRequest
{
    public function rules(): array
    {
        return [...parent::rules(), 'acepta_politica' => ['accepted']];
    }
}
