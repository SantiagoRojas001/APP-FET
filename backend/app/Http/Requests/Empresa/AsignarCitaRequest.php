<?php

namespace App\Http\Requests\Empresa;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/** Cita con un estudiante: fecha y hora (hora de Colombia) y dirección. */
class AsignarCitaRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'estudiante_id' => ['required', 'integer', 'exists:estudiante,id'],
            'fecha_hora' => ['required', 'date', 'after:now'],
            'direccion' => ['required', 'string', 'max:200'],
            'indicaciones' => ['nullable', 'string', 'max:1000'],
            'vacante_id' => ['nullable', 'integer'],
        ];
    }

    /** @return array{fecha_hora: Carbon, direccion: string, indicaciones: string|null, vacante_id: int|null} */
    public function datosCita(): array
    {
        return [
            'fecha_hora' => Carbon::parse($this->validated('fecha_hora'), config('fet.zona_horaria'))->utc(),
            'direccion' => $this->validated('direccion'),
            'indicaciones' => $this->validated('indicaciones'),
            'vacante_id' => $this->validated('vacante_id'),
        ];
    }

    public function messages(): array
    {
        return ['fecha_hora.after' => 'La cita debe ser en una fecha y hora futuras.'];
    }
}
