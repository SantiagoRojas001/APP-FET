<?php

namespace App\Enums;

/**
 * Rol de cada cuenta de acceso (usuario.rol).
 */
enum Rol: string
{
    use HasValues;

    case Admin = 'ADMIN';
    case Docente = 'DOCENTE';
    case Estudiante = 'ESTUDIANTE';
    case Empresa = 'EMPRESA';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Docente => 'Docente',
            self::Estudiante => 'Estudiante',
            self::Empresa => 'Empresa',
        };
    }
}
