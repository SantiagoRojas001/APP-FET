<?php

namespace App\Http\Requests\Seguimiento;

use App\Enums\TipoDocumento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubirDocumentoRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::enum(TipoDocumento::class)],
            'periodo_inicio' => ['required', 'date', 'before_or_equal:today'],
            'periodo_fin' => ['required', 'date', 'after_or_equal:periodo_inicio'],
            'archivo' => ['required', 'file', 'mimes:'.implode(',', config('fet.documentos.extensiones')), 'max:'.config('fet.documentos.max_kb')],
        ];
    }
}
