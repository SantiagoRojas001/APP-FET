<?php

namespace App\Http\Controllers\Empresa;

use App\Http\Controllers\Controller;
use App\Http\Requests\Empresa\GuardarVacanteRequest;
use App\Http\Resources\VacanteResource;
use App\Models\Vacante;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Vacantes de la empresa autenticada. Cada consulta se limita a sus propias
 * vacantes, así que una empresa no puede ver ni editar las de otra.
 */
class VacanteController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $vacantes = $request->user()->empresa->vacantes()
            ->with('postulaciones')
            ->latest('publicada_en')
            ->get();

        return VacanteResource::collection($vacantes);
    }

    public function store(GuardarVacanteRequest $request): JsonResponse
    {
        $vacante = $request->user()->empresa->vacantes()->create($request->validated());

        return (new VacanteResource($vacante->refresh()))->response()->setStatusCode(201);
    }

    public function update(GuardarVacanteRequest $request, int $vacante): VacanteResource
    {
        $vacante = $this->propia($request, $vacante);
        $vacante->update($request->validated());

        return new VacanteResource($vacante);
    }

    private function propia(Request $request, int $id): Vacante
    {
        return $request->user()->empresa->vacantes()->findOrFail($id);
    }
}
