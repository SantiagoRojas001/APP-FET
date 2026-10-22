<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Semestre mínimo desde el cual un programa autoriza la práctica, con su
 * periodo de vigencia.
 */
#[Table('regla_elegibilidad', timestamps: false)]
#[Fillable(['programa_id', 'semestre_minimo', 'vigente_desde', 'vigente_hasta'])]
class ReglaElegibilidad extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'semestre_minimo' => 'integer',
            'vigente_desde' => 'date',
            'vigente_hasta' => 'date',
        ];
    }

    /** @return BelongsTo<Programa, $this> */
    public function programa(): BelongsTo
    {
        return $this->belongsTo(Programa::class);
    }

    /**
     * Reglas vigentes en la fecha indicada (hoy por defecto).
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function vigentes(Builder $query, ?Carbon $fecha = null): void
    {
        $fecha = ($fecha ?? now())->toDateString();

        $query->where('vigente_desde', '<=', $fecha)
            ->where(fn (Builder $q) => $q->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>=', $fecha));
    }
}
