<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RestablecerContrasenaRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['correo' => mb_strtolower(trim((string) $this->input('correo')))]);
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'correo' => ['required', 'email', 'max:150'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}
