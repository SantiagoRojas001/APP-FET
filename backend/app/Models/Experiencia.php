<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Experiencia laboral registrada en una hoja de vida. La descripción son las funciones del cargo.
 */
#[Table('experiencia', timestamps: false)]
#[Fillable(['hoja_vida_id', 'cargo', 'organizacion', 'fecha_inicio', 'fecha_fin', 'descripcion', 'direccion', 'telefono', 'jefe_inmediato', 'dedicacion'])]
class Experiencia extends Model
{
    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
        ];
    }

    /** @return BelongsTo<HojaVida, $this> */
    public function hojaVida(): BelongsTo
    {
        return $this->belongsTo(HojaVida::class);
    }
}
