<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

/**
 * Primer ingreso de un estudiante: cambia la contraseña que recibió por
 * correo y acepta la política de tratamiento de datos (Ley 1581 de 2012).
 */
class PrimerIngresoRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'password' => ['required', 'confirmed', Password::defaults()],
            'acepta_politica' => ['accepted'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator) {
            if (Hash::check((string) $this->input('password'), $this->user()->password_hash)) {
                $validator->errors()->add('password', 'La nueva contraseña debe ser distinta de la que recibiste por correo.');
            }
        }];
    }
}
