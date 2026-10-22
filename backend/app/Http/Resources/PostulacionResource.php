<?php

namespace App\Http\Resources;

use App\Models\Postulacion;
use Illuminate\Http\Request;

/** @mixin Postulacion */
class PostulacionResource extends Recurso
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'estado' => $this->enum($this->estado),
            'fecha' => $this->fecha?->toIso8601String(),
            'observacion' => $this->observacion,
            'vacante' => new VacanteResource($this->whenLoaded('vacante')),
            'estudiante' => new EstudianteResource($this->whenLoaded('estudiante')),
        ];
    }
}
