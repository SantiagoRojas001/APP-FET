<?php

namespace App\Services;

use App\Models\Estudiante;
use App\Models\Parametro;

/**
 * Habilitación para pasantía o práctica, con los parámetros que define la
 * administración:
 *
 * - Solo se registran los estudiantes que cursan uno de los semestres
 *   habilitados (3.º, 5.º y 9.º por defecto) y eligieron pasantía o práctica.
 * - Quedan habilitados cuando el promedio que registra el docente alcanza el
 *   mínimo (4,2 por defecto). El resultado se guarda al registrar el promedio,
 *   así un cambio posterior de los parámetros no altera decisiones ya tomadas.
 */
class ElegibilidadService
{
    public function semestreHabilitado(int $semestre): bool
    {
        return in_array($semestre, Parametro::semestresHabilitados(), true);
    }

    public function promedioSuficiente(float $promedio): bool
    {
        return round($promedio, 2) >= round(Parametro::promedioMinimo(), 2);
    }

    public function habilitadoParaPractica(Estudiante $estudiante): bool
    {
        return $estudiante->habilitado === true && ! $estudiante->es_egresado;
    }
}
