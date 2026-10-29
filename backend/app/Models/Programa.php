<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Programa académico de la FET.
 */
#[Table('programa', timestamps: false)]
#[Fillable(['nombre', 'total_semestres', 'horas_requeridas'])]
class Programa extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['horas_requeridas' => 'integer'];
    }

    /** @return HasMany<ReglaElegibilidad, $this> */
    public function reglas(): HasMany
    {
        return $this->hasMany(ReglaElegibilidad::class);
    }

    /** @return HasMany<Estudiante, $this> */
    public function estudiantes(): HasMany
    {
        return $this->hasMany(Estudiante::class);
    }
}
