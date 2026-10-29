<?php

namespace App\Http\Controllers\Docente;

use App\Enums\EstadoPostulacion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Docente\AutorizarPostulacionRequest;
use App\Http\Resources\PostulacionResource;
use App\Models\Postulacion;
use App\Services\PostulacionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * El docente autoriza la práctica de los estudiantes que una empresa
 * seleccionó, con los requisitos a la vista.
 */
class PostulacionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate(['estado' => ['nullable', Rule::enum(EstadoPostulacion::class)]]);

        $postulaciones = Postulacion::query()
            ->with('vacante.empresa', 'estudiante.programa', 'estudiante.hojaVida.referencias', 'estudiante.competencias', 'estudiante.proyectos')
            ->when($filtros['estado'] ?? null, fn ($q, string $estado) => $q->where('estado', $estado))
            ->latest('fecha')
            ->get();

        return PostulacionResource::collection($postulaciones);
    }

    public function update(AutorizarPostulacionRequest $request, Postulacion $postulacion, PostulacionService $servicio): PostulacionResource
    {
        $postulacion = $servicio->autorizarComoDocente(
            $postulacion,
            $request->user(),
            $request->autoriza(),
            $request->validated('observacion'),
            $request->date('fecha_inicio'),
        );

        return new PostulacionResource($postulacion->load('vacante.empresa', 'estudiante.programa'));
    }
}
