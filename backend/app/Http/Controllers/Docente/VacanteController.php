<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Http\Resources\VacanteResource;
use App\Models\Estudiante;
use App\Models\Recomendacion;
use App\Models\Vacante;
use App\Services\Auditoria;
use App\Services\RecomendacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Vacantes de las empresas, que solo ven los docentes para recomendar
 * estudiantes.
 */
class VacanteController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $vacantes = Vacante::query()
            ->publicadas()
            ->with('empresa', 'recomendaciones.estudiante.programa', 'recomendaciones.docente')
            ->latest('publicada_en')
            ->get();

        return VacanteResource::collection($vacantes);
    }

    public function recomendar(Request $request, Vacante $vacante, RecomendacionService $recomendaciones): JsonResponse
    {
        $datos = $request->validate([
            'estudiante_id' => ['required', 'integer', 'exists:estudiante,id'],
            'comentario' => ['nullable', 'string', 'max:500'],
        ]);

        $estudiante = Estudiante::findOrFail($datos['estudiante_id']);
        Gate::authorize('view', $estudiante); // solo estudiantes de su programa

        $recomendaciones->recomendar($vacante, $estudiante, $request->user(), $datos['comentario'] ?? null);

        return response()->json(['message' => 'Recomendación enviada a la empresa.'], 201);
    }

    public function quitar(Vacante $vacante, Recomendacion $recomendacion): Response
    {
        abort_unless($recomendacion->vacante_id === $vacante->id, 404);

        $recomendacion->delete();
        Auditoria::registrar('recomendacion.retirada', $vacante, ['estudiante_id' => $recomendacion->estudiante_id]);

        return response()->noContent();
    }
}
