<?php

namespace App\Notifications;

use App\Models\Postulacion;

/**
 * A los docentes: una empresa seleccionó a un estudiante y falta autorizar su práctica.
 */
class EstudianteSeleccionado extends Aviso
{
    public function __construct(private readonly Postulacion $postulacion) {}

    protected function titulo(): string
    {
        return 'Estudiante seleccionado por autorizar';
    }

    protected function mensaje(): string
    {
        return "{$this->postulacion->vacante->empresa->razon_social} seleccionó a {$this->postulacion->estudiante->nombre_completo} "
            ."para «{$this->postulacion->vacante->titulo}». Autoriza la práctica para enviarle la carta de bienvenida.";
    }

    protected function enlace(): string
    {
        return '/postulaciones';
    }
}
