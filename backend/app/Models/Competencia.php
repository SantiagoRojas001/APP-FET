<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Competencia del catálogo institucional.
 */
#[Table('competencia', timestamps: false)]
#[Fillable(['nombre'])]
class Competencia extends Model
{
    use HasFactory;
}
