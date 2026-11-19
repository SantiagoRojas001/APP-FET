<?php

namespace App\Enums;

/**
 * Formatos institucionales de una práctica (formato_practica.tipo). Sus campos
 * y firmantes están en config/formatos.php.
 */
enum TipoFormato: string
{
    use HasValues;

    case ActaInicio = 'ACTA_INICIO';
    case PlanTrabajo = 'PLAN_TRABAJO';
    case RegistroAsistencia = 'REGISTRO_ASISTENCIA';
    case EvaluacionCuantitativa = 'EVALUACION_CUANTITATIVA';
    case EvaluacionConvenio = 'EVALUACION_CONVENIO';

    /** @return array<string, mixed> */
    public function config(): array
    {
        return config("formatos.{$this->value}");
    }
}
