<?php

namespace App\Enums;

/**
 * Quién evalúa a quién al cerrar una práctica (evaluacion.tipo).
 */
enum TipoEvaluacion: string
{
    use HasValues;

    case DocenteAPracticante = 'DOCENTE_A_PRACTICANTE';
    case EmpresaAPracticante = 'EMPRESA_A_PRACTICANTE';
    case PracticanteAEmpresa = 'PRACTICANTE_A_EMPRESA';

    public function label(): string
    {
        return match ($this) {
            self::DocenteAPracticante => 'Evaluación del docente al practicante',
            self::EmpresaAPracticante => 'Evaluación de la empresa al practicante',
            self::PracticanteAEmpresa => 'Evaluación del practicante a la empresa',
        };
    }

    /** Tipo de evaluación que llena cada rol. */
    public static function delRol(Rol $rol): ?self
    {
        return match ($rol) {
            Rol::Docente => self::DocenteAPracticante,
            Rol::Empresa => self::EmpresaAPracticante,
            Rol::Estudiante => self::PracticanteAEmpresa,
            default => null,
        };
    }
}
