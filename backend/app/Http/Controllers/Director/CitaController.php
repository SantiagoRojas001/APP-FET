<?php

namespace App\Http\Controllers\Director;

use App\Enums\EstadoCita;
use App\Http\Controllers\Controller;
use App\Http\Resources\CitaResource;
use App\Models\Cita;
use App\Services\CitaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Cartas de presentación: estudiantes del programa que el docente aprobó y
 * las cartas ya enviadas.
 */
class CitaController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $citas = $this->delPrograma($request)
            ->with('empresa', 'vacante', 'autorizador', 'director', 'estudiante.programa', 'estudiante.hojaVida.referencias')
            ->whereIn('estado', [EstadoCita::Autorizado, EstadoCita::Presentado])
            ->orderByRaw("estado = 'AUTORIZADO' desc")
            ->latest('autorizada_en')
            ->get();

        return CitaResource::collection($citas);
    }

    /** Envía la carta de presentación a la empresa: con ella empieza la práctica. */
    public function presentar(Request $request, int $cita, CitaService $citas): CitaResource
    {
        $cita = $citas->presentar($this->delPrograma($request)->findOrFail($cita), $request->user());

        return new CitaResource($cita->load('empresa', 'vacante', 'autorizador', 'director', 'estudiante.programa'));
    }

    /** @return Builder<Cita> */
    private function delPrograma(Request $request): Builder
    {
        return Cita::query()->whereHas('estudiante', fn (Builder $e) => $e->where('programa_id', $request->user()->programa_id));
    }
}
