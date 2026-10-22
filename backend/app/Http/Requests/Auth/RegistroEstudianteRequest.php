<?php

namespace App\Http\Requests\Auth;

use App\Models\Programa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class RegistroEstudianteRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['correo' => mb_strtolower(trim((string) $this->input('correo')))]);
    }

    public function rules(): array
    {
        $dominio = preg_quote(config('fet.dominio_correo'), '/');

        return [
            'correo' => ['required', 'email', 'max:150', "regex:/@{$dominio}$/", 'unique:usuario,correo'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'programa_id' => ['required', 'integer', 'exists:programa,id'],
            'codigo' => ['required', 'string', 'max:20', 'unique:estudiante,codigo'],
            'documento' => ['required', 'string', 'max:20', 'unique:estudiante,documento'],
            'nombres' => ['required', 'string', 'max:80'],
            'apellidos' => ['required', 'string', 'max:80'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'semestre_actual' => ['required', 'integer', 'min:1'],
            'es_egresado' => ['sometimes', 'boolean'],
            'acepta_politica' => ['accepted'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator) {
            $programa = Programa::find($this->integer('programa_id'));

            if ($programa && $this->integer('semestre_actual') > $programa->total_semestres) {
                $validator->errors()->add('semestre_actual', "El programa tiene {$programa->total_semestres} semestres.");
            }
        }];
    }

    public function messages(): array
    {
        return ['correo.regex' => 'Usa tu correo institucional @'.config('fet.dominio_correo').'.'];
    }
}
