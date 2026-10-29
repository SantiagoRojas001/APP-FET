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
            'decision' => ['required', Rule::in([EstadoPostulacion::Seleccionada->value, EstadoPostulacion::Descartada->value])],
        ];
    }

    public function selecciona(): bool
    {
        return $this->input('decision') === EstadoPostulacion::Seleccionada->value;
    }
}
