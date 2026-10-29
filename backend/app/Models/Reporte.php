<?php

namespace App\Models;

use App\Enums\CategoriaReporte;
use App\Enums\EstadoReporte;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Problema reportado por un usuario. La administración lo atiende y responde.
 */
#[Table('reporte', timestamps: false)]
#[Fillable([
    'autor_id', 'vinculacion_id', 'categoria', 'asunto', 'descripcion',
    'estado', 'respuesta', 'atendido_por', 'atendido_en',
])]
class Reporte extends Model
{
    protected $attributes = [
        'estado' => 'ABIERTO',
    ];

    protected function casts(): array
    {
        return [
            'categoria' => CategoriaReporte::class,
            'estado' => EstadoReporte::class,
            'creado_en' => 'datetime',
            'atendido_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Usuario, $this> */
    public function autor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'autor_id');
    }

    /** @return BelongsTo<Vinculacion, $this> */
    public function vinculacion(): BelongsTo
    {
        return $this->belongsTo(Vinculacion::class);
    }

    /** @return BelongsTo<Usuario, $this> */
    public function atendidoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'atendido_por');
    }
}
