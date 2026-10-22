<?php

namespace App\Enums;

/**
 * Ciclo de una postulación: el docente la revisa y, si la aprueba, la empresa decide (postulacion.estado).
 */
enum EstadoPostulacion: string
{
    use HasValues;

    case Enviada = 'ENVIADA';
    case Aprobada = 'APROBADA';
    case Rechazada = 'RECHAZADA';
    case Aceptada = 'ACEPTADA';
    case Descartada = 'DESCARTADA';

    public function label(): string
    {
        return match ($this) {
            self::Enviada => 'En revisión docente',
            self::Aprobada => 'Aprobada por el docente',
            self::Rechazada => 'Rechazada por el docente',
            self::Aceptada => 'Aceptada por la empresa',
            self::Descartada => 'No seleccionada',
        };
    }
}
