<?php

namespace App\Notifications;

use App\Models\Vinculacion;

/**
 * Al estudiante y a la empresa: el docente finalizó la práctica antes de
 * tiempo, con su justificación.
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
