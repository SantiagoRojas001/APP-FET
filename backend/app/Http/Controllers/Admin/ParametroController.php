<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Ciclo;
use App\Http\Controllers\Controller;
use App\Models\Parametro;
use App\Services\Auditoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Parámetros generales, iguales para todos los programas: promedio mínimo,
 * semestres en los que se registran los estudiantes para pasantía o práctica
 * y horas mínimas de la práctica.
 */
class ParametroController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json($this->valores());
    }

    public function update(Request $request): JsonResponse
    {
        $request->merge(['promedio_minimo' => str_replace(',', '.', (string) $request->input('promedio_minimo'))]);
        $datos = $request->validate([
            'promedio_minimo' => ['required', 'numeric', 'min:0', 'max:5', 'decimal:0,2'],
            'semestres_habilitados' => ['required', 'array', 'min:1'],
            'semestres_habilitados.*' => ['integer', 'min:1', 'max:10', 'distinct'],
            'horas' => ['required', 'integer', 'min:40', 'max:2000'],
        ]);

        $semestres = collect($datos['semestres_habilitados'])->map(fn ($s) => (int) $s)->sort()->values();
        Parametro::guardar('promedio_minimo', (string) round((float) $datos['promedio_minimo'], 2));
        Parametro::guardar('semestres_habilitados', $semestres->implode(','));
        Parametro::guardar('horas_practica', (string) $datos['horas']);
        Auditoria::registrar('parametros.actualizados', $request->user(), $this->valores());

        return response()->json($this->valores());
    }

    /** @return array{promedio_minimo: float, semestres_habilitados: list<int>, horas: int, ciclos: list<array<string, mixed>>} */
    private function valores(): array
    {
        return [
            'promedio_minimo' => Parametro::promedioMinimo(),
            'semestres_habilitados' => Parametro::semestresHabilitados(),
            'horas' => Parametro::horasRequeridas(),
            'ciclos' => array_map(fn (Ciclo $c) => [
                'valor' => $c->value,
                'etiqueta' => $c->label(),
                'semestres' => range(...$c->semestres()),
            ], Ciclo::cases()),
        ];
    }
}
