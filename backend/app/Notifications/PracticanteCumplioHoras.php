<?php

namespace App\Notifications;

use App\Models\Vinculacion;

/**
 * A la empresa y al docente tutor: el practicante completó las horas, así que
 * ya pueden evaluarlo. La evaluación de la empresa cierra la práctica.
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
            .'Ya se puede evaluar: cuando la empresa responda su evaluación, la práctica culmina.';
    }

    protected function enlace(): string
    {
        return "/vinculacion/{$this->vinculacion->id}";
    }
}
