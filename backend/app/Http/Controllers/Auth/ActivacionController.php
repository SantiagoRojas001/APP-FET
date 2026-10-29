<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ActivarCuentaRequest;
use App\Models\Usuario;
use App\Services\Auditoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

/**
 * Activación de las cuentas que crea la administración, con el enlace de la
 * invitación (broker `invitaciones`).
 */
class ActivacionController extends Controller
{
    public function __invoke(ActivarCuentaRequest $request): JsonResponse
    {
        $estado = Password::broker('invitaciones')->reset(
            $request->only('correo', 'password', 'password_confirmation', 'token'),
            function (Usuario $usuario, string $password) {
                $usuario->forceFill(['password_hash' => $password])->save();
                $usuario->consentimientos()->create(['version_politica' => config('fet.version_politica')]);
                Auditoria::registrar('usuario.activado', $usuario, autor: $usuario);
            },
        );

        if ($estado !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'token' => 'La invitación no es válida o ya venció. Pide a la coordinación de prácticas que te envíe una nueva.',
            ]);
        }

        return response()->json(['message' => 'Tu cuenta quedó activa. Ya puedes ingresar.']);
    }
}
