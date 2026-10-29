<?php

namespace App\Http\Requests\Docente;

use Illuminate\Foundation\Http\FormRequest;

class AutorizarCitaRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'autorizar' => ['required', 'boolean'],
            'observacion' => ['nullable', 'string', 'max:1000', 'required_if_declined:autorizar'],
            'fecha_inicio' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return ['observacion.required_if_declined' => 'Escribe el motivo para que el estudiante y la empresa lo conozcan.'];
    }
}
