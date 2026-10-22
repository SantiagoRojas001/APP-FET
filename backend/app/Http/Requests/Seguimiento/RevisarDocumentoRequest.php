<?php

namespace App\Http\Requests\Seguimiento;

use App\Enums\EstadoRevision;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RevisarDocumentoRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'estado_revision' => ['required', Rule::in([EstadoRevision::Aprobado->value, EstadoRevision::Observado->value])],
            'observaciones' => ['nullable', 'string', 'max:2000', 'required_if:estado_revision,'.EstadoRevision::Observado->value],
        ];
    }

    public function messages(): array
    {
        return ['observaciones.required_if' => 'Escribe las observaciones para que el estudiante pueda corregir.'];
    }
}
