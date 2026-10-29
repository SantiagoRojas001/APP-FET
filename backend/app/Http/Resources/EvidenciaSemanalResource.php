<?php

namespace App\Http\Resources;

use App\Models\EvidenciaSemanal;
use Illuminate\Http\Request;

/** @mixin EvidenciaSemanal */
class EvidenciaSemanalResource extends Recurso
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'semana_inicio' => $this->semana_inicio->toDateString(),
            'semana_fin' => $this->semana_inicio->copy()->addDays(6)->toDateString(),
            'horas' => $this->horas,
            'actividades' => $this->actividades,
            'estado' => $this->enum($this->estado),
            'comentario' => $this->comentario,
            'revisada_en' => $this->revisada_en?->toIso8601String(),
            'registrado_en' => $this->registrado_en?->toIso8601String(),
            // La ruta interna nunca se expone: el archivo se descarga por la API.
            'url_archivo' => $this->archivo_url ? "/api/evidencias/{$this->id}/archivo" : null,
        ];
    }
}
