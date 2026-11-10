<?php

namespace App\Http\Controllers;

use App\Http\Resources\EstudianteResource;
use App\Models\Estudiante;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Consulta de perfiles de estudiantes. La administración busca entre todos;
 * docentes y directores, entre los de su programa. La empresa no busca aquí
 * (usa las hojas de vida publicadas) y solo abre el perfil de quien tiene su
 * hoja de vida publicada o una cita con ella.
 */
class PerfilEstudianteController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'programa_id' => ['nullable', 'integer'],
            'semestre' => ['nullable', 'integer', 'min:1'],
            'competencia_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
            'disponibles' => ['nullable', 'boolean'],
        ]);

        $estudiantes = Estudiante::query()
            ->with('programa', 'competencias', 'proyectos', 'hojaVida.referencias', 'vinculacionActiva')
            ->when($request->boolean('disponibles'), fn (Builder $q) => $q->disponibles())
            ->delPrograma($request->user()->programaAsignado())
            ->when($filtros['programa_id'] ?? null, fn (Builder $q, int $id) => $q->where('programa_id', $id))
            ->when($filtros['semestre'] ?? null, fn (Builder $q, int $s) => $q->where('semestre_actual', $s))
            ->when($filtros['competencia_id'] ?? null, fn (Builder $q, int $id) => $q->whereHas('competencias', fn (Builder $c) => $c->where('competencia.id', $id)))
            ->when($filtros['q'] ?? null, fn (Builder $q, string $t) => $q->where(fn (Builder $w) => $w->whereLike('nombres', "%{$t}%")->orWhereLike('apellidos', "%{$t}%")))
            ->orderBy('apellidos')
            ->paginate(20)
            ->withQueryString();

        return EstudianteResource::collection($estudiantes);
    }

    public function show(Request $request, Estudiante $estudiante): EstudianteResource
    {
        Gate::authorize('view', $estudiante);

        return new EstudianteResource($estudiante->cargarHojaVida());
    }
}
