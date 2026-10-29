<?php

namespace App\Http\Controllers\Empresa;

use App\Http\Controllers\Controller;
use App\Http\Resources\VinculacionResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Practicantes de la empresa autenticada.
 */
class VinculacionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $vinculaciones = $request->user()->empresa->vinculaciones()
            ->conSeguimiento()
            ->with('estudiante.proyectos')
            ->latest('fecha_inicio')
            ->get();

        return VinculacionResource::collection($vinculaciones);
    }
}
