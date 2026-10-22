<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'correo' => ['required', 'email', 'max:150'],
            'password' => ['required', 'string'],
        ];
    }
}
