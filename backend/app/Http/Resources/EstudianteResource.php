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
            'modalidad_grado' => $this->enum($this->modalidad_grado),
            'ciclo' => $this->enum($this->ciclo()),
            // El promedio y su resultado solo los ve la FET y el propio estudiante.
            'promedio' => $this->when($this->puedeVerDatosSensibles($request), $this->promedio),
            'habilitado' => $this->when($this->puedeVerDatosSensibles($request), $this->habilitado),
            'resultado_enviado_en' => $this->when($this->puedeVerDatosSensibles($request), fn () => $this->resultado_enviado_en?->toIso8601String()),
            // Práctica en curso (si se cargó la vinculación activa): tipo y empresa.
            'practica_actual' => $this->whenLoaded('vinculacionActiva', fn () => $this->vinculacionActiva ? [
                'id' => $this->vinculacionActiva->id,
                'tipo' => $this->enum($this->vinculacionActiva->tipo),
                'empresa' => $this->vinculacionActiva->relationLoaded('empresa') ? $this->vinculacionActiva->empresa?->razon_social : null,
            ] : null),
            'recomendado' => $this->whenHas('recomendado', fn () => (bool) $this->recomendado),
            'citas' => $this->whenLoaded('citas', fn () => $this->citas->map(fn ($c) => [
                'id' => $c->id,
                'estado' => $this->enum($c->estado),
                'fecha_hora' => $c->fecha_hora->toIso8601String(),
            ])->values()),
            'recomendaciones' => $this->whenLoaded('recomendaciones', fn () => $this->recomendaciones->map(fn ($r) => [
                'vacante' => $r->relationLoaded('vacante') ? $r->vacante?->titulo : null,
                'comentario' => $r->comentario,
                'docente' => $r->relationLoaded('docente') ? $r->docente?->correo : null,
            ])->values()),
            'hoja_vida_habilitada' => $this->whenLoaded('vinculacionActiva', fn () => $this->vinculacionActiva === null),
            'hoja_vida' => $this->whenLoaded('hojaVida', fn () => $this->hojaVida($hojaVida)),
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

    /**
     * Hoja de vida en el formato PA-GTH-F-10. Las listas se incluyen solo en el
     * perfil completo; los listados cargan la hoja sin sus relaciones.
     *
     * @return array<string, mixed>
     */
    private function hojaVida(HojaVidaService $servicio): array
    {
        $hoja = $this->hojaVida;
        $lista = fn (string $relacion, callable $fila) => $this->when(
            $hoja?->relationLoaded($relacion),
            fn () => $hoja->{$relacion}->map($fila)->values(),
        );

        return [
            'perfil_profesional' => $hoja?->perfil_profesional,
            'estado' => $this->enum($hoja?->estado),
            'observaciones' => $hoja?->observaciones,
            'enviada_en' => $hoja?->enviada_en?->toIso8601String(),
            'revisada_en' => $hoja?->revisada_en?->toIso8601String(),
            'actualizado_en' => $hoja?->actualizado_en?->toIso8601String(),
            'porcentaje' => $servicio->porcentaje($this->resource),
            'secciones' => $servicio->secciones($this->resource),
            'url_foto' => $hoja?->foto_url ? "/api/estudiantes/{$this->id}/foto" : null,
            'url_firma' => $hoja?->firma_url ? "/api/estudiantes/{$this->id}/firma" : null,
            'datos_personales' => [
                'fecha_nacimiento' => $hoja?->fecha_nacimiento?->toDateString(),
                'lugar_nacimiento' => $hoja?->lugar_nacimiento,
                'estado_civil' => $hoja?->estado_civil,
                'lugar_residencia' => $hoja?->lugar_residencia,
                'direccion' => $hoja?->direccion,
                'rh' => $hoja?->rh,
                'contacto_emergencia_nombre' => $hoja?->contacto_emergencia_nombre,
                'contacto_emergencia_telefono' => $hoja?->contacto_emergencia_telefono,
            ],
            'formaciones' => $lista('formaciones', fn ($f) => [
                'nivel' => $this->enum($f->nivel),
                'titulo' => $f->titulo,
                'institucion' => $f->institucion,
                'pais_ciudad' => $f->pais_ciudad,
                'fecha_grado' => $f->fecha_grado?->toDateString(),
                'modalidad' => $f->modalidad,
            ]),
            'cursos' => $lista('cursos', fn ($c) => [
                'tema' => $c->tema,
                'duracion_horas' => $c->duracion_horas,
                'fecha' => $c->fecha->format('Y-m'),
                'institucion' => $c->institucion,
                'modalidad' => $c->modalidad,
            ]),
            'experiencias' => $lista('experiencias', fn ($e) => [
                'cargo' => $e->cargo,
                'organizacion' => $e->organizacion,
                'direccion' => $e->direccion,
                'telefono' => $e->telefono,
                'jefe_inmediato' => $e->jefe_inmediato,
                'dedicacion' => $e->dedicacion,
                'fecha_inicio' => $e->fecha_inicio->toDateString(),
                'fecha_fin' => $e->fecha_fin?->toDateString(),
                'descripcion' => $e->descripcion,
            ]),
            'investigaciones' => $lista('investigaciones', fn ($i) => [
                'tipo' => $this->enum($i->tipo),
                'descripcion' => $i->descripcion,
                'periodo' => $i->periodo,
            ]),
            'referencias' => $lista('referencias', fn ($r) => [
                'tipo' => $this->enum($r->tipo),
                'nombre' => $r->nombre,
                'ocupacion' => $r->ocupacion,
                'parentesco' => $r->parentesco,
                'telefono' => $r->telefono,
            ]),
        ];
    }

    /** Documento, promedio y habilitación solo los ven el propio estudiante, docentes, directores y administradores. */
    private function puedeVerDatosSensibles(Request $request): bool
    {
        $usuario = $request->user();

        return $usuario && ($usuario->id === $this->usuario_id || in_array($usuario->rol->value, ['DOCENTE', 'ADMIN', 'DIRECTOR'], true));
    }
}
