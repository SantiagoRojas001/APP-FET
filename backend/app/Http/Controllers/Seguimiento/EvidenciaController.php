<?php

namespace App\Http\Controllers\Seguimiento;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seguimiento\RegistrarEvidenciaRequest;
use App\Http\Requests\Seguimiento\RevisarEvidenciaRequest;
use App\Http\Resources\EvidenciaSemanalResource;
use App\Models\EvidenciaSemanal;
use App\Models\Vinculacion;
use App\Services\PracticaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Horas semanales con su evidencia: el estudiante las reporta y corrige, la
 * empresa las aprueba o las devuelve con un comentario.
 */
class EvidenciaController extends Controller
{
    public function index(Vinculacion $vinculacion): AnonymousResourceCollection
    {
        Gate::authorize('view', $vinculacion);

        return EvidenciaSemanalResource::collection($vinculacion->evidencias);
    }

    public function store(RegistrarEvidenciaRequest $request, Vinculacion $vinculacion, PracticaService $practica): JsonResponse
    {
        Gate::authorize('registrarEvidencia', $vinculacion);

        $evidencia = $practica->registrarEvidencia($vinculacion, $request->safe()->except('archivo'), $request->file('archivo'));

        return (new EvidenciaSemanalResource($evidencia->refresh()))->response()->setStatusCode(201);
    }

    /** Corrección de una semana devuelta (POST, porque puede llevar archivo). */
    public function corregir(RegistrarEvidenciaRequest $request, EvidenciaSemanal $evidencia, PracticaService $practica): EvidenciaSemanalResource
    {
        Gate::authorize('registrarEvidencia', $evidencia->vinculacion);

        return new EvidenciaSemanalResource($practica->corregirEvidencia($evidencia, $request->safe()->except('archivo'), $request->file('archivo')));
    }

    public function revisar(RevisarEvidenciaRequest $request, EvidenciaSemanal $evidencia, PracticaService $practica): EvidenciaSemanalResource
    {
        Gate::authorize('revisarEvidencias', $evidencia->vinculacion);

        return new EvidenciaSemanalResource(
            $practica->revisarEvidencia($evidencia, $request->user(), $request->aprueba(), $request->validated('comentario')),
        );
    }

    /** Aprueba de una vez todas las semanas pendientes del practicante. */
    public function aprobarPendientes(Request $request, Vinculacion $vinculacion, PracticaService $practica): JsonResponse
    {
        Gate::authorize('revisarEvidencias', $vinculacion);

        $aprobadas = $practica->aprobarPendientes($vinculacion, $request->user());

        return response()->json([
            'aprobadas' => $aprobadas,
            'message' => $aprobadas ? "Aprobaste {$aprobadas} semana(s)." : 'No hay semanas pendientes.',
        ]);
    }

    public function descargar(EvidenciaSemanal $evidencia): StreamedResponse
    {
        Gate::authorize('view', $evidencia->vinculacion);

        abort_unless($evidencia->archivo_url && Storage::disk('local')->exists($evidencia->archivo_url), 404, 'El archivo ya no está disponible.');

        $extension = pathinfo($evidencia->archivo_url, PATHINFO_EXTENSION);

        return Storage::disk('local')->download($evidencia->archivo_url, "evidencia_{$evidencia->semana_inicio->format('Y-m-d')}.{$extension}");
    }
}
