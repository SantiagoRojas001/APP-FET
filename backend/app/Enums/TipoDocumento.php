<?php

namespace App\Enums;

/**
 * Tipo de documento de seguimiento (documento_seguimiento.tipo).
 */
enum TipoDocumento: string
{
    use HasValues;

    case Arl = 'ARL';
    case Bitacora = 'BITACORA';
    case ControlAsistencia = 'CONTROL_ASISTENCIA';
    case InformeAvance = 'INFORME_AVANCE';
    case InformeFinal = 'INFORME_FINAL';

    public function label(): string
    {
        return match ($this) {
            self::Arl => 'Certificado de ARL',
            self::Bitacora => 'Bitácora',
            self::ControlAsistencia => 'Control de asistencia',
            self::InformeAvance => 'Informe de avance',
            self::InformeFinal => 'Informe final',
        };
    }
}
