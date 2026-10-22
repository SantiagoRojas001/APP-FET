<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Hoja de vida del estudiante en el formato institucional de la FET.
 */
#[Table('hoja_vida', timestamps: false)]
#[Fillable(['estudiante_id', 'perfil_profesional', 'formacion', 'actualizado_en'])]
class HojaVida extends Model
{
    protected function casts(): array
    {
        return ['actualizado_en' => 'datetime'];
    }

    /** @return BelongsTo<Estudiante, $this> */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    /** @return HasMany<Experiencia, $this> */
    public function experiencias(): HasMany
    {
        return $this->hasMany(Experiencia::class)->orderByDesc('fecha_inicio');
    }
}
