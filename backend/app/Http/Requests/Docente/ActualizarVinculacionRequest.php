<?php

namespace App\Http\Requests\Docente;

use App\Enums\EstadoVinculacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActualizarVinculacionRequest extends FormRequest
{
    public function rules(): array
    {
        $cierra = Rule::requiredIf(fn () => $this->input('estado') !== null && $this->input('estado') !== EstadoVinculacion::Activa->value);

        return [
            'asignarme_tutor' => ['sometimes', 'boolean'],
            'estado' => ['sometimes', Rule::enum(EstadoVinculacion::class)],
            'fecha_fin' => ['nullable', 'date', $cierra],
            'reconocida_como_practica' => ['nullable', 'boolean'],
            'observacion_final' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
