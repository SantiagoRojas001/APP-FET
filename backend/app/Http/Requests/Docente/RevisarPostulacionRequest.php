<?php

namespace App\Http\Requests\Docente;

use App\Enums\EstadoPostulacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RevisarPostulacionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in([EstadoPostulacion::Aprobada->value, EstadoPostulacion::Rechazada->value])],
            'observacion' => ['nullable', 'string', 'max:1000', 'required_if:decision,'.EstadoPostulacion::Rechazada->value],
        ];
    }

    public function aprueba(): bool
    {
        return $this->input('decision') === EstadoPostulacion::Aprobada->value;
    }

    public function messages(): array
    {
        return ['observacion.required_if' => 'Escribe el motivo del rechazo para que el estudiante lo conozca.'];
    }
}
