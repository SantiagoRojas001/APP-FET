<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/** @mixin DatabaseNotification */
class NotificacionResource extends Recurso
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'titulo' => $this->data['titulo'],
            'mensaje' => $this->data['mensaje'],
            'enlace' => $this->data['enlace'] ?? null,
            'leida' => $this->read_at !== null,
            'creada_en' => $this->created_at?->toIso8601String(),
        ];
    }
}
