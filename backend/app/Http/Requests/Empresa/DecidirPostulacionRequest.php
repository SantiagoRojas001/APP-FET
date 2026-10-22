<?php

namespace App\Http\Requests\Empresa;

use App\Enums\EstadoPostulacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecidirPostulacionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in([EstadoPostulacion::Aceptada->value, EstadoPostulacion::Descartada->value])],
            'fecha_inicio' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }

    public function acepta(): bool
    {
        return $this->input('decision') === EstadoPostulacion::Aceptada->value;
    }
}
