<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Restricciones de base de datos que el Schema Builder no expresa.
 */
final class Restricciones
{
    /**
     * Limita una columna de texto a los valores permitidos (CHECK ... IN).
     *
     * @param  list<string>  $valores
     */
    public static function valoresPermitidos(string $tabla, string $columna, array $valores): void
    {
        $lista = implode(', ', array_map(fn (string $v) => DB::getPdo()->quote($v), $valores));

        DB::statement("ALTER TABLE {$tabla} ADD CONSTRAINT {$tabla}_{$columna}_check CHECK ({$columna} IN ({$lista}))");
    }

    /**
     * Cambia los valores permitidos de una columna que ya tiene su CHECK.
     *
     * @param  list<string>  $valores
     */
    public static function reemplazarValores(string $tabla, string $columna, array $valores): void
    {
        DB::statement("ALTER TABLE {$tabla} DROP CONSTRAINT IF EXISTS {$tabla}_{$columna}_check");
        self::valoresPermitidos($tabla, $columna, $valores);
    }
}
