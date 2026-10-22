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
            'postulaciones_por_estado' => $this->whenLoaded('postulaciones', fn () => $this->postulaciones->countBy(fn ($p) => $p->estado->value)),
            // Calculados por el controlador para el estudiante que consulta.
            'puede_postularse' => $this->whenHas('puede_postularse'),
            'motivo_no_disponible' => $this->whenHas('motivo_no_disponible'),
            'mi_postulacion' => $this->whenHas('mi_postulacion'),
        ];
    }
}
