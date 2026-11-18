<?php

namespace App\Notifications;

use App\Models\Vinculacion;

/**
 * A la empresa y al docente tutor: el practicante completó las horas, así que
 * ya pueden registrar las notas del segundo momento.
 */
class PracticanteCumplioHoras extends Aviso
{
    public function __construct(private readonly Vinculacion $vinculacion) {}

    protected function titulo(): string
    {
        return 'Un practicante completó sus horas';
    }

    protected function mensaje(): string
    {
        return "{$this->vinculacion->estudiante->nombre_completo} completó las horas de práctica. "
            .'Ya se pueden registrar las notas del segundo momento. El docente tutor cierra la práctica.';
    }

    protected function enlace(): string
    {
        return "/vinculacion/{$this->vinculacion->id}";
    }
}
