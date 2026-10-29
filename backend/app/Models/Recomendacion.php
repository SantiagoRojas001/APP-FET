<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Estudiante que un docente recomienda para una vacante: la empresa lo ve
 * primero entre las hojas de vida publicadas.
 */
#[Table('recomendacion', timestamps: false)]
#[Fillable(['vacante_id', 'estudiante_id', 'docente_id', 'comentario'])]
class Recomendacion extends Model
{
    protected function casts(): array
    {
        return ['creada_en' => 'datetime'];
    }

    /** @return BelongsTo<Vacante, $this> */
    public function vacante(): BelongsTo
    {
        return $this->belongsTo(Vacante::class);
    }

    /** @return BelongsTo<Estudiante, $this> */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    /** @return BelongsTo<Usuario, $this> */
    public function docente(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'docente_id');
    }
}
