<?php

namespace App\Enums;

/**
 * Ciclo de una cita (cita.estado): la empresa la asigna y, después de
 * realizada, selecciona o descarta. El docente autoriza a los seleccionados y
 * el director de programa envía la carta de presentación, con la que empieza
 * la práctica.
 */
enum EstadoCita: string
{
    use HasValues;

    case Programada = 'PROGRAMADA';
    case Seleccionado = 'SELECCIONADO';
    case Descartado = 'DESCARTADO';
    case Autorizado = 'AUTORIZADO';
    case NoAutorizado = 'NO_AUTORIZADO';
    case Presentado = 'PRESENTADO';
    case Cancelada = 'CANCELADA';

    public function label(): string
    {
        return match ($this) {
            self::Programada => 'Cita programada',
            self::Seleccionado => 'Seleccionado, en autorización docente',
            self::Descartado => 'No seleccionado',
            self::Autorizado => 'Autorizado, en espera de la carta de presentación',
            self::NoAutorizado => 'No autorizado por el docente',
            self::Presentado => 'Carta de presentación enviada',
            self::Cancelada => 'Cancelada',
        };
    }

    /** Estados en los que el proceso con la empresa sigue abierto. */
    public static function abiertos(): array
    {
        return [self::Programada, self::Seleccionado, self::Autorizado];
    }
}
