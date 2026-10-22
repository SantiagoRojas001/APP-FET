<?php

namespace App\Models;

use App\Enums\EstadoRevision;
use App\Enums\TipoDocumento;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Control de asistencia, diario de campo o informe de una vinculación.
 *
 * `archivo_url` guarda la ruta del archivo en el disco privado; se descarga
 * solo a través de la API, que verifica permisos.
 */
#[Table('documento_seguimiento', timestamps: false)]
#[Fillable([
    'vinculacion_id', 'tipo', 'periodo_inicio', 'periodo_fin', 'archivo_url',
    'cargado_por', 'cargado_en', 'estado_revision', 'observaciones',
])]
class DocumentoSeguimiento extends Model
{
    protected $attributes = [
        'estado_revision' => 'PENDIENTE',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoDocumento::class,
            'estado_revision' => EstadoRevision::class,
            'periodo_inicio' => 'date',
            'periodo_fin' => 'date',
            'cargado_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Vinculacion, $this> */
    public function vinculacion(): BelongsTo
    {
        return $this->belongsTo(Vinculacion::class);
    }

    /** @return BelongsTo<Usuario, $this> */
    public function autor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'cargado_por');
    }
}
