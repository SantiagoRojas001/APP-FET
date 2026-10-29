<?php

namespace App\Http\Resources;

use App\Enums\EstadoVinculacion;
use App\Enums\Rol;
use App\Models\Usuario;
use App\Services\ElegibilidadService;
use App\Services\HojaVidaService;
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
            // Resumen para el perfil del encabezado (anillo de la hoja de vida y estado de práctica).
            // Solo para la propia cuenta (al ingresar, sin sesión aún, y en /auth/yo), nunca en los listados.
            'perfil' => $this->when(
                $this->rol === Rol::Estudiante && (! $request->user() || $request->user()->id === $this->id) && $this->relationLoaded('estudiante') && $this->estudiante !== null,
                fn () => $this->perfilEstudiante(),
            ),
            'estudiante' => $this->when($this->rol === Rol::Estudiante && $this->relationLoaded('estudiante'), fn () => new EstudianteResource($this->estudiante)),
            'empresa' => $this->when($this->rol === Rol::Empresa && $this->relationLoaded('empresa'), fn () => new EmpresaResource($this->empresa)),
        ];
    }

    /** @return array<string, mixed> */
    private function perfilEstudiante(): array
    {
        $estudiante = $this->estudiante;
        $elegibilidad = app(ElegibilidadService::class);
        $foto = $estudiante->hojaVida()->value('foto_url');

        return [
            'hoja_vida_porcentaje' => app(HojaVidaService::class)->porcentaje($estudiante),
            'habilitado_para_practica' => $elegibilidad->habilitadoParaPractica($estudiante),
            'semestre_minimo' => $elegibilidad->reglaVigente($estudiante)?->semestre_minimo,
            'empresa_practica' => $estudiante->vinculacionActiva()->with('empresa')->first()?->empresa->razon_social,
            'tiene_practicas_terminadas' => $estudiante->vinculaciones()->where('estado', '!=', EstadoVinculacion::Activa)->exists(),
            'url_foto' => $foto ? "/api/estudiantes/{$estudiante->id}/foto" : null,
        ];
    }
}
