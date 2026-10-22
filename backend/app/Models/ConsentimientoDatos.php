<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aceptación de la política de tratamiento de datos (Ley 1581 de 2012).
 */
#[Table('consentimiento_datos', timestamps: false)]
#[Fillable(['usuario_id', 'version_politica', 'aceptado_en'])]
class ConsentimientoDatos extends Model
{
    protected function casts(): array
    {
        return ['aceptado_en' => 'datetime'];
    }

    /** @return BelongsTo<Usuario, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }
}
