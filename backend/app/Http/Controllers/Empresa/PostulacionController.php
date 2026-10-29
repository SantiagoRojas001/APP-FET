<?php

namespace App\Http\Controllers\Empresa;

use App\Http\Controllers\Controller;
use App\Http\Requests\Empresa\DecidirPostulacionRequest;
use App\Http\Resources\PostulacionResource;
use App\Models\Postulacion;
use App\Services\PostulacionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Las hojas de vida de quienes se postulan a sus vacantes llegan directo a la
 * empresa, que selecciona o descarta. La selección pasa a autorización docente.
 */
class PostulacionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $postulaciones = $this->propias($request)
            ->with('vacante', 'estudiante.usuario', 'estudiante.programa', 'estudiante.hojaVida.referencias', 'estudiante.competencias', 'estudiante.proyectos')
            ->latest('fecha')
            ->get();

        return PostulacionResource::collection($postulaciones);
    }

    public function update(DecidirPostulacionRequest $request, int $postulacion, PostulacionService $servicio): PostulacionResource
    {
        $postulacion = $this->propias($request)->findOrFail($postulacion);

        $postulacion = $servicio->decidirComoEmpresa($postulacion, $request->selecciona());

        return new PostulacionResource($postulacion->load('vacante', 'estudiante.programa'));
    }

    /** @return Builder<Postulacion> */
    private function propias(Request $request): Builder
    {
        return Postulacion::query()
            ->whereHas('vacante', fn (Builder $q) => $q->where('empresa_id', $request->user()->empresa->id));
    }
}
