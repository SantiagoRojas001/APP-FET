<?php

namespace App\Http\Requests\Admin;

use App\Enums\Modalidad;
use App\Models\Empresa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Empresa que invita o corrige la administración. Al invitarla se pide el
 * correo de la cuenta, al que llega el enlace de activación.
 */
class GuardarEmpresaRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('correo')) {
            $this->merge(['correo' => mb_strtolower(trim((string) $this->input('correo')))]);
        }
    }

    public function rules(): array
    {
        /** @var Empresa|null $empresa */
        $empresa = $this->route('empresa');

        return [
            'correo' => $empresa ? ['prohibited'] : ['required', 'email', 'max:150', 'unique:usuario,correo'],
            'nit' => ['required', 'string', 'max:20', 'regex:/^[0-9.\-]+$/', Rule::unique('empresa', 'nit')->ignore($empresa)],
            'razon_social' => ['required', 'string', 'max:150'],
            'sector' => ['nullable', 'string', 'max:80'],
            'contacto_nombre' => ['required', 'string', 'max:120'],
            'contacto_telefono' => ['required', 'string', 'max:20'],
            'modalidad_interes' => ['required', Rule::enum(Modalidad::class)],
        ];
    }

    public function messages(): array
    {
        return ['nit.regex' => 'El NIT solo puede tener números, puntos y guion.'];
    }
}
