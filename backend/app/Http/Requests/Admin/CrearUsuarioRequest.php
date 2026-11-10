<?php

namespace App\Http\Requests\Admin;

use App\Enums\Rol;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cuentas de docentes, directores de programa y administradores. La persona
 * define su contraseña con el enlace de invitación. El director dirige un
 * programa; el docente pertenece a uno (sin programa, atiende a todos).
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
            'rol' => ['required', Rule::in([Rol::Docente->value, Rol::Director->value, Rol::Admin->value])],
            'programa_id' => ['nullable', 'integer', 'exists:programa,id', 'required_if:rol,'.Rol::Director->value, 'prohibited_if:rol,'.Rol::Admin->value],
        ];
    }

    public function messages(): array
    {
        return [
            'correo.regex' => 'Docentes, directores y administradores usan correo @'.config('fet.dominio_correo').'.',
            'programa_id.required_if' => 'Elige el programa que dirige.',
        ];
    }
}
