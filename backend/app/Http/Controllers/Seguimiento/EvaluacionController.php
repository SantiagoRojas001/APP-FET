<?php

namespace App\Http\Controllers\Seguimiento;

use App\Enums\Rol;
use App\Enums\TipoEvaluacion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seguimiento\ResponderEvaluacionRequest;
use App\Http\Resources\EvaluacionResource;
use App\Models\Evaluacion;
use App\Models\Usuario;
use App\Models\Vinculacion;
use App\Services\EvaluacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Cuestionarios de cierre de una práctica.
 */
class EvaluacionController extends Controller
{
    /**
     * El cuestionario que le corresponde al usuario (si evalúa) y las
     * evaluaciones que puede leer.
     */
    public function index(Request $request, Vinculacion $vinculacion, EvaluacionService $evaluaciones): JsonResponse
    {
        Gate::authorize('view', $vinculacion);

        $usuario = $request->user();
        $tipo = $usuario->can('evaluar', $vinculacion) ? TipoEvaluacion::delRol($usuario->rol) : null;

        return response()->json([
            'habilitada' => $evaluaciones->habilitada($vinculacion),
            'cuestionario' => $tipo ? [
                'tipo' => ['valor' => $tipo->value, 'etiqueta' => $tipo->label()],
                'preguntas' => $evaluaciones->preguntas($tipo),
            ] : null,
            'evaluaciones' => EvaluacionResource::collection(
                $vinculacion->evaluaciones->filter(fn (Evaluacion $e) => $this->puedeLeer($usuario, $e))->values(),
            ),
        ]);
    }

    public function store(ResponderEvaluacionRequest $request, Vinculacion $vinculacion, EvaluacionService $evaluaciones): JsonResponse
    {
        Gate::authorize('evaluar', $vinculacion);

        $evaluacion = $evaluaciones->responder($vinculacion, $request->user(), $request->validated('respuestas'));

        return (new EvaluacionResource($evaluacion->refresh()))->response()->setStatusCode(201);
    }

    /**
     * La FET lee todas y la empresa solo la suya. El estudiante lee todas las
     * de su práctica: la suya y las que le hicieron.
     */
    private function puedeLeer(Usuario $usuario, Evaluacion $evaluacion): bool
    {
        return match ($usuario->rol) {
            Rol::Admin, Rol::Docente => true,
            Rol::Empresa => $evaluacion->tipo === TipoEvaluacion::EmpresaAPracticante,
            Rol::Estudiante => true,
        };
    }
}
