<?php

namespace App\Notifications;

use App\Models\Reporte;

/**
 * A los administradores: llegó un reporte nuevo.
 */
class ReporteRecibido extends Aviso
{
    public function __construct(private readonly Reporte $reporte) {}

    protected function titulo(): string
    {
        return 'Nuevo reporte: '.$this->reporte->asunto;
    }

    protected function mensaje(): string
    {
        return "{$this->reporte->autor->correo} reportó un problema ({$this->reporte->categoria->label()}).";
    }

    protected function enlace(): string
    {
        return '/reportes';
    }
}
