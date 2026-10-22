<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GuardarReglaRequest;
use App\Http\Resources\ReglaElegibilidadResource;
use App\Models\ReglaElegibilidad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Las reglas no se editan: se crea una nueva y la anterior queda cerrada el
 * día antes, para conservar el historial con el que se evaluó a cada estudiante.
 */
class ReglaElegibilidadController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $reglas = ReglaElegibilidad::query()
            ->with('programa')
            ->orderBy('programa_id')
            ->orderByDesc('vigente_desde')
            ->get();

        return ReglaElegibilidadResource::collection($reglas);
    }

    public function store(GuardarReglaRequest $request): JsonResponse
    {
        $desde = Carbon::parse($request->validated('vigente_desde'))->startOfDay();

        $regla = DB::transaction(function () use ($request, $desde) {
            ReglaElegibilidad::query()
                ->where('programa_id', $request->validated('programa_id'))
                ->where('vigente_desde', '<', $desde)
                ->where(fn ($q) => $q->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>=', $desde))
                ->update(['vigente_hasta' => $desde->copy()->subDay()->toDateString()]);

            return ReglaElegibilidad::create([...$request->validated(), 'vigente_desde' => $desde->toDateString()]);
        });

        return (new ReglaElegibilidadResource($regla->load('programa')))->response()->setStatusCode(201);
    }
}
