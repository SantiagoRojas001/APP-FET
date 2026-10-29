<?php

namespace App\Models;

use App\Enums\EstadoCita;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cita que una empresa asigna a un estudiante con hoja de vida aprobada por
 * el docente. Registra también lo que sigue: la decisión de la empresa
 * (con la fecha de inicio si lo acepta) y la carta de presentación del
 * director de programa.
 */
#[Table('cita', timestamps: false)]
#[Fillable([
    'empresa_id', 'estudiante_id', 'vacante_id', 'fecha_hora', 'direccion', 'indicaciones', 'estado', 'observacion',
    'fecha_inicio_practica', 'decidida_en', 'presentada_por', 'presentada_en', 'vinculacion_id',
])]
class Cita extends Model
{
    use HasFactory;

    protected $attributes = [
        'estado' => 'PROGRAMADA',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoCita::class,
            'fecha_hora' => 'datetime',
            'fecha_inicio_practica' => 'date',
            'creada_en' => 'datetime',
            'decidida_en' => 'datetime',
            'presentada_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Empresa, $this> */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /** @return BelongsTo<Estudiante, $this> */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    /** @return BelongsTo<Vacante, $this> */
    public function vacante(): BelongsTo
    {
        return $this->belongsTo(Vacante::class);
    }

    /** @return BelongsTo<Usuario, $this> */
    public function director(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'presentada_por');
    }

    /** @return BelongsTo<Vinculacion, $this> */
    public function vinculacion(): BelongsTo
    {
        return $this->belongsTo(Vinculacion::class);
    }
}
