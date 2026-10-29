<?php

namespace App\Http\Resources;

use App\Models\Acuerdo;
use Illuminate\Http\Request;

/** @mixin Acuerdo */
class AcuerdoResource extends Recurso
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'estado' => $this->enum($this->estado),
            'vigente' => $this->vigente(),
            'vigencia_inicio' => $this->vigencia_inicio->toDateString(),
            'vigencia_fin' => $this->vigencia_fin->toDateString(),
            'observacion' => $this->observacion,
            'cargado_en' => $this->cargado_en?->toIso8601String(),
            'revisado_en' => $this->revisado_en?->toIso8601String(),
            // La ruta interna nunca se expone: el PDF se descarga por la API.
            'url_archivo' => "/api/acuerdos/{$this->id}/archivo",
            'empresa' => new EmpresaResource($this->whenLoaded('empresa')),
        ];
    }
}
