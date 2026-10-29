<?php

namespace App\Http\Controllers\Docente;

use App\Enums\EstadoHojaVida;
use App\Http\Controllers\Controller;
use App\Http\Resources\EstudianteResource;
use App\Models\Estudiante;
use App\Models\HojaVida;
use App\Services\HojaVidaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Revisión docente de las hojas de vida: solo las aprobadas las ven las
 * empresas; las demás vuelven al estudiante con recomendaciones.
 */
class HojaVidaController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate(['estado' => ['nullable', Rule::enum(EstadoHojaVida::class)]]);
        $estado = $filtros['estado'] ?? EstadoHojaVida::EnRevision->value;

        $estudiantes = Estudiante::query()
            ->with('usuario', 'programa', 'competencias', 'proyectos', 'hojaVida.referencias')
            ->whereHas('hojaVida', fn (Builder $h) => $h->where('estado', $estado))
            ->orderBy(HojaVida::query()->select('enviada_en')->whereColumn('hoja_vida.estudiante_id', 'estudiante.id'))
            ->get();

        return EstudianteResource::collection($estudiantes);
    }

    public function update(Request $request, Estudiante $estudiante, HojaVidaService $hojaVida): EstudianteResource
    {
        $datos = $request->validate([
            'aprobar' => ['required', 'boolean'],
            'observaciones' => ['nullable', 'string', 'max:2000', 'required_if_declined:aprobar'],
        ], ['observaciones.required_if_declined' => 'Escribe las recomendaciones para que el estudiante sepa qué corregir.']);

        $hojaVida->revisar($estudiante, $request->user(), $request->boolean('aprobar'), $datos['observaciones'] ?? null);

        return new EstudianteResource($estudiante->load('usuario', 'programa', 'hojaVida.referencias'));
    }
}
