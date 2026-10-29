<?php

namespace App\Http\Requests\Docente;

use App\Enums\EstadoPostulacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AutorizarPostulacionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in([EstadoPostulacion::Aceptada->value, EstadoPostulacion::Rechazada->value])],
            'observacion' => ['nullable', 'string', 'max:1000', 'required_if:decision,'.EstadoPostulacion::Rechazada->value],
            'fecha_inicio' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }

    public function autoriza(): bool
    {
        return $this->input('decision') === EstadoPostulacion::Aceptada->value;
    }

    public function messages(): array
    {
        return ['observacion.required_if' => 'Escribe el motivo para que el estudiante y la empresa lo conozcan.'];
    }
}
