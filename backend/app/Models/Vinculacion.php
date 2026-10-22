<?php

namespace App\Models;

use App\Enums\EstadoVinculacion;
use App\Enums\Modalidad;
use App\Policies\VinculacionPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pasantía o práctica en curso entre un estudiante y una empresa.
 */
#[Table('vinculacion', timestamps: false)]
#[Fillable([
    'estudiante_id', 'empresa_id', 'vacante_id', 'tutor_id', 'tipo', 'semestre_al_vincular',
    'fecha_inicio', 'fecha_fin', 'estado', 'reconocida_como_practica', 'observacion_final',
])]
#[UsePolicy(VinculacionPolicy::class)]
class Vinculacion extends Model
{
    use HasFactory;

    protected $attributes = [
        'estado' => 'ACTIVA',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => Modalidad::class,
            'estado' => EstadoVinculacion::class,
            'semestre_al_vincular' => 'integer',
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'reconocida_como_practica' => 'boolean',
        ];
    }

    public function estaActiva(): bool
    {
        return $this->estado === EstadoVinculacion::Activa;
    }

    /** @return BelongsTo<Estudiante, $this> */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    /** @return BelongsTo<Empresa, $this> */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /** @return BelongsTo<Vacante, $this> */
    public function vacante(): BelongsTo
    {
        return $this->belongsTo(Vacante::class);
    }

    /** @return BelongsTo<Usuario, $this> */
    public function tutor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'tutor_id');
    }

    /** @return HasMany<DocumentoSeguimiento, $this> */
    public function documentos(): HasMany
    {
        return $this->hasMany(DocumentoSeguimiento::class)->orderByDesc('cargado_en');
    }
}
