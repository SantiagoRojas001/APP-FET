<?php

namespace App\Http\Controllers\Docente;

use App\Enums\EstadoPostulacion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Docente\RevisarPostulacionRequest;
use App\Http\Resources\PostulacionResource;
use App\Models\Postulacion;
use App\Services\PostulacionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * El docente verifica los requisitos de cada postulación antes de que la
 * empresa la reciba.
 */
class PostulacionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate(['estado' => ['nullable', Rule::enum(EstadoPostulacion::class)]]);

        $postulaciones = Postulacion::query()
            ->with('vacante.empresa', 'estudiante.programa', 'estudiante.hojaVida', 'estudiante.competencias', 'estudiante.proyectos')
            ->when($filtros['estado'] ?? null, fn ($q, string $estado) => $q->where('estado', $estado))
            ->latest('fecha')
            ->get();

        return PostulacionResource::collection($postulaciones);
    }

    public function update(RevisarPostulacionRequest $request, Postulacion $postulacion, PostulacionService $servicio): PostulacionResource
    {
        $postulacion = $servicio->revisarComoDocente($postulacion, $request->aprueba(), $request->validated('observacion'));

        return new PostulacionResource($postulacion->load('vacante.empresa', 'estudiante.programa'));
    }
}
