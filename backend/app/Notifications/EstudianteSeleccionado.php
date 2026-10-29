<?php

namespace App\Notifications;

use App\Models\Cita;

/**
 * A los docentes: una empresa seleccionó a un estudiante después de la cita y
 * falta volver a aprobarlo.
 */
class EstudianteSeleccionado extends Aviso
{
    public function __construct(private readonly Cita $cita) {}

    protected function titulo(): string
    {
        return 'Estudiante seleccionado por aprobar';
    }

    protected function mensaje(): string
    {
        return "{$this->cita->empresa->razon_social} seleccionó a {$this->cita->estudiante->nombre_completo} después de su cita. "
            .'Revísalo y aprueba para que el director de programa envíe la carta de presentación.';
    }

    protected function enlace(): string
    {
        return '/autorizaciones';
    }
}
