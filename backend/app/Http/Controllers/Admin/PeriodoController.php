<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoVacante;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GuardarPeriodoRequest;
use App\Http\Resources\PeriodoResource;
use App\Models\PeriodoAcademico;
use App\Models\Vacante;
use App\Models\Vinculacion;
use App\Services\Auditoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * Periodos académicos. Las vinculaciones nuevas quedan en el periodo abierto
 * de su fecha de inicio; al cerrar un periodo se cierran las vacantes abiertas.
 */
class PeriodoController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return PeriodoResource::collection(PeriodoAcademico::withCount('vinculaciones')->orderByDesc('fecha_inicio')->get());
    }

    public function store(GuardarPeriodoRequest $request): JsonResponse
    {
        $periodo = DB::transaction(function () use ($request) {
            $periodo = PeriodoAcademico::create($request->validated());

            // Las prácticas que ya empezaron dentro de las fechas quedan en el periodo.
            Vinculacion::query()
                ->whereNull('periodo_id')
                ->whereBetween('fecha_inicio', [$periodo->fecha_inicio, $periodo->fecha_fin])
                ->update(['periodo_id' => $periodo->id]);

            return $periodo;
        });

        Auditoria::registrar('periodo.creado', $periodo, ['nombre' => $periodo->nombre]);

        return (new PeriodoResource($periodo->loadCount('vinculaciones')))->response()->setStatusCode(201);
    }

    public function cerrar(PeriodoAcademico $periodo): PeriodoResource
    {
        abort_if($periodo->estaCerrado(), 422, 'Este periodo ya está cerrado.');

        $vacantes = DB::transaction(function () use ($periodo) {
            $periodo->update(['cerrado_en' => now()]);

            return Vacante::where('estado', EstadoVacante::Abierta)->update(['estado' => EstadoVacante::Cerrada]);
        });

        Auditoria::registrar('periodo.cerrado', $periodo, ['vacantes_cerradas' => $vacantes]);

        return new PeriodoResource($periodo->loadCount('vinculaciones'));
    }
}
