<?php

namespace App\Models;

use App\Enums\EstadoRevision;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Horas de una semana de práctica con lo que hizo el estudiante. Solo cuentan
 * cuando la empresa las aprueba; si las devuelve con un comentario, el
 * estudiante las corrige.
 */
#[Table('evidencia_semanal', timestamps: false)]
#[Fillable(['vinculacion_id', 'semana_inicio', 'horas', 'actividades', 'archivo_url', 'estado', 'comentario', 'revisada_por', 'revisada_en'])]
class EvidenciaSemanal extends Model
{
    protected $attributes = [
        'estado' => 'PENDIENTE',
    ];

    protected function casts(): array
    {
        return [
            'semana_inicio' => 'date',
            'horas' => 'float',
            'estado' => EstadoRevision::class,
            'revisada_en' => 'datetime',
            'registrado_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Vinculacion, $this> */
    public function vinculacion(): BelongsTo
    {
        return $this->belongsTo(Vinculacion::class);
    }
}
