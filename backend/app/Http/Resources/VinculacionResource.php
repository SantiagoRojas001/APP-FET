<?php

namespace App\Http\Resources;

use App\Enums\EstadoRevision;
use App\Enums\Rol;
use App\Enums\TipoEvaluacion;
use App\Models\Vinculacion;
use App\Services\EvaluacionService;
use Illuminate\Http\Request;

/** @mixin Vinculacion */
class VinculacionResource extends Recurso
{
    public function toArray(Request $request): array
    {
        $usuario = $request->user();

        return [
            'id' => $this->id,
            'tipo' => $this->enum($this->tipo),
            'estado' => $this->enum($this->estado),
            'semestre_al_vincular' => $this->semestre_al_vincular,
            'fecha_inicio' => $this->fecha_inicio->toDateString(),
            'fecha_fin' => $this->fecha_fin?->toDateString(),
            'reconocida_como_practica' => $this->reconocida_como_practica,
            'observacion_final' => $this->observacion_final,
            'periodo' => $this->whenLoaded('periodo', fn () => $this->periodo?->nombre),
            'horas' => $this->when(
                array_key_exists('horas_aprobadas', $this->resource->getAttributes()) && $this->relationLoaded('estudiante'),
                fn () => [
                    'aprobadas' => (float) $this->horas_aprobadas,
                    'requeridas' => $this->estudiante->programa->horas_requeridas,
                    'cumplidas_en' => $this->horas_cumplidas_en?->toIso8601String(),
                    'semanas_por_revisar' => (int) $this->evidencias_por_revisar,
                ],
            ),
            // Los reportes pueden venir de cualquiera de las partes: solo la FET ve cuántos hay.
            'reportes' => $this->when($usuario?->tieneRol(Rol::Docente, Rol::Admin) && isset($this->reportes_count), fn () => $this->reportes_count),
            'reportes_para_finalizar' => config('fet.reportes_para_finalizar'),
            'evaluaciones' => $this->whenLoaded('evaluaciones', fn () => [
                'habilitadas' => app(EvaluacionService::class)->habilitada($this->resource),
                'respondidas' => $this->evaluaciones->map(fn ($e) => $this->enum($e->tipo))->values(),
                'mia' => $usuario && TipoEvaluacion::delRol($usuario->rol)
                    ? $this->evaluaciones->contains('tipo', TipoEvaluacion::delRol($usuario->rol))
                    : null,
            ]),
            'puede' => $this->when($usuario !== null, fn () => [
                'registrar_evidencia' => $usuario->can('registrarEvidencia', $this->resource),
                'revisar_evidencias' => $usuario->can('revisarEvidencias', $this->resource),
                'revisar_documentos' => $usuario->can('revisarDocumentos', $this->resource),
                'finalizar' => $this->estaActiva() && $usuario->can('finalizar', $this->resource),
                'evaluar' => $usuario->can('evaluar', $this->resource),
            ]),
            'estudiante' => new EstudianteResource($this->whenLoaded('estudiante')),
            'empresa' => new EmpresaResource($this->whenLoaded('empresa')),
            'vacante' => new VacanteResource($this->whenLoaded('vacante')),
            'tutor' => $this->whenLoaded('tutor', fn () => $this->tutor ? ['id' => $this->tutor->id, 'correo' => $this->tutor->correo] : null),
            'documentos' => DocumentoSeguimientoResource::collection($this->whenLoaded('documentos')),
            'documentos_pendientes' => $this->whenLoaded('documentos', fn () => $this->documentos->where('estado_revision', EstadoRevision::Pendiente)->count()),
        ];
    }
}
