<?php

namespace App\Http\Controllers\Docente;

use App\Enums\Modalidad;
use App\Http\Controllers\Controller;
use App\Http\Resources\EstudianteResource;
use App\Models\Estudiante;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Estudiantes del programa del docente registrados desde el listado de
 * modalidades de grado (ver ImportacionController). Solo se registra a quien
 * queda habilitado, con su promedio.
 */
class EstudianteController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'programa_id' => ['nullable', 'integer'],
            'semestre' => ['nullable', 'integer', 'min:1'],
            'modalidad' => ['nullable', Rule::enum(Modalidad::class)],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $estudiantes = Estudiante::query()
            ->with('usuario', 'programa', 'hojaVida.referencias', 'vinculacionActiva.empresa')
            ->whereNotNull('modalidad_grado')
            ->delPrograma($request->user()->programaAsignado())
            ->when($filtros['programa_id'] ?? null, fn (Builder $q, int $id) => $q->where('programa_id', $id))
            ->when($filtros['semestre'] ?? null, fn (Builder $q, int $s) => $q->where('semestre_actual', $s))
            ->when($filtros['modalidad'] ?? null, fn (Builder $q, string $m) => $q->where('modalidad_grado', $m))
            ->when($filtros['q'] ?? null, fn (Builder $q, string $t) => $q->where(fn (Builder $w) => $w->whereLike('nombres', "%{$t}%")->orWhereLike('apellidos', "%{$t}%")))
            ->orderBy('apellidos')
            ->paginate(50)
            ->withQueryString();

        return EstudianteResource::collection($estudiantes);
    }
}
