<?php

namespace App\Http\Requests\Docente;

use App\Enums\Modalidad;
use App\Models\Programa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Estudiante que el docente registra a partir del listado de modalidades de
 * grado: solo quienes eligieron pasantía o práctica.
 */
class RegistrarEstudianteRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['correo' => mb_strtolower(trim((string) $this->input('correo')))]);
    }

    public function rules(): array
    {
        return [
            'correo' => ['required', 'email', 'max:150', 'unique:usuario,correo'],
            'nombres' => ['required', 'string', 'max:80'],
            'apellidos' => ['required', 'string', 'max:80'],
            'programa_id' => ['required', 'integer', 'exists:programa,id'],
            'semestre_actual' => ['required', 'integer', 'min:1'],
            'modalidad_grado' => ['required', Rule::enum(Modalidad::class)],
            'codigo' => ['nullable', 'string', 'max:20', 'unique:estudiante,codigo'],
            'documento' => ['nullable', 'string', 'max:20', 'unique:estudiante,documento'],
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
        return ['correo.unique' => 'Ya hay una cuenta con este correo.'];
    }
}
