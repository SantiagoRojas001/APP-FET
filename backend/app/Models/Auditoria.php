<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fila de la bitácora de auditoría. Se crea con App\Services\Auditoria.
 */
#[Table('auditoria', timestamps: false)]
#[Fillable(['usuario_id', 'accion', 'entidad', 'entidad_id', 'detalle', 'ip'])]
class Auditoria extends Model
{
    protected function casts(): array
    {
        return [
            'detalle' => 'array',
            'creado_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Usuario, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }
}
