<?php

namespace App\Http\Requests\Docente;

use Illuminate\Foundation\Http\FormRequest;

/**
 * El docente solo se asigna como tutor. El cierre de la práctica tiene su
 * propio flujo (aprobación de la empresa o finalización del docente).
 */
class ActualizarVinculacionRequest extends FormRequest
{
    public function rules(): array
    {
        return ['asignarme_tutor' => ['required', 'accepted']];
    }
}
