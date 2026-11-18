<?php

namespace App\Models;

use App\Enums\ParteCalificacion;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Calificación de una parte (empresa o docente) en un momento de la práctica.
 * Cada puntaje guarda el texto del ítem tal como se calificó.
 */
#[Table('calificacion', timestamps: false)]
#[Fillable(['vinculacion_id', 'momento', 'parte', 'evaluador_id', 'puntajes', 'observaciones'])]
class Calificacion extends Model
{
    protected function casts(): array
    {
        return [
            'momento' => 'integer',
            'parte' => ParteCalificacion::class,
            'puntajes' => 'array',
            'creada_en' => 'datetime',
        ];
    }
}
