<?php

namespace App\Http\Resources;

use App\Models\Programa;
use Illuminate\Http\Request;

/** @mixin Programa */
class ProgramaResource extends Recurso
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'total_semestres' => $this->total_semestres,
        ];
    }
}
