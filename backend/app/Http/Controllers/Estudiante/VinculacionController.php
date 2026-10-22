<?php

namespace App\Http\Controllers\Estudiante;

use App\Http\Controllers\Controller;
use App\Http\Resources\VinculacionResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VinculacionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $vinculaciones = $request->user()->estudiante()->firstOrFail()
            ->vinculaciones()
            ->with('empresa', 'vacante', 'tutor', 'documentos')
            ->latest('fecha_inicio')
            ->get();

        return VinculacionResource::collection($vinculaciones);
    }
}
