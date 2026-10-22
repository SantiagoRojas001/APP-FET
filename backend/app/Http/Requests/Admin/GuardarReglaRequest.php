<?php

namespace App\Http\Requests\Admin;

use App\Models\Programa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class GuardarReglaRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'programa_id' => ['required', 'integer', 'exists:programa,id'],
            'semestre_minimo' => ['required', 'integer', 'min:1'],
            'vigente_desde' => ['required', 'date'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator) {
            $programa = Programa::find($this->integer('programa_id'));

            if ($programa && $this->integer('semestre_minimo') > $programa->total_semestres) {
                $validator->errors()->add('semestre_minimo', "El programa tiene {$programa->total_semestres} semestres.");
            }
        }];
    }
}
