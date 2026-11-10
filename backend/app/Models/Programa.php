<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Programa académico de la FET. Se cursa por ciclos (ver App\Enums\Ciclo).
 */
#[Table('programa', timestamps: false)]
#[Fillable(['nombre', 'total_semestres'])]
class Programa extends Model
{
    use HasFactory;

    /** @return HasMany<Estudiante, $this> */
    public function estudiantes(): HasMany
    {
        return $this->hasMany(Estudiante::class);
    }
}
