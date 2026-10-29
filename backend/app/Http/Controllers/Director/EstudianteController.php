<?php

namespace App\Http\Controllers\Director;

use App\Enums\EstadoVinculacion;
use App\Enums\Modalidad;
use App\Http\Controllers\Controller;
use App\Http\Resources\EstudianteResource;
use App\Models\Estudiante;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Estudiantes del programa del director, con su ciclo académico y si cursan
 * pasantía, práctica o aún no.
 */
class EstudianteController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'ciclo' => ['nullable', Rule::in(['Técnico', 'Tecnólogo', 'Profesional'])],
            'practica' => ['nullable', Rule::in([...Modalidad::values(), 'NINGUNA'])],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $activa = fn (Builder $v) => $v->where('estado', EstadoVinculacion::Activa);

        $estudiantes = Estudiante::query()
            ->where('programa_id', $request->user()->programa_id)
            ->with('usuario', 'programa', 'hojaVida.referencias', 'vinculacionActiva.empresa')
            ->when($filtros['ciclo'] ?? null, fn (Builder $q, string $c) => match ($c) {
                'Técnico' => $q->where('semestre_actual', '<=', 4),
                'Tecnólogo' => $q->whereBetween('semestre_actual', [5, 6]),
                'Profesional' => $q->where('semestre_actual', '>=', 7),
            })
            ->when($filtros['practica'] ?? null, fn (Builder $q, string $p) => $p === 'NINGUNA'
                ? $q->whereDoesntHave('vinculaciones', $activa)
                : $q->whereHas('vinculaciones', fn (Builder $v) => $activa($v)->where('tipo', $p)))
            ->when($filtros['q'] ?? null, fn (Builder $q, string $t) => $q->where(fn (Builder $w) => $w->whereLike('nombres', "%{$t}%")->orWhereLike('apellidos', "%{$t}%")))
            ->orderBy('semestre_actual')
            ->orderBy('apellidos')
            ->paginate(50)
            ->withQueryString();

        return EstudianteResource::collection($estudiantes);
    }
}
