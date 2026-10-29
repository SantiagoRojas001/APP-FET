<?php

namespace App\Notifications;

use App\Models\Cita;

/**
 * Al director del programa (o a la administración si el programa no tiene
 * director): el docente aprobó a un estudiante y falta la carta de presentación.
 */
class CartaPorEnviar extends Aviso
{
    public function __construct(private readonly Cita $cita) {}

    protected function titulo(): string
    {
        return 'Carta de presentación por enviar';
    }

    protected function mensaje(): string
    {
        return "El docente aprobó la práctica de {$this->cita->estudiante->nombre_completo} ({$this->cita->estudiante->programa->nombre}) "
            ."en {$this->cita->empresa->razon_social}. Envía la carta de presentación para que inicie.";
    }

    protected function enlace(): string
    {
        return '/cartas';
    }
}
