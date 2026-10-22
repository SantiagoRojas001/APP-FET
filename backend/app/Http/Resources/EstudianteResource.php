<?php

namespace App\Http\Resources;

use App\Models\Estudiante;
use App\Services\ElegibilidadService;
use App\Services\HojaVidaService;
use Illuminate\Http\Request;

/**
 * Perfil del estudiante. La hoja de vida, competencias y proyectos se
 * incluyen solo si se cargaron.
 *
 * @mixin Estudiante
 */
class EstudianteResource extends Recurso
{
    public function toArray(Request $request): array
    {
        $hojaVida = app(HojaVidaService::class);

        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'documento' => $this->when($this->puedeVerDatosSensibles($request), $this->documento),
            'nombres' => $this->nombres,
            'apellidos' => $this->apellidos,
            'nombre_completo' => $this->nombre_completo,
            'correo' => $this->whenLoaded('usuario', fn () => $this->usuario->correo),
            'telefono' => $this->telefono,
            'semestre_actual' => $this->semestre_actual,
            'es_egresado' => $this->es_egresado,
            'disponibilidad' => $this->disponibilidad,
            'programa' => new ProgramaResource($this->whenLoaded('programa')),
            'habilitado_para_practica' => app(ElegibilidadService::class)->habilitadoParaPractica($this->resource),
            'hoja_vida' => $this->whenLoaded('hojaVida', fn () => [
                'perfil_profesional' => $this->hojaVida?->perfil_profesional,
                'formacion' => $this->hojaVida?->formacion,
                'actualizado_en' => $this->hojaVida?->actualizado_en?->toIso8601String(),
                'porcentaje' => $hojaVida->porcentaje($this->resource),
                'secciones' => $hojaVida->secciones($this->resource),
                // Solo en el perfil completo; los listados no cargan las experiencias.
                'experiencias' => $this->when($this->hojaVida?->relationLoaded('experiencias'), fn () => $this->hojaVida->experiencias->map(fn ($e) => [
                    'id' => $e->id,
                    'cargo' => $e->cargo,
                    'organizacion' => $e->organizacion,
                    'fecha_inicio' => $e->fecha_inicio->toDateString(),
                    'fecha_fin' => $e->fecha_fin?->toDateString(),
                    'descripcion' => $e->descripcion,
                ])),
            ]),
            'competencias' => $this->whenLoaded('competencias', fn () => $this->competencias->map(fn ($c) => [
                'id' => $c->id,
                'nombre' => $c->nombre,
                'nivel' => $c->pivot->nivel,
            ])),
            'proyectos' => $this->whenLoaded('proyectos', fn () => $this->proyectos->map(fn ($p) => [
                'id' => $p->id,
                'titulo' => $p->titulo,
                'descripcion' => $p->descripcion,
                'url_repositorio' => $p->url_repositorio,
                'semestre' => $p->semestre,
            ])),
        ];
    }

    /** El documento de identidad solo lo ven el propio estudiante, docentes y administradores. */
    private function puedeVerDatosSensibles(Request $request): bool
    {
        $usuario = $request->user();

        return $usuario && ($usuario->id === $this->usuario_id || in_array($usuario->rol->value, ['DOCENTE', 'ADMIN'], true));
    }
}
