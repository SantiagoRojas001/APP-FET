<?php

namespace App\Http\Requests\Admin;

use App\Enums\Rol;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Cuentas de docentes y administradores. Estudiantes y empresas se registran solos.
 */
class CrearUsuarioRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['correo' => mb_strtolower(trim((string) $this->input('correo')))]);
    }

    public function rules(): array
    {
        $dominio = preg_quote(config('fet.dominio_correo'), '/');

        return [
            'correo' => ['required', 'email', 'max:150', "regex:/@{$dominio}$/", 'unique:usuario,correo'],
            'password' => ['required', Password::defaults()],
            'rol' => ['required', Rule::in([Rol::Docente->value, Rol::Admin->value])],
        ];
    }

    public function messages(): array
    {
        return ['correo.regex' => 'Docentes y administradores usan correo @'.config('fet.dominio_correo').'.'];
    }
}
