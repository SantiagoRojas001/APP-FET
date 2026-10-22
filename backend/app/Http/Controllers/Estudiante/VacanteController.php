<?php

namespace App\Http\Controllers\Estudiante;

use App\Enums\EstadoVinculacion;
use App\Enums\Modalidad;
use App\Http\Controllers\Controller;
use App\Http\Resources\VacanteResource;
use App\Models\Vacante;
use App\Services\ElegibilidadService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Vacantes publicadas, marcadas según si el estudiante puede postularse.
 */
class VacanteController extends Controller
{
    public function index(Request $request, ElegibilidadService $elegibilidad): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'modalidad' => ['nullable', Rule::enum(Modalidad::class)],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $estudiante = $request->user()->estudiante()->firstOrFail();
        $misPostulaciones = $estudiante->postulaciones()->get()->keyBy('vacante_id');
        $tieneVinculacionActiva = $estudiante->vinculaciones()->where('estado', EstadoVinculacion::Activa)->exists();

        $vacantes = Vacante::query()
            ->publicadas()
            ->with('empresa')
            ->when($filtros['modalidad'] ?? null, fn (Builder $q, string $m) => $q->where('modalidad', $m))
            ->when($filtros['q'] ?? null, function (Builder $q, string $texto) {
                $q->where(fn (Builder $w) => $w
                    ->whereLike('titulo', "%{$texto}%")
                    ->orWhereHas('empresa', fn (Builder $e) => $e->whereLike('razon_social', "%{$texto}%")));
            })
            ->latest('publicada_en')
            ->get();

        $vacantes->each(function (Vacante $vacante) use ($estudiante, $elegibilidad, $misPostulaciones, $tieneVinculacionActiva) {
            $motivo = $tieneVinculacionActiva
                ? 'Ya tienes una vinculación activa.'
                : $elegibilidad->motivoParaNoPostular($estudiante, $vacante);
            $postulacion = $misPostulaciones->get($vacante->id);

            $vacante->setAttribute('puede_postularse', $motivo === null && $postulacion === null);
            $vacante->setAttribute('motivo_no_disponible', $motivo);
            $vacante->setAttribute('mi_postulacion', $postulacion
                ? ['id' => $postulacion->id, 'estado' => ['valor' => $postulacion->estado->value, 'etiqueta' => $postulacion->estado->label()]]
                : null);
        });

        return VacanteResource::collection($vacantes);
    }
}
