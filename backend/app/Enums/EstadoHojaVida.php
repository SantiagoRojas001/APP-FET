<?php

namespace App\Enums;

/**
 * Revisión docente de la hoja de vida (hoja_vida.estado). Solo las aprobadas
 * las ven las empresas; las que tienen observaciones vuelven al estudiante
 * para que las corrija y las envíe de nuevo.
 */
enum EstadoHojaVida: string
{
    use HasValues;

    case Borrador = 'BORRADOR';
    case EnRevision = 'EN_REVISION';
    case Aprobada = 'APROBADA';
    case ConObservaciones = 'CON_OBSERVACIONES';

    public function label(): string
    {
        return match ($this) {
            self::Borrador => 'Sin enviar a revisión',
            self::EnRevision => 'En revisión docente',
            self::Aprobada => 'Aprobada por el docente',
            self::ConObservaciones => 'Con recomendaciones por corregir',
        };
    }
}
