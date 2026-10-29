<?php

namespace App\Enums;

/**
 * Revisión del acuerdo (convenio) que sube una empresa (acuerdo.estado).
 */
enum EstadoAcuerdo: string
{
    use HasValues;

    case Pendiente = 'PENDIENTE';
    case Aprobado = 'APROBADO';
    case Rechazado = 'RECHAZADO';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente de aprobación',
            self::Aprobado => 'Aprobado',
            self::Rechazado => 'Rechazado',
        };
    }
}
