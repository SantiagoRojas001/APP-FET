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
            Rol::Admin => true,
            Rol::Docente => in_array($vinculacion->tutor_id, [null, $usuario->id], true)
                && ($usuario->programa_id === null || $usuario->programa_id === $vinculacion->estudiante()->value('programa_id')),
            Rol::Director => $usuario->programa_id !== null && $usuario->programa_id === $vinculacion->estudiante()->value('programa_id'),
            Rol::Estudiante => $usuario->loadMissing('estudiante')->estudiante?->id === $vinculacion->estudiante_id,
            Rol::Empresa => $usuario->loadMissing('empresa')->empresa?->id === $vinculacion->empresa_id,
        };
    }

    /** Mientras la práctica siga activa, suben documentos el estudiante, su docente y la empresa. */
    public function subirDocumento(Usuario $usuario, Vinculacion $vinculacion): bool
    {
        return $vinculacion->estaActiva() && (
            $this->esElEstudiante($usuario, $vinculacion)
            || $this->esLaEmpresa($usuario, $vinculacion)
            || $this->revisarDocumentos($usuario, $vinculacion)
        );
    }

    /** Las horas semanales las reporta (y corrige) el estudiante mientras la práctica sigue activa. */
    public function registrarEvidencia(Usuario $usuario, Vinculacion $vinculacion): bool
    {
        return $this->esElEstudiante($usuario, $vinculacion) && $vinculacion->estaActiva();
    }

    /** La empresa receptora aprueba las horas semanales mientras la práctica sigue activa. */
    public function revisarEvidencias(Usuario $usuario, Vinculacion $vinculacion): bool
    {
        return $this->esLaEmpresa($usuario, $vinculacion) && $vinculacion->estaActiva();
    }

    /** El docente tutor (o cualquier docente del programa si aún no hay tutor) cierra la práctica. */
    public function finalizar(Usuario $usuario, Vinculacion $vinculacion): bool
    {
        return $usuario->rol === Rol::Docente && $this->view($usuario, $vinculacion)
            && in_array($vinculacion->tutor_id, [null, $usuario->id], true);
    }

    /** Califican la empresa receptora y el docente tutor. */
    public function calificar(Usuario $usuario, Vinculacion $vinculacion): bool
    {
        return $this->finalizar($usuario, $vinculacion) || $this->esLaEmpresa($usuario, $vinculacion);
    }

    /** El practicante puede evaluar a la empresa (es opcional). */
    public function evaluar(Usuario $usuario, Vinculacion $vinculacion): bool
    {
        return $this->esElEstudiante($usuario, $vinculacion);
    }

    /** Los reportes ligados a la práctica pueden involucrar a cualquiera de las partes: solo los ve la FET. */
    public function verReportes(Usuario $usuario, Vinculacion $vinculacion): bool
    {
        return $usuario->tieneRol(Rol::Docente, Rol::Admin);
    }

    /** Los documentos los revisa el docente; la empresa puede dar su visto bueno. */
    public function revisarDocumentos(Usuario $usuario, Vinculacion $vinculacion): bool
    {
        return $usuario->rol === Rol::Docente && $this->view($usuario, $vinculacion);
    }

    /** El docente se asigna como tutor. */
    public function update(Usuario $usuario, Vinculacion $vinculacion): bool
    {
        return $usuario->rol === Rol::Docente && $this->view($usuario, $vinculacion);
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
