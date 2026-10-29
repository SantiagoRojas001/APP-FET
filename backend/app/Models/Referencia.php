<?php

namespace App\Models;

use App\Enums\TipoReferencia;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Referencia familiar o personal de la hoja de vida.
 */
#[Table('referencia', timestamps: false)]
#[Fillable(['hoja_vida_id', 'tipo', 'nombre', 'ocupacion', 'parentesco', 'telefono'])]
class Referencia extends Model
{
    protected function casts(): array
    {
        return [
            'tipo' => TipoReferencia::class,
        ];
    }

    /** @return BelongsTo<HojaVida, $this> */
    public function hojaVida(): BelongsTo
    {
        return $this->belongsTo(HojaVida::class);
    }
}
