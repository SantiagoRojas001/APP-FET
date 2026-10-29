<?php

namespace App\Services;

use App\Models\Auditoria as RegistroAuditoria;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Bitácora de auditoría: deja constancia de quién hizo cada acción relevante
 * (aprobar, rechazar, revisar, finalizar, crear cuentas…), sobre qué y cuándo.
 */
class Auditoria
{
    /**
     * @param  string  $accion  Verbo en pasado, por ejemplo 'postulacion.aprobada'.
     * @param  array<string, mixed>  $detalle  Datos útiles para entender la acción.
     * @param  Usuario|null  $autor  Quién la hizo, si no es el usuario autenticado.
     */
    public static function registrar(string $accion, Model $entidad, array $detalle = [], ?Usuario $autor = null): void
    {
        $request = request();

        RegistroAuditoria::create([
            'usuario_id' => ($autor ?? $request->user())?->id,
            'accion' => $accion,
            'entidad' => Str::snake(class_basename($entidad)),
            'entidad_id' => $entidad->getKey(),
            'detalle' => $detalle ?: null,
            'ip' => $request->ip(),
        ]);
    }
}
