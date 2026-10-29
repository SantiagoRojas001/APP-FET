<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Diplomado, curso, seminario o actualización registrado en la hoja de vida.
 */
#[Table('curso', timestamps: false)]
#[Fillable(['hoja_vida_id', 'tema', 'duracion_horas', 'fecha', 'institucion', 'modalidad'])]
class Curso extends Model
{
    protected function casts(): array
    {
        return [
            'duracion_horas' => 'integer',
            'fecha' => 'date',
        ];
    }

    /** @return BelongsTo<HojaVida, $this> */
    public function hojaVida(): BelongsTo
    {
        return $this->belongsTo(HojaVida::class);
    }
}
