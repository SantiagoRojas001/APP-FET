<?php

namespace App\Http\Resources;

use App\Models\PeriodoAcademico;
use Illuminate\Http\Request;

/** @mixin PeriodoAcademico */
class PeriodoResource extends Recurso
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'fecha_inicio' => $this->fecha_inicio->toDateString(),
            'fecha_fin' => $this->fecha_fin->toDateString(),
            'cerrado_en' => $this->cerrado_en?->toIso8601String(),
            'vinculaciones' => $this->whenCounted('vinculaciones'),
        ];
    }
}
