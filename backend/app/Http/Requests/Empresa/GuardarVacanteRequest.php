<?php

namespace App\Http\Requests\Empresa;

use App\Enums\EstadoVacante;
use App\Enums\Modalidad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarVacanteRequest extends FormRequest
{
    public function rules(): array
    {
        $creando = $this->isMethod('POST');

        return [
            'titulo' => [$creando ? 'required' : 'sometimes', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'modalidad' => [$creando ? 'required' : 'sometimes', Rule::enum(Modalidad::class)],
            'estado' => ['sometimes', Rule::enum(EstadoVacante::class)],
        ];
    }
}
