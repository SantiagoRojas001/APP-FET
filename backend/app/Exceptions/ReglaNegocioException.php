<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Una acción válida en forma que incumple una regla del proceso de prácticas
 * (por ejemplo, postularse a una pasantía sin estar en semestre habilitado).
 */
class ReglaNegocioException extends Exception
{
    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 422);
    }
}
