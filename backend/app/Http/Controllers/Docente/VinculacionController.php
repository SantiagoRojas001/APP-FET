<?php

namespace App\Http\Controllers\Docente;

use App\Enums\EstadoVinculacion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Docente\ActualizarVinculacionRequest;
use App\Http\Resources\VinculacionResource;
use App\Models\Vinculacion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Seguimiento docente de las prácticas y contrataciones en curso.
 */
class VinculacionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'estado' => ['nullable', Rule::enum(EstadoVinculacion::class)],
            'mias' => ['nullable', 'boolean'],
        ]);

        $vinculaciones = Vinculacion::query()
            ->with('estudiante.programa', 'empresa', 'vacante', 'tutor', 'documentos')
            ->when($filtros['estado'] ?? null, fn ($q, string $estado) => $q->where('estado', $estado))
            ->when($request->boolean('mias'), fn ($q) => $q->where('tutor_id', $request->user()->id))
            ->latest('fecha_inicio')
            ->get();

        return VinculacionResource::collection($vinculaciones);
    }

    public function update(ActualizarVinculacionRequest $request, Vinculacion $vinculacion): VinculacionResource
    {
        Gate::authorize('update', $vinculacion);

        $datos = $request->safe()->except('asignarme_tutor');

        if ($request->boolean('asignarme_tutor')) {
            $datos['tutor_id'] = $request->user()->id;
        }

        $vinculacion->update($datos);

        return new VinculacionResource($vinculacion->load('estudiante.programa', 'empresa', 'vacante', 'tutor', 'documentos'));
    }
}
