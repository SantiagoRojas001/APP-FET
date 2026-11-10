<?php

namespace App\Http\Resources;

use App\Models\SolicitudConvenio;
use Illuminate\Http\Request;

/** @mixin SolicitudConvenio */
class SolicitudConvenioResource extends Recurso
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nit' => $this->nit,
            'razon_social' => $this->razon_social,
            'sector' => $this->sector,
            'correo' => $this->correo,
            'contacto_nombre' => $this->contacto_nombre,
            'contacto_telefono' => $this->contacto_telefono,
            'modalidad_interes' => $this->enum($this->modalidad_interes),
            'representante_nombre' => $this->representante_nombre,
            'representante_documento' => $this->representante_documento,
            'estado' => $this->enum($this->estado),
            'observacion' => $this->observacion,
            'creado_en' => $this->creado_en?->toIso8601String(),
            'atendida_en' => $this->atendida_en?->toIso8601String(),
            'url_firma' => "/api/admin/solicitudes-convenio/{$this->id}/firma",
            'url_convenio' => $this->convenio_url ? "/api/admin/solicitudes-convenio/{$this->id}/convenio" : null,
        ];
    }
}
