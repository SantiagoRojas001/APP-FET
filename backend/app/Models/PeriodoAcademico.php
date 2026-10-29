<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Periodo académico (por ejemplo, 2026-2). Al cerrarse ya no recibe
 * vinculaciones nuevas.
 */
#[Table('periodo_academico', timestamps: false)]
#[Fillable(['nombre', 'fecha_inicio', 'fecha_fin', 'cerrado_en'])]
class PeriodoAcademico extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'cerrado_en' => 'datetime',
        ];
    }

    public function estaCerrado(): bool
    {
        return $this->cerrado_en !== null;
    }

    /** Periodo abierto que contiene la fecha, si existe. */
    public static function abiertoEn(Carbon $fecha): ?self
    {
        return self::query()
            ->whereNull('cerrado_en')
            ->whereDate('fecha_inicio', '<=', $fecha)
            ->whereDate('fecha_fin', '>=', $fecha)
            ->first();
    }

    /** @return HasMany<Vinculacion, $this> */
    public function vinculaciones(): HasMany
    {
        return $this->hasMany(Vinculacion::class, 'periodo_id');
    }
}
