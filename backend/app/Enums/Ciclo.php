<?php

namespace App\Enums;

/**
 * Ciclo académico de un programa por ciclos propedéuticos, según el semestre:
 * técnico hasta 4.º, tecnólogo en 5.º y 6.º y profesional de 7.º a 10.º.
 */
enum Ciclo: string
{
    use HasValues;

    case Tecnico = 'TECNICO';
    case Tecnologo = 'TECNOLOGO';
    case Profesional = 'PROFESIONAL';

    public static function deSemestre(int $semestre): self
    {
        return match (true) {
            $semestre <= 4 => self::Tecnico,
            $semestre <= 6 => self::Tecnologo,
            default => self::Profesional,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Tecnico => 'Técnico',
            self::Tecnologo => 'Tecnólogo',
            self::Profesional => 'Profesional',
        };
    }

    /** @return array{0: int, 1: int} primer y último semestre del ciclo */
    public function semestres(): array
    {
        return match ($this) {
            self::Tecnico => [1, 4],
            self::Tecnologo => [5, 6],
            self::Profesional => [7, 10],
        };
    }
}
