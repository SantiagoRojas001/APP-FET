<?php

namespace App\Http\Requests\Reporte;

use App\Enums\CategoriaReporte;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrearReporteRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'categoria' => ['required', Rule::enum(CategoriaReporte::class)],
            'asunto' => ['required', 'string', 'max:150'],
            'descripcion' => ['required', 'string', 'max:5000'],
            'vinculacion_id' => ['nullable', 'integer', 'exists:vinculacion,id'],
        ];
    }
}
