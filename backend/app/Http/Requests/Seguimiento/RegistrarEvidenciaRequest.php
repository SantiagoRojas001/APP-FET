<?php

namespace App\Http\Requests\Seguimiento;

use Illuminate\Foundation\Http\FormRequest;

/** Evidencia de una semana; al corregirla se envían los mismos campos sin la semana. */
class RegistrarEvidenciaRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'semana_inicio' => [$this->route('evidencia') ? 'exclude' : 'required', 'date', 'before_or_equal:today'],
            'horas' => ['required', 'numeric', 'min:0.5', 'max:60', 'multiple_of:0.5'],
            'actividades' => ['required', 'string', 'max:2000'],
            'archivo' => ['nullable', 'file', 'mimes:'.implode(',', config('fet.evidencias.extensiones')), 'max:'.config('fet.evidencias.max_kb')],
        ];
    }

    public function messages(): array
    {
        return ['horas.multiple_of' => 'Registra las horas en múltiplos de media hora (por ejemplo, 40 o 37,5).'];
    }
}
