<?php

namespace App\Http\Requests\Docente;

use Illuminate\Foundation\Http\FormRequest;

class RegistrarPromedioRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        // Acepta la coma decimal que se usa en Colombia (4,25).
        $this->merge(['promedio' => str_replace(',', '.', (string) $this->input('promedio'))]);
    }

    public function rules(): array
    {
        return ['promedio' => ['required', 'numeric', 'min:0', 'max:5', 'decimal:0,2']];
    }

    public function messages(): array
    {
        return ['promedio.decimal' => 'Escribe el promedio con máximo dos decimales (por ejemplo, 4,25).'];
    }
}
