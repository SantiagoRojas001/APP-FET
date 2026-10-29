<?php

namespace App\Http\Resources;

use App\Models\Vacante;
use Illuminate\Http\Request;

/** @mixin Vacante */
class VacanteResource extends Recurso
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'titulo' => $this->titulo,
            'descripcion' => $this->descripcion,
            'modalidad' => $this->enum($this->modalidad),
            'estado' => $this->enum($this->estado),
            'publicada_en' => $this->publicada_en?->toIso8601String(),
            'empresa' => new EmpresaResource($this->whenLoaded('empresa')),
            'recomendaciones' => $this->whenLoaded('recomendaciones', fn () => $this->recomendaciones->map(fn ($r) => [
                'id' => $r->id,
                'comentario' => $r->comentario,
                'creada_en' => $r->creada_en?->toIso8601String(),
                'estudiante' => $r->relationLoaded('estudiante') ? new EstudianteResource($r->estudiante) : null,
                'docente' => $r->relationLoaded('docente') ? $r->docente?->correo : null,
            ])->values()),
            'recomendaciones_count' => $this->whenCounted('recomendaciones'),
        ];
    }
}
