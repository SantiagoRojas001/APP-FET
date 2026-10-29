<?php

namespace App\Enums;

/**
 * Ciclo de una postulación: la empresa la recibe y selecciona o descarta;
 * si selecciona, el docente autoriza la práctica (postulacion.estado).
 */
enum EstadoPostulacion: string
{
    use HasValues;

    case Enviada = 'ENVIADA';
    case Seleccionada = 'SELECCIONADA';
    case Aceptada = 'ACEPTADA';
    case Rechazada = 'RECHAZADA';
    case Descartada = 'DESCARTADA';

    public function label(): string
    {
        return match ($this) {
            self::Enviada => 'Enviada a la empresa',
            self::Seleccionada => 'Seleccionada, en autorización docente',
            self::Aceptada => 'Autorizada',
            self::Rechazada => 'No autorizada por el docente',
            self::Descartada => 'No seleccionada',
        };
    }
}
