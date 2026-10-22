<?php

namespace App\Http\Requests\Auth;

use App\Enums\Modalidad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegistroEmpresaRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['correo' => mb_strtolower(trim((string) $this->input('correo')))]);
    }

    public function rules(): array
    {
        return [
            'correo' => ['required', 'email', 'max:150', 'unique:usuario,correo'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'nit' => ['required', 'string', 'max:20', 'regex:/^[0-9.\-]+$/', 'unique:empresa,nit'],
            'razon_social' => ['required', 'string', 'max:150'],
            'sector' => ['nullable', 'string', 'max:80'],
            'contacto_nombre' => ['required', 'string', 'max:120'],
            'contacto_telefono' => ['required', 'string', 'max:20'],
            'modalidad_interes' => ['required', Rule::enum(Modalidad::class)],
            'acepta_politica' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return ['nit.regex' => 'El NIT solo puede tener números, puntos y guion.'];
    }
}
