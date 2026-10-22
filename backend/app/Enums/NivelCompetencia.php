<?php

namespace App\Enums;

/**
 * Nivel de dominio de una competencia (estudiante_competencia.nivel).
 */
enum NivelCompetencia: string
{
    use HasValues;

    case Basico = 'BASICO';
    case Intermedio = 'INTERMEDIO';
    case Avanzado = 'AVANZADO';

    public function label(): string
    {
        return match ($this) {
            self::Basico => 'Básico',
            self::Intermedio => 'Intermedio',
            self::Avanzado => 'Avanzado',
        };
    }
}
