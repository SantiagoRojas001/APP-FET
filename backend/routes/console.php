<?php

use App\Enums\Rol;
use App\Models\Usuario;
use App\Services\CuentaService;
use Illuminate\Support\Facades\Artisan;

/*
| Crea la primera cuenta de administración en un servidor nuevo (no hay
| registro público). La persona recibe la invitación para activarla.
*/
Artisan::command('fet:crear-admin {correo}', function (string $correo, CuentaService $cuentas) {
    $correo = mb_strtolower(trim($correo));

    if (! str_ends_with($correo, '@'.config('fet.dominio_correo'))) {
        $this->error('Usa un correo @'.config('fet.dominio_correo').'.');

        return 1;
    }

    // Si la cuenta ya existe sin activar (por ejemplo, porque falló el correo), reenvía la invitación.
    if ($usuario = Usuario::where('correo', $correo)->first()) {
        if (! $cuentas->pendienteDeActivacion($usuario)) {
            $this->error('Esa cuenta ya está activa.');

            return 1;
        }
        $cuentas->invitar($usuario);
        $this->info("Invitación enviada de nuevo a {$correo}.");

        return 0;
    }

    $cuentas->crear($correo, Rol::Admin);
    $this->info("Cuenta creada. Enviamos la invitación a {$correo}.");
})->purpose('Crea una cuenta de administrador y le envía la invitación');
