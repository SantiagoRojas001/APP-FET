<?php

namespace App\Http\Controllers\Empresa;

use App\Http\Controllers\Controller;
use App\Http\Resources\VinculacionResource;
use App\Models\Vinculacion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Practicantes de la empresa autenticada.
 */
class VinculacionController extends Controller
{
    /** La empresa registra quién acompaña al practicante en su organización. */
    public function tutor(Request $request, Vinculacion $vinculacion): VinculacionResource
    {
        Gate::authorize('revisarEvidencias', $vinculacion);

        $vinculacion->update(['tutor_empresa' => $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'cargo' => ['required', 'string', 'max:120'],
            'correo' => ['required', 'email', 'max:150'],
            'telefono' => ['required', 'string', 'max:20'],
        ])]);

        return new VinculacionResource($vinculacion->cargarSeguimiento());
    }

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
