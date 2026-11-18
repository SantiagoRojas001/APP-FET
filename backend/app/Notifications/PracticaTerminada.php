<?php

namespace App\Notifications;

use App\Models\Vinculacion;

/**
 * El docente cerró la práctica: a la empresa siempre y, si terminó antes de
 * tiempo, también al estudiante, con la justificación.
 */
class PracticaTerminada extends Aviso
{
    public function __construct(private readonly Vinculacion $vinculacion) {}

    protected function titulo(): string
    {
        return 'Práctica finalizada por el docente';
    }

    protected function mensaje(): string
    {
        $mensaje = "El docente tutor finalizó la práctica de {$this->vinculacion->estudiante->nombre_completo} "
            ."en {$this->vinculacion->empresa->razon_social}.";

        return $this->vinculacion->observacion_final
            ? "{$mensaje} Justificación: {$this->vinculacion->observacion_final}"
            : $mensaje;
    }

    protected function enlace(): string
    {
        return "/vinculacion/{$this->vinculacion->id}";
    }
}
