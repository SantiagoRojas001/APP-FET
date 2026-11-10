<?php

namespace App\Models;

use App\Enums\EstadoAcuerdo;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Acuerdo (convenio) que una empresa sube en PDF con su vigencia. La
 * administración lo aprueba o lo rechaza; el director de programa lo consulta.
 */
#[Table('acuerdo', timestamps: false)]
#[Fillable(['empresa_id', 'archivo_url', 'vigencia_inicio', 'vigencia_fin', 'estado', 'observacion', 'revisado_por', 'revisado_en'])]
class Acuerdo extends Model
{
    protected $attributes = [
        'estado' => 'PENDIENTE',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoAcuerdo::class,
            'vigencia_inicio' => 'date',
            'vigencia_fin' => 'date',
            'cargado_en' => 'datetime',
            'revisado_en' => 'datetime',
        ];
    }

    public function vigente(): bool
    {
        return $this->estado === EstadoAcuerdo::Aprobado
            && $this->vigencia_inicio->lte(today())
            && $this->vigencia_fin->gte(today());
    }

    /** @param  Builder<self>  $query */
    #[Scope]
    protected function vigentes(Builder $query): void
    {
        $query->where('estado', EstadoAcuerdo::Aprobado)
            ->whereDate('vigencia_inicio', '<=', today())
            ->whereDate('vigencia_fin', '>=', today());
    }

    /** @return BelongsTo<Empresa, $this> */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /** @return BelongsTo<Usuario, $this> */
    public function revisor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'revisado_por');
    }
}
