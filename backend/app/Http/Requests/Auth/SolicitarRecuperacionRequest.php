<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class SolicitarRecuperacionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['correo' => mb_strtolower(trim((string) $this->input('correo')))]);
    }

    public function rules(): array
    {
        return ['correo' => ['required', 'email', 'max:150']];
    }
}
