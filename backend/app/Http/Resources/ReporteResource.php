<?php

namespace App\Http\Resources;

use App\Models\Reporte;
use Illuminate\Http\Request;

/** @mixin Reporte */
class ReporteResource extends Recurso
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'categoria' => $this->enum($this->categoria),
            'asunto' => $this->asunto,
            'descripcion' => $this->descripcion,
            'estado' => $this->enum($this->estado),
            'respuesta' => $this->respuesta,
            'creado_en' => $this->creado_en?->toIso8601String(),
            'atendido_en' => $this->atendido_en?->toIso8601String(),
            'autor' => $this->whenLoaded('autor', fn () => ['id' => $this->autor->id, 'correo' => $this->autor->correo, 'rol' => $this->enum($this->autor->rol)]),
            'vinculacion' => $this->whenLoaded('vinculacion', fn () => $this->vinculacion ? [
                'id' => $this->vinculacion->id,
                'estudiante' => $this->vinculacion->estudiante->nombre_completo,
                'empresa' => $this->vinculacion->empresa->razon_social,
            ] : null),
        ];
    }
}
