<?php

namespace App\Http\Controllers\Estudiante;

use App\Http\Controllers\Controller;
use App\Http\Resources\CitaResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Empresas interesadas: las citas que le asignaron al estudiante.
 */
class CitaController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $citas = $request->user()->estudiante()->firstOrFail()
            ->citas()
            ->with('empresa', 'vacante')
            ->get();

        return CitaResource::collection($citas);
    }
}
