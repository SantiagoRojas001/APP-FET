<?php

namespace App\Notifications;

use App\Models\Vinculacion;

/**
 * Al estudiante: su docente tutor cerró la práctica, con las horas y las notas completas.
 */
class PracticaCulminada extends Aviso
{
    public function __construct(private readonly Vinculacion $vinculacion) {}

    protected function titulo(): string
    {
        return "Tu {$this->modalidad()} culminó";
    }

    protected function mensaje(): string
    {
        return "Tu docente tutor cerró tu {$this->modalidad()} en {$this->vinculacion->empresa->razon_social}. "
            .'Si quieres, cuéntanos cómo te fue respondiendo la evaluación de la empresa.';
    }

    protected function enlace(): string
    {
        return "/vinculacion/{$this->vinculacion->id}/evaluacion";
    }

    private function modalidad(): string
    {
        return mb_strtolower($this->vinculacion->tipo->label());
    }
}
