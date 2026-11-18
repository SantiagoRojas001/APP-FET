<?php

namespace App\Enums;

/**
 * Quién califica cada parte de la evaluación cuantitativa (calificacion.parte).
 */
enum ParteCalificacion: string
{
    use HasValues;

    case Empresa = 'EMPRESA';
    case Docente = 'DOCENTE';

    public function label(): string
    {
        return match ($this) {
            self::Empresa => 'Empresa',
            self::Docente => 'Docente',
        };
    }

    public static function delRol(Rol $rol): ?self
    {
        return match ($rol) {
            Rol::Empresa => self::Empresa,
            Rol::Docente => self::Docente,
            default => null,
        };
    }
}
