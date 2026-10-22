<?php

namespace App\Models;

use App\Policies\ProyectoPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Proyecto académico o personal del estudiante, con su repositorio.
 */
#[Table('proyecto', timestamps: false)]
#[Fillable(['estudiante_id', 'titulo', 'descripcion', 'url_repositorio', 'semestre'])]
#[UsePolicy(ProyectoPolicy::class)]
class Proyecto extends Model
{
    protected function casts(): array
    {
        return ['semestre' => 'integer'];
    }

    /** @return BelongsTo<Estudiante, $this> */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }
}
