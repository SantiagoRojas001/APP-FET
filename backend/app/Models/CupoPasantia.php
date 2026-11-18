<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Cupos de pasantía de un programa en un periodo, por ciclo.
 */
#[Table('cupo_pasantia', timestamps: false)]
#[Fillable(['programa_id', 'periodo', 'tecnico', 'tecnologo'])]
class CupoPasantia extends Model
{
    protected $attributes = [
        'tecnico' => 5,
        'tecnologo' => 5,
    ];
}
