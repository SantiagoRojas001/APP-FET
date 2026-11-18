<?php

namespace App\Http\Controllers\Director;

use App\Http\Controllers\Controller;
use App\Services\Auditoria;
use App\Services\CupoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cupos de pasantía del programa del director en el periodo actual.
 */
class CupoController extends Controller
{
    public function show(Request $request, CupoService $cupos): JsonResponse
    {
        return response()->json($cupos->resumen($request->user()->programa_id));
    }

    public function update(Request $request, CupoService $cupos): JsonResponse
    {
        $datos = $request->validate([
            'tecnico' => ['required', 'integer', 'min:0', 'max:500'],
            'tecnologo' => ['required', 'integer', 'min:0', 'max:500'],
        ]);

        $programa = $request->user()->programa_id;
        $cupos->actualizar($programa, $datos['tecnico'], $datos['tecnologo']);
        Auditoria::registrar('cupos.actualizados', $request->user(), $datos);

        return response()->json($cupos->resumen($programa));
    }
}
