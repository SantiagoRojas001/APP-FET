<?php

namespace App\Models;

use App\Enums\TipoInvestigacion;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Grupo, proyecto, tutoría, publicación u otra actividad investigativa de la hoja de vida.
 */
#[Table('actividad_investigativa', timestamps: false)]
#[Fillable(['hoja_vida_id', 'tipo', 'descripcion', 'periodo'])]
class ActividadInvestigativa extends Model
{
    protected function casts(): array
    {
        return [
            'tipo' => TipoInvestigacion::class,
        ];
    }

    /** @return BelongsTo<HojaVida, $this> */
    public function hojaVida(): BelongsTo
    {
        return $this->belongsTo(HojaVida::class);
    }
}
