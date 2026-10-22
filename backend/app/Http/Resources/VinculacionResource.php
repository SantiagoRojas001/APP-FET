<?php

namespace App\Http\Resources;

use App\Enums\EstadoRevision;
use App\Models\Vinculacion;
use Illuminate\Http\Request;

/** @mixin Vinculacion */
class VinculacionResource extends Recurso
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipo' => $this->enum($this->tipo),
            'estado' => $this->enum($this->estado),
            'semestre_al_vincular' => $this->semestre_al_vincular,
            'fecha_inicio' => $this->fecha_inicio->toDateString(),
            'fecha_fin' => $this->fecha_fin?->toDateString(),
            'reconocida_como_practica' => $this->reconocida_como_practica,
            'observacion_final' => $this->observacion_final,
            'estudiante' => new EstudianteResource($this->whenLoaded('estudiante')),
            'empresa' => new EmpresaResource($this->whenLoaded('empresa')),
            'vacante' => new VacanteResource($this->whenLoaded('vacante')),
            'tutor' => $this->whenLoaded('tutor', fn () => $this->tutor ? ['id' => $this->tutor->id, 'correo' => $this->tutor->correo] : null),
            'documentos' => DocumentoSeguimientoResource::collection($this->whenLoaded('documentos')),
            'documentos_pendientes' => $this->whenLoaded('documentos', fn () => $this->documentos->where('estado_revision', EstadoRevision::Pendiente)->count()),
        ];
    }
}
