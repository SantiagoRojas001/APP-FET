<?php

namespace App\Enums;

/**
 * Referencia de la hoja de vida (referencia.tipo).
 */
enum TipoReferencia: string
{
    use HasValues;

    case Familiar = 'FAMILIAR';
    case Personal = 'PERSONAL';

    public function label(): string
    {
        return match ($this) {
            self::Familiar => 'Referencia familiar',
            self::Personal => 'Referencia personal',
        };
    }
}
