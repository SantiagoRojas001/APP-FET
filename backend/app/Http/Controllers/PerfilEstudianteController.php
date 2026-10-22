<?php

namespace App\Http\Controllers;

use App\Http\Resources\EstudianteResource;
use App\Models\Estudiante;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Consulta de perfiles de estudiantes para empresas, docentes y administración.
 * Las empresas ven todos los perfiles: el campo `habilitado_para_practica`
 * indica si ya pueden vincularse como pasantes o practicantes.
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
        ]);

        $estudiantes = Estudiante::query()
            ->with('programa', 'competencias', 'proyectos', 'hojaVida')
            ->when($filtros['programa_id'] ?? null, fn (Builder $q, int $id) => $q->where('programa_id', $id))
            ->when($filtros['semestre'] ?? null, fn (Builder $q, int $s) => $q->where('semestre_actual', $s))
            ->when($filtros['competencia_id'] ?? null, fn (Builder $q, int $id) => $q->whereHas('competencias', fn (Builder $c) => $c->where('competencia.id', $id)))
            ->when($filtros['q'] ?? null, fn (Builder $q, string $t) => $q->where(fn (Builder $w) => $w->whereLike('nombres', "%{$t}%")->orWhereLike('apellidos', "%{$t}%")))
            ->orderBy('apellidos')
            ->paginate(20)
            ->withQueryString();

        return EstudianteResource::collection($estudiantes);
    }

    public function show(Estudiante $estudiante): EstudianteResource
    {
        return new EstudianteResource($estudiante->load('usuario', 'programa', 'hojaVida.experiencias', 'competencias', 'proyectos'));
    }
}
