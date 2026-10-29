<?php

namespace App\Notifications;

use App\Models\Reporte;

/**
 * A quien reportó: la administración respondió o cambió el estado del reporte.
 */
class ReporteAtendido extends Aviso
{
    public function __construct(private readonly Reporte $reporte) {}

    protected function titulo(): string
    {
        return "Tu reporte está {$this->estado()}";
    }

    protected function mensaje(): string
    {
        $mensaje = "Tu reporte «{$this->reporte->asunto}» está {$this->estado()}.";

        return $this->reporte->respuesta ? "{$mensaje} Respuesta: {$this->reporte->respuesta}" : $mensaje;
    }

    protected function enlace(): string
    {
        return '/reportar';
    }

    private function estado(): string
    {
        return mb_strtolower($this->reporte->estado->label());
    }
}
