<?php

namespace App\Http\Requests\Docente;

use Illuminate\Foundation\Http\FormRequest;

class FinalizarVinculacionRequest extends FormRequest
{
    public function rules(): array
    {
        return ['justificacion' => ['nullable', 'string', 'max:2000']];
    }
}
