<?php

namespace App\Enums;

/**
 * Tipo de problema que reporta un usuario (reporte.categoria).
 */
enum CategoriaReporte: string
{
    use HasValues;

    case FallaPlataforma = 'FALLA_PLATAFORMA';
    case Practica = 'PRACTICA';
    case Datos = 'DATOS';
    case Otro = 'OTRO';

    public function label(): string
    {
        return match ($this) {
            self::FallaPlataforma => 'Falla de la plataforma',
            self::Practica => 'Problema en la práctica',
            self::Datos => 'Datos incorrectos',
            self::Otro => 'Otro',
        };
    }
}
