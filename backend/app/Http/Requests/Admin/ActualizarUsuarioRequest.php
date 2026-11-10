<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarUsuarioRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'activo' => ['sometimes', 'boolean'],
            // Programa del docente o director.
            'programa_id' => ['sometimes', 'nullable', 'integer', 'exists:programa,id'],
        ];
    }
}
