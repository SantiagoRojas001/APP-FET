<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Periodo académico calculado con la fecha: hay dos semestres al año, de
 * enero a junio (AAAA-1) y de julio a diciembre (AAAA-2). No se administra.
 */
final class Periodo
{
    public static function deFecha(DateTimeInterface $fecha): string
    {
        return $fecha->format('Y').'-'.((int) $fecha->format('n') <= 6 ? 1 : 2);
    }

    public static function actual(): string
    {
        return self::deFecha(now(config('fet.zona_horaria')));
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} primer y último día del periodo */
    public static function rango(string $periodo): array
    {
        [$anio, $semestre] = array_map('intval', explode('-', $periodo));
        $inicio = CarbonImmutable::create($anio, $semestre === 1 ? 1 : 7, 1);

        return [$inicio, $inicio->addMonths(5)->endOfMonth()];
    }
}
