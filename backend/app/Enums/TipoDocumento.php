<?php

namespace App\Enums;

/**
 * Tipo de documento de seguimiento (documento_seguimiento.tipo).
 */
enum TipoDocumento: string
{
    use HasValues;

    case ControlAsistencia = 'CONTROL_ASISTENCIA';
    case DiarioCampo = 'DIARIO_CAMPO';
    case InformeAvance = 'INFORME_AVANCE';
    case InformeFinal = 'INFORME_FINAL';

    public function label(): string
    {
        return match ($this) {
            self::ControlAsistencia => 'Control de asistencia',
            self::DiarioCampo => 'Diario de campo',
            self::InformeAvance => 'Informe de avance',
            self::InformeFinal => 'Informe final',
        };
    }
}
