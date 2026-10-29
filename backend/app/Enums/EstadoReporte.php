<?php

namespace App\Enums;

/**
 * Atención de un reporte por la administración (reporte.estado).
 */
enum EstadoReporte: string
{
    use HasValues;

    case Abierto = 'ABIERTO';
    case EnRevision = 'EN_REVISION';
    case Resuelto = 'RESUELTO';

    public function label(): string
    {
        return match ($this) {
            self::Abierto => 'Abierto',
            self::EnRevision => 'En revisión',
            self::Resuelto => 'Resuelto',
        };
    }
}
