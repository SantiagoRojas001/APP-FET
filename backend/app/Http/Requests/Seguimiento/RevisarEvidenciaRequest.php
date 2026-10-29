<?php

namespace App\Http\Requests\Seguimiento;

use App\Enums\EstadoRevision;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RevisarEvidenciaRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'estado' => ['required', Rule::in([EstadoRevision::Aprobado->value, EstadoRevision::Observado->value])],
            'comentario' => ['nullable', 'string', 'max:1000', 'required_if:estado,'.EstadoRevision::Observado->value],
        ];
    }

    public function aprueba(): bool
    {
        return $this->input('estado') === EstadoRevision::Aprobado->value;
    }

    public function messages(): array
    {
        return ['comentario.required_if' => 'Comenta qué debe corregir el practicante (por ejemplo, las horas de la semana).'];
    }
}
