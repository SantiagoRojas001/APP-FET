<?php

namespace App\Http\Controllers\Docente;

use App\Enums\EstadoVinculacion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Docente\ActualizarVinculacionRequest;
use App\Http\Requests\Docente\FinalizarVinculacionRequest;
use App\Http\Resources\VinculacionResource;
use App\Models\Vinculacion;
use App\Services\Auditoria;
use App\Services\PracticaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Seguimiento docente de las pasantías y prácticas.
 */
class VinculacionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'estado' => ['nullable', Rule::enum(EstadoVinculacion::class)],
            'periodo_id' => ['nullable', 'integer'],
            'mias' => ['nullable', 'boolean'],
        ]);

        $vinculaciones = Vinculacion::query()
            ->conSeguimiento()
            ->when($filtros['estado'] ?? null, fn ($q, string $estado) => $q->where('estado', $estado))
            ->when($filtros['periodo_id'] ?? null, fn ($q, int $periodo) => $q->where('periodo_id', $periodo))
            ->when($request->boolean('mias'), fn ($q) => $q->where('tutor_id', $request->user()->id))
            ->latest('fecha_inicio')
            ->get();

        return VinculacionResource::collection($vinculaciones);
    }

    /** El docente se asigna como tutor de la práctica. */
    public function update(ActualizarVinculacionRequest $request, Vinculacion $vinculacion): VinculacionResource
    {
        Gate::authorize('update', $vinculacion);

        $vinculacion->update(['tutor_id' => $request->user()->id]);
        Auditoria::registrar('vinculacion.tutor_asignado', $vinculacion);

        return new VinculacionResource($vinculacion->cargarSeguimiento());
    }

    /** Finalización anticipada: exige varios reportes y, si faltan horas, justificación. */
    public function finalizar(FinalizarVinculacionRequest $request, Vinculacion $vinculacion, PracticaService $practica): VinculacionResource
    {
        Gate::authorize('finalizar', $vinculacion);

        $vinculacion = $practica->finalizarComoDocente($vinculacion, $request->user(), $request->validated('justificacion'));

        return new VinculacionResource($vinculacion->cargarSeguimiento());
    }
}
