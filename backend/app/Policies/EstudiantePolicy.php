<?php

namespace App\Policies;

use App\Enums\Rol;
use App\Models\Estudiante;
use App\Models\Usuario;

/**
 * Quién puede ver el perfil, la hoja de vida en PDF y la foto de un estudiante.
 */
class EstudiantePolicy
{
    public function view(Usuario $usuario, Estudiante $estudiante): bool
    {
        return match ($usuario->rol) {
            Rol::Admin, Rol::Docente => true,
            Rol::Director => $usuario->programa_id !== null && $usuario->programa_id === $estudiante->programa_id,
            Rol::Estudiante => $usuario->id === $estudiante->usuario_id,
            // La empresa solo ve hojas de vida publicadas o de estudiantes con los que tiene una cita.
            Rol::Empresa => ($empresa = $usuario->loadMissing('empresa')->empresa) !== null && (
                Estudiante::query()->disponibles()->whereKey($estudiante->id)->exists()
                || $estudiante->citas()->where('empresa_id', $empresa->id)->exists()
                || $estudiante->vinculaciones()->where('empresa_id', $empresa->id)->exists()
            ),
        };
    }
}
