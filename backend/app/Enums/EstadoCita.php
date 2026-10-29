<?php

namespace App\Enums;

/**
 * Ciclo de una cita (cita.estado): la empresa la asigna a un estudiante con
 * hoja de vida aprobada por el docente y, según la cita, lo acepta o lo
 * rechaza. Si lo acepta, el director de programa envía la carta de
 * presentación, con la que empieza la práctica.
 */
enum EstadoCita: string
{
    use HasValues;

    case Programada = 'PROGRAMADA';
    case Aceptado = 'ACEPTADO';
    case Rechazado = 'RECHAZADO';
    case Presentado = 'PRESENTADO';
    case Cancelada = 'CANCELADA';

    public function label(): string
    {
        return match ($this) {
            self::Programada => 'Cita programada',
            self::Aceptado => 'Aceptado, en espera de la carta de presentación',
            self::Rechazado => 'No aceptado por la empresa',
            self::Presentado => 'Carta de presentación enviada',
            self::Cancelada => 'Cancelada',
        };
    }

    /** Estados en los que el proceso con la empresa sigue abierto. */
    public static function abiertos(): array
    {
        return [self::Programada, self::Aceptado];
    }
}
