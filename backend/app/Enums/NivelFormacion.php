<?php

namespace App\Enums;

/**
 * Nivel de un título en la hoja de vida (formacion_academica.nivel), en el
 * orden del formato PA-GTH-F-10.
 */
enum NivelFormacion: string
{
    use HasValues;

    case Maestria = 'MAESTRIA';
    case Especializacion = 'ESPECIALIZACION';
    case Pregrado = 'PREGRADO';
    case Tecnologo = 'TECNOLOGO';
    case Tecnico = 'TECNICO';

    public function label(): string
    {
        return match ($this) {
            self::Maestria => 'Maestría',
            self::Especializacion => 'Especialización',
            self::Pregrado => 'Pregrado',
            self::Tecnologo => 'Tecnólogo',
            self::Tecnico => 'Técnico',
        };
    }
}
