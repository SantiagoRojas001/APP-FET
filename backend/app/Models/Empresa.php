<?php

namespace App\Models;

use App\Enums\EstadoAcuerdo;
use App\Enums\EstadoValidacion;
use App\Enums\Modalidad;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Empresa registrada y su estado de validación por la FET.
 */
#[Table('empresa', timestamps: false)]
#[Fillable([
    'usuario_id', 'nit', 'razon_social', 'sector', 'contacto_nombre',
    'contacto_telefono', 'modalidad_interes', 'estado_validacion', 'validada_por', 'validada_en',
])]
class Empresa extends Model
{
    use HasFactory;

    protected $attributes = [
        'estado_validacion' => 'PENDIENTE',
    ];

    protected function casts(): array
    {
        return [
            'modalidad_interes' => Modalidad::class,
            'estado_validacion' => EstadoValidacion::class,
            'validada_en' => 'datetime',
        ];
    }

    public function estaValidada(): bool
    {
        return $this->estado_validacion === EstadoValidacion::Validada;
    }

    /** @return BelongsTo<Usuario, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }

    /** @return BelongsTo<Usuario, $this> */
    public function validador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'validada_por');
    }

    /** @return HasMany<Vacante, $this> */
    public function vacantes(): HasMany
    {
        return $this->hasMany(Vacante::class);
    }

    /** @return HasMany<Acuerdo, $this> */
    public function acuerdos(): HasMany
    {
        return $this->hasMany(Acuerdo::class)->orderByDesc('cargado_en');
    }

    /** Solo con un acuerdo aprobado y vigente la empresa puede asignar citas. */
    public function tieneAcuerdoVigente(): bool
    {
        return $this->acuerdos()
            ->where('estado', EstadoAcuerdo::Aprobado)
            ->whereDate('vigencia_inicio', '<=', today())
            ->whereDate('vigencia_fin', '>=', today())
            ->exists();
    }

    /** @return HasMany<Cita, $this> */
    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class);
    }

    /** @return HasMany<Vinculacion, $this> */
    public function vinculaciones(): HasMany
    {
        return $this->hasMany(Vinculacion::class);
    }
}
