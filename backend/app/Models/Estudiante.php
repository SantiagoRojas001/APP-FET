<?php

namespace App\Models;

use App\Enums\EstadoVinculacion;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Datos personales y académicos del estudiante o egresado.
 */
#[Table('estudiante', timestamps: false)]
#[Fillable([
    'usuario_id', 'programa_id', 'codigo', 'documento', 'nombres', 'apellidos',
    'telefono', 'semestre_actual', 'es_egresado', 'disponibilidad',
])]
class Estudiante extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'semestre_actual' => 'integer',
            'es_egresado' => 'boolean',
        ];
    }

    /** @return Attribute<string, never> */
    protected function nombreCompleto(): Attribute
    {
        return Attribute::get(fn () => "{$this->nombres} {$this->apellidos}");
    }

    /** @return BelongsTo<Usuario, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }

    /** @return BelongsTo<Programa, $this> */
    public function programa(): BelongsTo
    {
        return $this->belongsTo(Programa::class);
    }

    /** @return HasOne<HojaVida, $this> */
    public function hojaVida(): HasOne
    {
        return $this->hasOne(HojaVida::class);
    }

    /** @return BelongsToMany<Competencia, $this> */
    public function competencias(): BelongsToMany
    {
        return $this->belongsToMany(Competencia::class, 'estudiante_competencia')->withPivot('nivel');
    }

    /** @return HasMany<Proyecto, $this> */
    public function proyectos(): HasMany
    {
        return $this->hasMany(Proyecto::class);
    }

    /** @return HasMany<Postulacion, $this> */
    public function postulaciones(): HasMany
    {
        return $this->hasMany(Postulacion::class);
    }

    /** @return HasMany<Vinculacion, $this> */
    public function vinculaciones(): HasMany
    {
        return $this->hasMany(Vinculacion::class);
    }

    /** @return HasOne<Vinculacion, $this> */
    public function vinculacionActiva(): HasOne
    {
        return $this->hasOne(Vinculacion::class)
            ->where('estado', EstadoVinculacion::Activa)
            ->latestOfMany('fecha_inicio');
    }
}
