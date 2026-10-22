<?php

namespace App\Http\Controllers\Empresa;

use App\Http\Controllers\Controller;
use App\Http\Resources\VinculacionResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Practicantes y contratados de la empresa autenticada.
 */
class VinculacionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $vinculaciones = $request->user()->empresa->vinculaciones()
            ->with('estudiante.programa', 'estudiante.proyectos', 'vacante', 'tutor', 'documentos')
            ->latest('fecha_inicio')
            ->get();

        return VinculacionResource::collection($vinculaciones);
    }
}
