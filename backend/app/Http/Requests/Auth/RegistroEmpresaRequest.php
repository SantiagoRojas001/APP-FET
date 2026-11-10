<?php

namespace App\Http\Requests\Auth;

use App\Enums\Modalidad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Registro público de una empresa (/empresasRegistro) con la firma de su
 * representante legal y el convenio vigente con la FET. Sin convenio, la
 * empresa envía una solicitud de convenio (`con_convenio` = false): no se
 * crea la cuenta, por eso no lleva contraseña ni el PDF.
 */
class RegistroEmpresaRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['correo' => mb_strtolower(trim((string) $this->input('correo')))]);
    }

    public function rules(): array
    {
        $conConvenio = $this->boolean('con_convenio');

        return [
            'con_convenio' => ['required', 'boolean'],
            'correo' => ['required', 'email', 'max:150', ...($conConvenio ? ['unique:usuario,correo'] : [])],
            'nit' => ['required', 'string', 'max:20', 'regex:/^[0-9.\-]+$/', 'unique:empresa,nit'],
            'razon_social' => ['required', 'string', 'max:150'],
            'sector' => ['nullable', 'string', 'max:80'],
            'contacto_nombre' => ['required', 'string', 'max:120'],
            'contacto_telefono' => ['required', 'string', 'max:20'],
            'modalidad_interes' => ['required', Rule::enum(Modalidad::class)],
            'representante_nombre' => ['required', 'string', 'max:120'],
            'representante_documento' => ['required', 'string', 'max:20', 'regex:/^[0-9.\-]+$/'],
            'firma' => ['required', 'file', 'mimes:png', 'max:300', 'dimensions:min_width=300,max_width=2400,min_height=100,max_height=1200'],
            'acepta_politica' => ['accepted'],
            // Solo con convenio: la cuenta y el PDF del convenio vigente.
            'password' => $conConvenio ? ['required', 'confirmed', Password::defaults()] : ['prohibited'],
            'convenio' => $conConvenio ? ['required', 'file', 'mimes:pdf', 'max:10240'] : ['prohibited'],
            'vigencia_inicio' => $conConvenio ? ['required', 'date'] : ['prohibited'],
            'vigencia_fin' => $conConvenio ? ['required', 'date', 'after_or_equal:vigencia_inicio', 'after_or_equal:today'] : ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'nit.regex' => 'El NIT solo puede tener números, puntos y guion.',
            'nit.unique' => 'Esta empresa ya está registrada en APP FET.',
            'representante_documento.regex' => 'El documento solo puede tener números, puntos y guion.',
            'firma.required' => 'El representante legal debe firmar el registro.',
            'convenio.required' => 'Adjunta el convenio vigente con la FET en PDF.',
            'vigencia_fin.after_or_equal' => 'El convenio debe estar vigente.',
        ];
    }
}
