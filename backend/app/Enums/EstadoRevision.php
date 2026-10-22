<?php

namespace App\Enums;

/**
 * Estado de revisión de un documento de seguimiento (documento_seguimiento.estado_revision).
 */
enum EstadoRevision: string
{
    use HasValues;

    case Pendiente = 'PENDIENTE';
    case Aprobado = 'APROBADO';
    case Observado = 'OBSERVADO';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Por revisar',
            self::Aprobado => 'Aprobado',
            self::Observado => 'Con observaciones',
        };
    }
}
