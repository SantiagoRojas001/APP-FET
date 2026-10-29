<?php

namespace App\Http\Requests\Seguimiento;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Las respuestas se validan contra el cuestionario en EvaluacionService.
 */
class ResponderEvaluacionRequest extends FormRequest
{
    public function rules(): array
    {
        return ['respuestas' => ['required', 'array']];
    }
}
