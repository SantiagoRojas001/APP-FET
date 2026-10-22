<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RestablecerContrasenaRequest;
use App\Http\Requests\Auth\SolicitarRecuperacionRequest;
use App\Models\Usuario;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

/**
 * Recuperación de contraseña con enlace por correo (broker de Laravel).
 */
class RecuperacionController extends Controller
{
    /**
     * Envía el enlace si la cuenta existe. La respuesta es la misma en
     * cualquier caso, para no revelar qué correos están registrados.
     */
    public function solicitar(SolicitarRecuperacionRequest $request): JsonResponse
    {
        Password::sendResetLink(['correo' => $request->validated('correo')]);

        return response()->json([
            'message' => 'Si el correo está registrado, te enviamos un enlace para crear una contraseña nueva. Revisa tu bandeja de entrada.',
        ]);
    }

    public function restablecer(RestablecerContrasenaRequest $request): JsonResponse
    {
        $estado = Password::reset(
            $request->only('correo', 'password', 'password_confirmation', 'token'),
            function (Usuario $usuario, string $password) {
                $usuario->forceFill(['password_hash' => $password])->save();
                $usuario->tokens()->delete(); // cierra las sesiones abiertas con la clave anterior

                event(new PasswordReset($usuario));
            },
        );

        if ($estado !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'token' => 'El enlace no es válido o ya venció. Solicita uno nuevo.',
            ]);
        }

        return response()->json(['message' => 'Tu contraseña se actualizó. Ya puedes ingresar.']);
    }
}
