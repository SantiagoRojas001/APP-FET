<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mientras la cuenta tenga la contraseña inicial que le enviamos por correo,
 * solo puede cambiarla (y consultar su sesión o cerrarla).
 */
class AsegurarPrimerIngreso
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if($request->user()?->debe_cambiar_password, 403, 'Cambia tu contraseña inicial para continuar.');

        return $next($request);
    }
}
