<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarUsuarioRequest extends FormRequest
{
    public function rules(): array
    {
        return ['activo' => ['required', 'boolean']];
    }
}
