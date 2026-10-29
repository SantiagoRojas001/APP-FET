<?php

namespace App\Services;

use App\Enums\Rol;
use App\Models\Usuario;
use App\Notifications\InvitacionCuenta;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Las cuentas solo las crea la administración. La persona recibe un enlace
 * de invitación con el que define su contraseña y acepta la política de
 * datos; hasta entonces la cuenta no tiene una contraseña que alguien conozca.
 */
class CuentaService
{
    /**
     * @param  Closure(Usuario): void|null  $perfil  Crea el perfil del rol (estudiante o empresa).
     */
    public function crear(string $correo, Rol $rol, ?Closure $perfil = null): Usuario
    {
        $usuario = DB::transaction(function () use ($correo, $rol, $perfil) {
            $usuario = Usuario::create([
                'correo' => $correo,
                'password_hash' => Str::password(40),
                'rol' => $rol,
            ]);

            if ($perfil) {
                $perfil($usuario);
            }

            return $usuario;
        });

        Auditoria::registrar('usuario.creado', $usuario, ['rol' => $rol->value]);
        $this->invitar($usuario);

        return $usuario;
    }

    public function invitar(Usuario $usuario): void
    {
        $token = Password::broker('invitaciones')->createToken($usuario);
        $usuario->notify(new InvitacionCuenta($token));
    }

    /** La cuenta se activó si la persona ya aceptó la política de datos. */
    public function pendienteDeActivacion(Usuario $usuario): bool
    {
        return ! $usuario->consentimientos()->exists();
    }
}
