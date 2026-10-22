<?php

namespace App\Models;

use App\Enums\EstadoPostulacion;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Postulación de un estudiante a una vacante.
 */
#[Table('postulacion', timestamps: false)]
#[Fillable(['vacante_id', 'estudiante_id', 'estado', 'fecha', 'observacion'])]
class Postulacion extends Model
{
    use HasFactory;

    protected $attributes = [
        'estado' => 'ENVIADA',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoPostulacion::class,
            'fecha' => 'datetime',
        ];
    }

    /** @return BelongsTo<Vacante, $this> */
    public function vacante(): BelongsTo
    {
        return $this->belongsTo(Vacante::class);
    }

    /** @return BelongsTo<Estudiante, $this> */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }
}
