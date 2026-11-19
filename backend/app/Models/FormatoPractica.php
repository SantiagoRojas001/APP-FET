<?php

namespace App\Models;

use App\Enums\TipoFormato;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Formato institucional de una práctica: lo diligenciado y quién lo firmó.
 */
#[Table('formato_practica', timestamps: false)]
#[Fillable(['vinculacion_id', 'tipo', 'datos', 'firmas', 'actualizado_en'])]
class FormatoPractica extends Model
{
    protected $attributes = [
        'datos' => '{}',
        'firmas' => '{}',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoFormato::class,
            'datos' => 'array',
            'firmas' => 'array',
            'actualizado_en' => 'datetime',
        ];
    }
}
