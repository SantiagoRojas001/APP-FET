<?php

namespace App\Http\Resources;

use App\Models\DocumentoSeguimiento;
use Illuminate\Http\Request;

/** @mixin DocumentoSeguimiento */
class DocumentoSeguimientoResource extends Recurso
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vinculacion_id' => $this->vinculacion_id,
            'tipo' => $this->enum($this->tipo),
            'periodo_inicio' => $this->periodo_inicio->toDateString(),
            'periodo_fin' => $this->periodo_fin->toDateString(),
            'cargado_en' => $this->cargado_en?->toIso8601String(),
            'estado_revision' => $this->enum($this->estado_revision),
            'observaciones' => $this->observaciones,
            // Nunca se expone la ruta interna del archivo: se descarga por la API.
            'url_descarga' => "/api/documentos/{$this->id}/archivo",
        ];
    }
}
