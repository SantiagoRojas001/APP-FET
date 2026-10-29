<?php

namespace App\Http\Controllers;

use App\Enums\Rol;
use App\Http\Requests\Reporte\CrearReporteRequest;
use App\Http\Resources\ReporteResource;
use App\Models\Usuario;
use App\Models\Vinculacion;
use App\Notifications\ReporteRecibido;
use App\Services\Auditoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

/**
 * Cualquier usuario reporta un problema y sigue su respuesta. Si el problema
 * es de una práctica, el reporte queda ligado a esa vinculación.
 */
class ReporteController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $reportes = $request->user()->reportes()
            ->with('vinculacion.estudiante', 'vinculacion.empresa')
            ->latest('creado_en')
            ->get();

        return ReporteResource::collection($reportes);
    }

    public function store(CrearReporteRequest $request): JsonResponse
    {
        if ($id = $request->validated('vinculacion_id')) {
            Gate::authorize('view', Vinculacion::findOrFail($id));
        }

        $reporte = $request->user()->reportes()->create($request->validated())
            ->refresh()
            ->load('autor', 'vinculacion.estudiante', 'vinculacion.empresa');

        Auditoria::registrar('reporte.creado', $reporte, ['vinculacion_id' => $reporte->vinculacion_id]);
        Notification::send(Usuario::where('rol', Rol::Admin)->where('activo', true)->get(), new ReporteRecibido($reporte));

        return (new ReporteResource($reporte))->response()->setStatusCode(201);
    }

    /** Reportes ligados a una práctica, para el docente que la sigue. */
    public function deVinculacion(Vinculacion $vinculacion): AnonymousResourceCollection
    {
        Gate::authorize('verReportes', $vinculacion);

        return ReporteResource::collection($vinculacion->reportes()->with('autor')->get());
    }
}
