<?php

namespace App\Policies;

use App\Enums\Rol;
use App\Models\Usuario;
use App\Models\Vinculacion;

/**
 * Quién puede ver una vinculación, hacer su seguimiento y cerrarla.
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
        return $this->esElEstudiante($usuario, $vinculacion) && $vinculacion->estaActiva();
    }

    /** Las horas semanales las reporta (y corrige) el estudiante mientras la práctica sigue activa. */
    public function registrarEvidencia(Usuario $usuario, Vinculacion $vinculacion): bool
    {
        return $this->subirDocumento($usuario, $vinculacion);
    }

    /** La empresa receptora aprueba las horas semanales mientras la práctica sigue activa. */
    public function revisarEvidencias(Usuario $usuario, Vinculacion $vinculacion): bool
    {
        return $this->esLaEmpresa($usuario, $vinculacion) && $vinculacion->estaActiva();
    }

    /** El docente tutor (o cualquier docente si aún no hay tutor) la finaliza anticipadamente. */
    public function finalizar(Usuario $usuario, Vinculacion $vinculacion): bool
    {
        return $usuario->rol === Rol::Docente && in_array($vinculacion->tutor_id, [null, $usuario->id], true);
    }

    /** Evalúan el docente tutor, la empresa receptora y el propio practicante. */
    public function evaluar(Usuario $usuario, Vinculacion $vinculacion): bool
    {
        return $this->finalizar($usuario, $vinculacion)
            || $this->esLaEmpresa($usuario, $vinculacion)
            || $this->esElEstudiante($usuario, $vinculacion);
    }

    /** Los reportes ligados a la práctica pueden involucrar a cualquiera de las partes: solo los ve la FET. */
    public function verReportes(Usuario $usuario, Vinculacion $vinculacion): bool
    {
        return $usuario->tieneRol(Rol::Docente, Rol::Admin);
    }

    /** Los documentos los revisa el docente; la empresa solo los consulta, para aliviar su carga. */
    public function revisarDocumentos(Usuario $usuario, Vinculacion $vinculacion): bool
    {
        return $usuario->rol === Rol::Docente;
    }

    /** El docente se asigna como tutor. */
    public function update(Usuario $usuario, Vinculacion $vinculacion): bool
    {
        return $usuario->rol === Rol::Docente;
    }

    private function esElEstudiante(Usuario $usuario, Vinculacion $vinculacion): bool
    {
        return $usuario->rol === Rol::Estudiante && $usuario->loadMissing('estudiante')->estudiante?->id === $vinculacion->estudiante_id;
    }

    private function esLaEmpresa(Usuario $usuario, Vinculacion $vinculacion): bool
    {
        return $usuario->rol === Rol::Empresa && $usuario->loadMissing('empresa')->empresa?->id === $vinculacion->empresa_id;
    }
}
