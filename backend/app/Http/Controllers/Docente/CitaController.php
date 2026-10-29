<?php

namespace App\Http\Controllers\Docente;

use App\Enums\EstadoCita;
use App\Http\Controllers\Controller;
use App\Http\Requests\Docente\AutorizarCitaRequest;
use App\Http\Resources\CitaResource;
use App\Models\Cita;
use App\Services\CitaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Estudiantes que una empresa seleccionó después de la cita: el docente los
 * vuelve a aprobar (o no) antes de la carta de presentación.
 */
class CitaController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate(['estado' => ['nullable', Rule::enum(EstadoCita::class)]]);

        $citas = Cita::query()
            ->with('empresa', 'vacante', 'autorizador', 'estudiante.programa', 'estudiante.hojaVida.referencias', 'estudiante.competencias', 'estudiante.proyectos')
            ->where('estado', $filtros['estado'] ?? EstadoCita::Seleccionado->value)
            ->latest('decidida_en')
            ->get();

        return CitaResource::collection($citas);
    }

    public function update(AutorizarCitaRequest $request, Cita $cita, CitaService $citas): CitaResource
    {
        $cita = $citas->autorizar(
            $cita,
            $request->user(),
            $request->boolean('autorizar'),
            $request->validated('observacion'),
            $request->date('fecha_inicio'),
        );

        return new CitaResource($cita->load('empresa', 'estudiante.programa', 'autorizador'));
    }
}
