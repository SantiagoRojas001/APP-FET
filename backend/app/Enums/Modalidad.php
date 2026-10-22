<?php

namespace App\Enums;

/**
 * Modalidad de una vacante o tipo de vinculación (vacante.modalidad, vinculacion.tipo, empresa.modalidad_interes).
 */
enum Modalidad: string
{
    use HasValues;

    case Pasantia = 'PASANTIA';
    case Practica = 'PRACTICA';

    public function label(): string
    {
        return match ($this) {
            self::Pasantia => 'Pasantía',
            self::Practica => 'Práctica profesional',
        };
    }
}
