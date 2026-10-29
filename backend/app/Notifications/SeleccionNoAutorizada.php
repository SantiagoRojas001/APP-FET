<?php

namespace App\Notifications;

use App\Models\Postulacion;

/**
 * Al estudiante y a la empresa: el docente no autorizó la práctica.
 */
class SeleccionNoAutorizada extends Aviso
{
    public function __construct(private readonly Postulacion $postulacion) {}

    protected function titulo(): string
    {
        return 'La práctica no fue autorizada';
    }

    protected function mensaje(): string
    {
        return "La FET no autorizó la práctica de {$this->postulacion->estudiante->nombre_completo} en "
            ."{$this->postulacion->vacante->empresa->razon_social}. Motivo: {$this->postulacion->observacion}";
    }
}
