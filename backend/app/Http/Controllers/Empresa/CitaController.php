<?php

namespace App\Http\Controllers\Empresa;

use App\Http\Controllers\Controller;
use App\Http\Requests\Empresa\AsignarCitaRequest;
use App\Http\Resources\CitaResource;
use App\Models\Cita;
use App\Models\Estudiante;
use App\Services\CitaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Citas de la empresa con los estudiantes: asignarlas, cancelarlas y, una vez
 * realizadas, seleccionar o descartar.
 */
class CitaController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $citas = $request->user()->empresa->citas()
            ->with('vacante', 'estudiante.programa', 'estudiante.hojaVida.referencias')
            ->latest('fecha_hora')
            ->get();

        return CitaResource::collection($citas);
    }

    public function store(AsignarCitaRequest $request, CitaService $citas): JsonResponse
    {
        $cita = $citas->asignar(
            $request->user()->empresa,
            Estudiante::findOrFail($request->validated('estudiante_id')),
            $request->datosCita(),
        );

        return (new CitaResource($cita->load('vacante', 'estudiante.programa')))->response()->setStatusCode(201);
    }

    public function cancelar(Request $request, int $cita, CitaService $citas): CitaResource
    {
        $motivo = $request->validate(['motivo' => ['nullable', 'string', 'max:500']])['motivo'] ?? null;

        return new CitaResource($citas->cancelar($this->propia($request, $cita), $motivo));
    }

    /** Después de la cita: selecciona al estudiante (pasa al docente) o lo descarta. */
    public function decidir(Request $request, int $cita, CitaService $citas): CitaResource
    {
        $datos = $request->validate([
            'seleccionar' => ['required', 'boolean'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ]);

        return new CitaResource($citas->decidir($this->propia($request, $cita), $request->boolean('seleccionar'), $datos['observacion'] ?? null));
    }

    private function propia(Request $request, int $id): Cita
    {
        return $request->user()->empresa->citas()->findOrFail($id);
    }
}
