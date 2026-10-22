<?php

namespace App\Policies;

use App\Enums\Rol;
use App\Models\Proyecto;
use App\Models\Usuario;

/**
 * Los proyectos hacen parte de la hoja de vida: los ve su dueño y quienes
 * evalúan perfiles (empresas, docentes y administración).
 */
class ProyectoPolicy
{
    public function view(Usuario $usuario, Proyecto $proyecto): bool
    {
        return $usuario->rol === Rol::Estudiante
            ? $usuario->loadMissing('estudiante')->estudiante?->id === $proyecto->estudiante_id
            : true;
    }
}
