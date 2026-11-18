<?php

namespace App\Http\Controllers\Seguimiento;

use App\Enums\ParteCalificacion;
use App\Enums\Rol;
use App\Http\Controllers\Controller;
use App\Models\Calificacion;
use App\Models\Vinculacion;
use App\Services\CalificacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Evaluación cuantitativa de la práctica, por momentos. La empresa solo lee
 * lo que ella calificó.
 */
class CalificacionController extends Controller
{
    public function index(Request $request, Vinculacion $vinculacion, CalificacionService $calificaciones): JsonResponse
    {
        Gate::authorize('view', $vinculacion);

        $usuario = $request->user();
        $parte = $usuario->can('calificar', $vinculacion) ? ParteCalificacion::delRol($usuario->rol) : null;
        $registros = $vinculacion->calificaciones()
            ->when($usuario->rol === Rol::Empresa, fn ($q) => $q->where('parte', ParteCalificacion::Empresa))
            ->orderBy('momento')
            ->get();

        return response()->json([
            'parte' => $parte ? ['valor' => $parte->value, 'etiqueta' => $parte->label()] : null,
            'escala' => config('calificacion.escala'),
            'categorias' => $parte ? $calificaciones->categorias($parte) : [],
            'momentos' => array_map(fn (int $momento, int $peso) => [
                'momento' => $momento,
                'peso' => $peso,
                'habilitado' => $calificaciones->habilitado($vinculacion, $momento),
                'mia' => $parte !== null && $registros->contains(fn (Calificacion $c) => $c->momento === $momento && $c->parte === $parte),
                'registros' => $registros->where('momento', $momento)->values()->map(fn (Calificacion $c) => [
                    'parte' => ['valor' => $c->parte->value, 'etiqueta' => $c->parte->label()],
                    'puntajes' => $c->puntajes,
                    'observaciones' => $c->observaciones,
                    'creada_en' => $c->creada_en?->toIso8601String(),
                ]),
            ], array_keys(config('calificacion.momentos')), config('calificacion.momentos')),
        ]);
    }

    public function store(Request $request, Vinculacion $vinculacion, CalificacionService $calificaciones): JsonResponse
    {
        Gate::authorize('calificar', $vinculacion);

        $datos = $request->validate([
            'momento' => ['required', 'integer', Rule::in(array_keys(config('calificacion.momentos')))],
            'puntajes' => ['required', 'array'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ]);

        $calificaciones->registrar($vinculacion, $request->user(), (int) $datos['momento'], $datos['puntajes'], $datos['observaciones'] ?? null);

        return response()->json(['message' => 'Calificación registrada.'], 201);
    }
}
