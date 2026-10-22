<?php

namespace App\Http\Requests\Admin;

use App\Enums\EstadoValidacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidarEmpresaRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'estado_validacion' => ['required', Rule::in([EstadoValidacion::Validada->value, EstadoValidacion::Rechazada->value])],
        ];
    }
}
