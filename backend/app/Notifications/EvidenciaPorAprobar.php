<?php

namespace App\Notifications;

use App\Models\Vinculacion;

/**
 * A la empresa: el practicante reportó una semana de horas para aprobar.
 */
class EvidenciaPorAprobar extends Aviso
{
    public function __construct(private readonly Vinculacion $vinculacion) {}

    protected function titulo(): string
    {
        return 'Horas semanales por aprobar';
    }

    protected function mensaje(): string
    {
        return "{$this->vinculacion->estudiante->nombre_completo} reportó las horas de una semana. "
            .'Apruébalas o devuélvelas con un comentario; puedes aprobar todas las pendientes de una vez.';
    }

    protected function enlace(): string
    {
        return "/vinculacion/{$this->vinculacion->id}";
    }
}
