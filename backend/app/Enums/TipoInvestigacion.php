<?php

namespace App\Enums;

/**
 * Apartados de la experiencia académica investigativa del formato PA-GTH-F-10
 * (actividad_investigativa.tipo).
 */
enum TipoInvestigacion: string
{
    use HasValues;

    case Grupo = 'GRUPO';
    case Proyecto = 'PROYECTO';
    case TutoriaPosgrado = 'TUTORIA_POSGRADO';
    case TutoriaPregrado = 'TUTORIA_PREGRADO';
    case ParEvaluador = 'PAR_EVALUADOR';
    case InformeTecnico = 'INFORME_TECNICO';
    case Publicacion = 'PUBLICACION';
    case Organizacion = 'ORGANIZACION';
    case Ponencia = 'PONENCIA';

    public function label(): string
    {
        return match ($this) {
            self::Grupo => 'Pertenencia a grupos de investigación',
            self::Proyecto => 'Proyectos de investigación desarrollados',
            self::TutoriaPosgrado => 'Tutorías de trabajos de culminación de posgrado',
            self::TutoriaPregrado => 'Tutorías de trabajos de culminación de pregrado',
            self::ParEvaluador => 'Par evaluador / jurado en comités de evaluación',
            self::InformeTecnico => 'Trabajos e informes técnicos',
            self::Publicacion => 'Publicaciones',
            self::Organizacion => 'Pertenencia a organizaciones',
            self::Ponencia => 'Ponencias',
        };
    }
}
