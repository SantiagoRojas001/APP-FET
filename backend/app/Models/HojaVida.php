<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Hoja de vida del estudiante en el formato institucional de la FET
 * (PA-GTH-F-10): datos personales, formación, experiencia laboral,
 * experiencia investigativa y referencias.
 */
#[Table('hoja_vida', timestamps: false)]
#[Fillable([
    'estudiante_id', 'perfil_profesional', 'actualizado_en', 'foto_url', 'fecha_nacimiento', 'lugar_nacimiento',
    'estado_civil', 'lugar_residencia', 'direccion', 'rh', 'contacto_emergencia_nombre', 'contacto_emergencia_telefono',
])]
class HojaVida extends Model
{
    /** Relaciones que componen la hoja de vida completa. */
    public const COMPLETA = ['experiencias', 'formaciones', 'cursos', 'investigaciones', 'referencias'];

    protected function casts(): array
    {
        return [
            'actualizado_en' => 'datetime',
            'fecha_nacimiento' => 'date',
        ];
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

    /** @return HasMany<FormacionAcademica, $this> */
    public function formaciones(): HasMany
    {
        return $this->hasMany(FormacionAcademica::class)->orderByRaw('fecha_grado desc nulls first');
    }

    /** @return HasMany<Curso, $this> */
    public function cursos(): HasMany
    {
        return $this->hasMany(Curso::class)->orderByDesc('fecha');
    }

    /** @return HasMany<ActividadInvestigativa, $this> */
    public function investigaciones(): HasMany
    {
        return $this->hasMany(ActividadInvestigativa::class)->orderBy('id');
    }

    /** @return HasMany<Referencia, $this> */
    public function referencias(): HasMany
    {
        return $this->hasMany(Referencia::class)->orderBy('id');
    }
}
