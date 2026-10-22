<?php

namespace App\Http\Controllers\Empresa;

use App\Enums\EstadoPostulacion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Empresa\DecidirPostulacionRequest;
use App\Http\Resources\PostulacionResource;
use App\Models\Postulacion;
use App\Services\PostulacionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * La empresa solo recibe postulaciones a sus vacantes que un docente ya aprobó.
 */
class PostulacionController extends Controller
{
    private const VISIBLES = [EstadoPostulacion::Aprobada, EstadoPostulacion::Aceptada, EstadoPostulacion::Descartada];

    public function index(Request $request): AnonymousResourceCollection
    {
        $postulaciones = $this->propias($request)
            ->with('vacante', 'estudiante.usuario', 'estudiante.programa', 'estudiante.hojaVida.experiencias', 'estudiante.competencias', 'estudiante.proyectos')
            ->latest('fecha')
            ->get();

        return PostulacionResource::collection($postulaciones);
    }

    public function update(DecidirPostulacionRequest $request, int $postulacion, PostulacionService $servicio): PostulacionResource
    {
        $postulacion = $this->propias($request)->findOrFail($postulacion);

        $postulacion = $servicio->decidirComoEmpresa(
            $postulacion,
            $request->acepta(),
            $request->date('fecha_inicio'),
        );

        return new PostulacionResource($postulacion->load('vacante', 'estudiante.programa'));
    }

    /** @return Builder<Postulacion> */
    private function propias(Request $request): Builder
    {
        return Postulacion::query()
            ->whereIn('estado', self::VISIBLES)
            ->whereHas('vacante', fn (Builder $q) => $q->where('empresa_id', $request->user()->empresa->id));
    }
}
