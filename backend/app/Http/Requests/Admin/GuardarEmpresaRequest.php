<?php

namespace App\Http\Requests\Admin;

use App\Enums\Modalidad;
use App\Models\Empresa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Datos de una empresa que corrige la administración (el correo de la cuenta
 * no cambia).
 */
class GuardarEmpresaRequest extends FormRequest
{
    public function rules(): array
    {
        /** @var Empresa|null $empresa */
        $empresa = $this->route('empresa');

        return [
            'correo' => ['prohibited'],
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
