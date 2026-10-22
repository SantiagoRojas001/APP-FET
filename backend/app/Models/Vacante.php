<?php

namespace App\Models;

use App\Enums\EstadoVacante;
use App\Enums\EstadoValidacion;
use App\Enums\Modalidad;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Oferta de pasantía o práctica profesional.
 */
#[Table('vacante', timestamps: false)]
#[Fillable(['empresa_id', 'titulo', 'descripcion', 'modalidad', 'estado', 'publicada_en'])]
class Vacante extends Model
{
    use HasFactory;

    protected $attributes = [
        'estado' => 'ABIERTA',
    ];

    protected function casts(): array
    {
        return [
            'modalidad' => Modalidad::class,
            'estado' => EstadoVacante::class,
            'publicada_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Empresa, $this> */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /** @return HasMany<Postulacion, $this> */
    public function postulaciones(): HasMany
    {
        return $this->hasMany(Postulacion::class);
    }

    /**
     * Vacantes abiertas de empresas validadas: las que ve un estudiante.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function publicadas(Builder $query): void
    {
        $query->where('estado', EstadoVacante::Abierta)
            ->whereHas('empresa', fn (Builder $q) => $q->where('estado_validacion', EstadoValidacion::Validada));
    }
}
