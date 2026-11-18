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
    case ActaInicio = 'ACTA_INICIO';
    case PlanTrabajo = 'PLAN_TRABAJO';
    case RegistroAsistencia = 'REGISTRO_ASISTENCIA';
    case EvaluacionCuantitativa = 'EVALUACION_CUANTITATIVA';
    case EvaluacionConvenio = 'EVALUACION_CONVENIO';
    case Otro = 'OTRO';

    public function label(): string
    {
        return match ($this) {
            self::Arl => 'Certificado de ARL',
            self::Bitacora => 'Bitácora',
            self::ControlAsistencia => 'Control de asistencia',
            self::InformeAvance => 'Informe de avance',
            self::InformeFinal => 'Informe final',
            self::ActaInicio => 'Acta de inicio firmada',
            self::PlanTrabajo => 'Plan de trabajo firmado',
            self::RegistroAsistencia => 'Registro de asistencia firmado',
            self::EvaluacionCuantitativa => 'Evaluación cuantitativa firmada',
            self::EvaluacionConvenio => 'Evaluación de convenio firmada',
            self::Otro => 'Otro documento',
        };
    }
}
