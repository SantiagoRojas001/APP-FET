<?php

namespace App\Http\Resources;

use App\Models\Empresa;
use Illuminate\Http\Request;

/** @mixin Empresa */
class EmpresaResource extends Recurso
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'usuario_id' => $this->usuario_id,
            'nit' => $this->nit,
            'razon_social' => $this->razon_social,
            'sector' => $this->sector,
            'contacto_nombre' => $this->contacto_nombre,
            'contacto_telefono' => $this->contacto_telefono,
            'modalidad_interes' => $this->enum($this->modalidad_interes),
            'estado_validacion' => $this->enum($this->estado_validacion),
            'validada_en' => $this->validada_en?->toIso8601String(),
            'correo' => $this->whenLoaded('usuario', fn () => $this->usuario->correo),
            // Sin política aceptada, la empresa aún no usa su invitación.
            'pendiente_activacion' => $this->whenLoaded('usuario', fn () => isset($this->usuario->consentimientos_count) ? $this->usuario->consentimientos_count === 0 : null),
        ];
    }
}
