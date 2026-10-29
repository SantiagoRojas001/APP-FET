<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RevisarAcuerdoRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'aprobar' => ['required', 'boolean'],
            'observacion' => ['nullable', 'string', 'max:1000', 'required_if_declined:aprobar'],
        ];
    }

    public function messages(): array
    {
        return ['observacion.required_if_declined' => 'Escribe el motivo del rechazo para que la empresa lo conozca.'];
    }
}
