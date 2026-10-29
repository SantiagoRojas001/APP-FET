<?php

namespace App\Notifications;

use App\Models\Vinculacion;

/**
 * Al estudiante: la empresa lo evaluó con las horas cumplidas y su proceso terminó.
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
        return "{$this->vinculacion->empresa->razon_social} evaluó tu {$this->modalidad()}. Tu proceso culminó con éxito. "
            .'Cuéntanos cómo te fue respondiendo la evaluación de la empresa.';
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
