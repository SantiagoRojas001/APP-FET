<?php

namespace App\Http\Requests\Admin;

use App\Models\PeriodoAcademico;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class GuardarPeriodoRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'regex:/^\d{4}-[12]$/', 'unique:periodo_academico,nombre'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after:fecha_inicio'],
        ];
    }

    /** Los periodos no se cruzan: cada vinculación pertenece a uno solo. */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $cruce = PeriodoAcademico::query()
                ->whereDate('fecha_inicio', '<=', $this->date('fecha_fin'))
                ->whereDate('fecha_fin', '>=', $this->date('fecha_inicio'))
                ->first();

            if ($cruce) {
                $validator->errors()->add('fecha_inicio', "Las fechas se cruzan con el periodo {$cruce->nombre}.");
            }
        }];
    }

    public function messages(): array
    {
        return ['nombre.regex' => 'Usa el formato año-semestre, por ejemplo 2026-2.'];
    }
}
