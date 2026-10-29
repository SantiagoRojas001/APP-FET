<?php

namespace App\Http\Controllers\Empresa;

use App\Enums\Modalidad;
use App\Http\Controllers\Controller;
use App\Http\Resources\EstudianteResource;
use App\Models\Estudiante;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Hojas de vida publicadas por estudiantes habilitados que aún no cursan
 * práctica. Primero aparecen los que un docente recomendó para las vacantes
 * de la empresa.
 */
class HojaVidaController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'programa_id' => ['nullable', 'integer'],
            'modalidad' => ['nullable', Rule::enum(Modalidad::class)],
            'competencia_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $empresaId = $request->user()->empresa->id;
        $deLaEmpresa = fn ($r) => $r->whereHas('vacante', fn (Builder $v) => $v->where('empresa_id', $empresaId));

        $estudiantes = Estudiante::query()
            ->disponibles()
            ->with([
                'programa', 'competencias', 'proyectos', 'hojaVida.referencias',
                'recomendaciones' => fn ($r) => $deLaEmpresa($r)->with('vacante', 'docente'),
                'citas' => fn ($c) => $c->where('empresa_id', $empresaId),
            ])
            ->withCount(['recomendaciones as recomendado' => $deLaEmpresa])
            ->when($filtros['programa_id'] ?? null, fn (Builder $q, int $id) => $q->where('programa_id', $id))
            ->when($filtros['modalidad'] ?? null, fn (Builder $q, string $m) => $q->where('modalidad_grado', $m))
            ->when($filtros['competencia_id'] ?? null, fn (Builder $q, int $id) => $q->whereHas('competencias', fn (Builder $c) => $c->where('competencia.id', $id)))
            ->when($filtros['q'] ?? null, fn (Builder $q, string $t) => $q->where(fn (Builder $w) => $w->whereLike('nombres', "%{$t}%")->orWhereLike('apellidos', "%{$t}%")))
            ->orderByDesc('recomendado')
            ->orderBy('apellidos')
            ->paginate(20)
            ->withQueryString();

        return EstudianteResource::collection($estudiantes);
    }
}
