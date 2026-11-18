<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Archivo que un docente comparte con los estudiantes que asesora.
 */
#[Table('material_docente', timestamps: false)]
#[Fillable(['docente_id', 'titulo', 'archivo_url'])]
class MaterialDocente extends Model
{
    protected function casts(): array
    {
        return ['creado_en' => 'datetime'];
    }
}
