<?php

namespace App\Enums;

/**
 * Estado de publicación de una vacante (vacante.estado).
 */
enum EstadoVacante: string
{
    use HasValues;

    case Abierta = 'ABIERTA';
    case Cerrada = 'CERRADA';

    public function label(): string
    {
        return match ($this) {
            self::Abierta => 'Abierta',
            self::Cerrada => 'Cerrada',
        };
    }
}
