<?php

namespace App\Http\Middleware;

use App\Enums\Rol;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe una ruta a uno o varios roles. Uso: ->middleware('rol:DOCENTE,ADMIN').
 */
class AsegurarRol
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $permitidos = array_map(fn (string $rol) => Rol::from($rol), $roles);

        abort_unless($request->user()?->tieneRol(...$permitidos), 403, 'Tu rol no tiene acceso a esta sección.');

        return $next($request);
    }
}
