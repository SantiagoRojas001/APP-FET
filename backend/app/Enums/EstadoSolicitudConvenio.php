<?php

namespace App\Enums;

/**
 * Solicitud de convenio de una empresa que aún no lo tiene (solicitud_convenio.estado).
 */
enum EstadoSolicitudConvenio: string
{
    use HasValues;

    case Pendiente = 'PENDIENTE';
    case Enviado = 'ENVIADO';
    case Rechazada = 'RECHAZADA';
    case Registrada = 'REGISTRADA';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Por atender',
            self::Enviado => 'Convenio enviado',
            self::Rechazada => 'Rechazada',
            self::Registrada => 'Empresa registrada',
        };
    }
}
