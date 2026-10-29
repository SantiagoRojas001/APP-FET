<?php

namespace App\Models;

use App\Enums\NivelFormacion;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Título académico de la hoja de vida (maestría, especialización, pregrado, tecnólogo o técnico).
 */
#[Table('formacion_academica', timestamps: false)]
#[Fillable(['hoja_vida_id', 'nivel', 'titulo', 'institucion', 'pais_ciudad', 'fecha_grado', 'modalidad'])]
class FormacionAcademica extends Model
{
    protected function casts(): array
    {
        return [
            'nivel' => NivelFormacion::class,
            'fecha_grado' => 'date',
        ];
    }

    /** @return BelongsTo<HojaVida, $this> */
    public function hojaVida(): BelongsTo
    {
        return $this->belongsTo(HojaVida::class);
    }
}
