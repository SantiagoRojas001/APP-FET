<?php

namespace App\Models;

use App\Enums\TipoEvaluacion;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cuestionario de cierre respondido. `respuestas` guarda, por cada pregunta,
 * su texto y la respuesta dada, para que el registro no cambie si el
 * cuestionario se modifica después.
 */
#[Table('evaluacion', timestamps: false)]
#[Fillable(['vinculacion_id', 'tipo', 'evaluador_id', 'respuestas'])]
class Evaluacion extends Model
{
    protected function casts(): array
    {
        return [
            'tipo' => TipoEvaluacion::class,
            'respuestas' => 'array',
            'creada_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Vinculacion, $this> */
    public function vinculacion(): BelongsTo
    {
        return $this->belongsTo(Vinculacion::class);
    }

    /** @return BelongsTo<Usuario, $this> */
    public function evaluador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'evaluador_id');
    }
}
