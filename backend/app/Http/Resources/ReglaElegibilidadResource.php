<?php

namespace App\Http\Resources;

use App\Models\ReglaElegibilidad;
use Illuminate\Http\Request;

/** @mixin ReglaElegibilidad */
class ReglaElegibilidadResource extends Recurso
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'programa' => new ProgramaResource($this->whenLoaded('programa')),
            'semestre_minimo' => $this->semestre_minimo,
            'vigente_desde' => $this->vigente_desde->toDateString(),
            'vigente_hasta' => $this->vigente_hasta?->toDateString(),
        ];
    }
}
