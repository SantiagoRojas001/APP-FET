<?php

namespace App\Enums;

/**
 * Estado de una práctica o contratación en curso (vinculacion.estado).
 */
enum EstadoVinculacion: string
{
    use HasValues;

    case Activa = 'ACTIVA';
    case Finalizada = 'FINALIZADA';
    case Cancelada = 'CANCELADA';

    public function label(): string
    {
        return match ($this) {
            self::Activa => 'Activa',
            self::Finalizada => 'Finalizada',
            self::Cancelada => 'Terminada anticipadamente',
        };
    }
}
