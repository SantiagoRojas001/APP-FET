<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Parametro;
use App\Services\Auditoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Parámetros de habilitación: promedio mínimo y semestres en los que se
 * registran los estudiantes para pasantía o práctica.
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
            'semestres_habilitados.*' => ['integer', 'min:1', 'max:12', 'distinct'],
        ]);

        $semestres = collect($datos['semestres_habilitados'])->map(fn ($s) => (int) $s)->sort()->values();
        Parametro::guardar('promedio_minimo', (string) round((float) $datos['promedio_minimo'], 2));
        Parametro::guardar('semestres_habilitados', $semestres->implode(','));
        Auditoria::registrar('parametros.actualizados', $request->user(), $this->valores());

        return response()->json($this->valores());
    }

    /** @return array{promedio_minimo: float, semestres_habilitados: list<int>} */
    private function valores(): array
    {
        return ['promedio_minimo' => Parametro::promedioMinimo(), 'semestres_habilitados' => Parametro::semestresHabilitados()];
    }
}
