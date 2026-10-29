<?php

namespace App\Http\Requests\Admin;

use App\Models\Estudiante;
use App\Models\Programa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Datos de un estudiante que crea o corrige la administración. Al crearlo se
 * pide el correo institucional, al que llega la invitación.
 */
class GuardarEstudianteRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('correo')) {
            $this->merge(['correo' => mb_strtolower(trim((string) $this->input('correo')))]);
        }
    }

    public function rules(): array
    {
        /** @var Estudiante|null $estudiante */
        $estudiante = $this->route('estudiante');
        $dominio = preg_quote(config('fet.dominio_correo'), '/');

        return [
            'correo' => $estudiante ? ['prohibited'] : ['required', 'email', 'max:150', "regex:/@{$dominio}$/", 'unique:usuario,correo'],
            'programa_id' => ['required', 'integer', 'exists:programa,id'],
            'codigo' => ['required', 'string', 'max:20', Rule::unique('estudiante', 'codigo')->ignore($estudiante)],
            'documento' => ['required', 'string', 'max:20', Rule::unique('estudiante', 'documento')->ignore($estudiante)],
            'nombres' => ['required', 'string', 'max:80'],
            'apellidos' => ['required', 'string', 'max:80'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'semestre_actual' => ['required', 'integer', 'min:1'],
            'es_egresado' => ['sometimes', 'boolean'],
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
        return ['correo.regex' => 'Los estudiantes usan su correo institucional @'.config('fet.dominio_correo').'.'];
    }
}
