<?php

namespace App\Enums;

/**
 * Estado de validación institucional de una empresa (empresa.estado_validacion).
 */
enum EstadoValidacion: string
{
    use HasValues;

    case Pendiente = 'PENDIENTE';
    case Validada = 'VALIDADA';
    case Rechazada = 'RECHAZADA';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Validada => 'Validada',
            self::Rechazada => 'Rechazada',
        };
    }
}
