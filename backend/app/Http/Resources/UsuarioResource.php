<?php

namespace App\Http\Resources;

use App\Enums\Rol;
use App\Models\Usuario;
use Illuminate\Http\Request;

/** @mixin Usuario */
class UsuarioResource extends Recurso
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'correo' => $this->correo,
            'rol' => $this->enum($this->rol),
            'activo' => $this->activo,
            'tema' => $this->tema ?? 'system',
            'creado_en' => $this->creado_en?->toIso8601String(),
            // Sin política aceptada, la persona aún no usa su invitación.
            'pendiente_activacion' => $this->whenCounted('consentimientos', fn (int $n) => $n === 0),
            'nombre' => match ($this->rol) {
                Rol::Estudiante => $this->estudiante?->nombre_completo,
                Rol::Empresa => $this->empresa?->razon_social,
                default => null,
            } ?? $this->correo,
            // El estudiante con pasantía activa trabaja en otro entorno (seguimiento en vez de vacantes).
            // Solo cuando se cargó la relación, para no generar consultas N+1 en los listados.
            'vinculacion_activa_id' => $this->when(
                $this->rol === Rol::Estudiante && $this->relationLoaded('estudiante') && $this->estudiante?->relationLoaded('vinculacionActiva'),
                fn () => $this->estudiante?->vinculacionActiva?->id,
            ),
            'estudiante' => $this->when($this->rol === Rol::Estudiante && $this->relationLoaded('estudiante'), fn () => new EstudianteResource($this->estudiante)),
            'empresa' => $this->when($this->rol === Rol::Empresa && $this->relationLoaded('empresa'), fn () => new EmpresaResource($this->empresa)),
        ];
    }
}
