<?php

namespace App\Notifications;

use App\Models\Vinculacion;

/**
 * Al estudiante, cuando el director envía la carta de presentación a la
 * empresa: empieza su práctica.
 */
class PracticaAutorizada extends Aviso
{
    public function __construct(private readonly Vinculacion $vinculacion) {}

    protected function titulo(): string
    {
        return "Empieza tu {$this->modalidad()}";
    }

    protected function mensaje(): string
    {
        $inicio = $this->vinculacion->fecha_inicio->format('d/m/Y');

        return "La FET autorizó tu {$this->modalidad()} en {$this->vinculacion->empresa->razon_social} desde el {$inicio}. "
            .'El director de tu programa envió la carta de presentación a la empresa. Sube tu ARL y reporta tus horas cada semana en APP FET.';
    }

    protected function enlace(): string
    {
        return '/pasantia';
    }

    private function modalidad(): string
    {
        return mb_strtolower($this->vinculacion->tipo->label());
    }
}
