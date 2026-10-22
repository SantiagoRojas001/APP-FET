<?php

namespace App\Policies;

use App\Enums\Rol;
use App\Models\Usuario;
use App\Models\Vinculacion;

/**
 * Quién puede ver una vinculación y gestionar sus documentos de seguimiento.
 */
class VinculacionPolicy
{
    public function view(Usuario $usuario, Vinculacion $vinculacion): bool
    {
        return match ($usuario->rol) {
            Rol::Admin, Rol::Docente => true,
            Rol::Estudiante => $usuario->loadMissing('estudiante')->estudiante?->id === $vinculacion->estudiante_id,
            Rol::Empresa => $usuario->loadMissing('empresa')->empresa?->id === $vinculacion->empresa_id,
        };
    }

    /** Solo el estudiante sube sus documentos, y mientras la vinculación siga activa. */
    public function subirDocumento(Usuario $usuario, Vinculacion $vinculacion): bool
    {
        return $usuario->rol === Rol::Estudiante
            && $usuario->loadMissing('estudiante')->estudiante?->id === $vinculacion->estudiante_id
            && $vinculacion->estaActiva();
    }

    /** Revisan el docente y la empresa receptora. */
    public function revisarDocumentos(Usuario $usuario, Vinculacion $vinculacion): bool
    {
        return $usuario->rol === Rol::Docente
            || ($usuario->rol === Rol::Empresa && $usuario->loadMissing('empresa')->empresa?->id === $vinculacion->empresa_id);
    }

    /** El docente cierra la vinculación y decide si se reconoce como práctica. */
    public function update(Usuario $usuario, Vinculacion $vinculacion): bool
    {
        return $usuario->rol === Rol::Docente;
    }
}
