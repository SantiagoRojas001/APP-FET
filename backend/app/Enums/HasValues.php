<?php

namespace App\Enums;

/**
 * Utilidades comunes para los enums respaldados por texto.
 */
trait HasValues
{
    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
