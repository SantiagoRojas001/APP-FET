<?php

namespace App\Http\Requests\Admin;

use App\Enums\EstadoReporte;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AtenderReporteRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'estado' => ['required', Rule::enum(EstadoReporte::class)],
            'respuesta' => ['nullable', 'string', 'max:5000', Rule::requiredIf($this->input('estado') === EstadoReporte::Resuelto->value)],
        ];
    }

    public function messages(): array
    {
        return ['respuesta.required' => 'Escribe la respuesta para quien reportó el problema.'];
    }
}
