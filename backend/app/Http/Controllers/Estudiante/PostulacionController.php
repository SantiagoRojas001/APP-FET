<?php

namespace App\Http\Controllers\Estudiante;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostulacionResource;
use App\Models\Vacante;
use App\Services\PostulacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PostulacionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $postulaciones = $request->user()->estudiante()->firstOrFail()
            ->postulaciones()
            ->with('vacante.empresa')
            ->latest('fecha')
            ->get();

        return PostulacionResource::collection($postulaciones);
    }

    public function store(Request $request, Vacante $vacante, PostulacionService $servicio): JsonResponse
    {
        $postulacion = $servicio->postular($request->user()->estudiante()->firstOrFail(), $vacante);

        return (new PostulacionResource($postulacion->load('vacante.empresa')))
            ->response()
            ->setStatusCode(201);
    }
}
