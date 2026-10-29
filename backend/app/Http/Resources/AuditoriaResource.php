<?php

namespace App\Http\Resources;

use App\Models\Auditoria;
use Illuminate\Http\Request;

/** @mixin Auditoria */
class AuditoriaResource extends Recurso
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'accion' => $this->accion,
            'entidad' => $this->entidad,
            'entidad_id' => $this->entidad_id,
            'detalle' => $this->detalle,
            'ip' => $this->ip,
            'creado_en' => $this->creado_en?->toIso8601String(),
            'usuario' => $this->whenLoaded('usuario', fn () => $this->usuario ? ['id' => $this->usuario->id, 'correo' => $this->usuario->correo, 'rol' => $this->enum($this->usuario->rol)] : null),
        ];
    }
}
