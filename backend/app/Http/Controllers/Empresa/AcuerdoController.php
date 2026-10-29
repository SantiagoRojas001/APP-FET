<?php

namespace App\Http\Controllers\Empresa;

use App\Http\Controllers\Controller;
use App\Http\Resources\AcuerdoResource;
use App\Services\AcuerdoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Acuerdos (convenios) de la empresa con la FET. Sin uno aprobado y vigente
 * no puede asignar citas.
 */
class AcuerdoController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return AcuerdoResource::collection($request->user()->empresa->acuerdos);
    }

    public function store(Request $request, AcuerdoService $acuerdos): JsonResponse
    {
        $datos = $request->validate([
            'vigencia_inicio' => ['required', 'date'],
            'vigencia_fin' => ['required', 'date', 'after:vigencia_inicio', 'after_or_equal:today'],
            'archivo' => ['required', 'file', 'mimes:pdf', 'max:'.config('fet.documentos.max_kb')],
        ]);

        $acuerdo = $acuerdos->subir($request->user()->empresa, collect($datos)->except('archivo')->all(), $request->file('archivo'));

        return (new AcuerdoResource($acuerdo->refresh()))->response()->setStatusCode(201);
    }
}
