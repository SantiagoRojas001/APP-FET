<?php

namespace App\Http\Resources;

use App\Models\Evaluacion;
use Illuminate\Http\Request;

/** @mixin Evaluacion */
class EvaluacionResource extends Recurso
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipo' => $this->enum($this->tipo),
            'respuestas' => $this->respuestas,
            'creada_en' => $this->creada_en?->toIso8601String(),
        ];
    }
}
