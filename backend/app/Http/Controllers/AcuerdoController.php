<?php

namespace App\Http\Controllers;

use App\Enums\EstadoAcuerdo;
use App\Enums\Rol;
use App\Http\Requests\Admin\RevisarAcuerdoRequest;
use App\Http\Resources\AcuerdoResource;
use App\Models\Acuerdo;
use App\Services\AcuerdoService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Acuerdos de todas las empresas: la administración los aprueba o rechaza y
 * el director de programa los consulta (sin aprobarlos).
 */
class AcuerdoController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate(['estado' => ['nullable', Rule::enum(EstadoAcuerdo::class)]]);

        $acuerdos = Acuerdo::query()
            ->with('empresa')
            ->when($filtros['estado'] ?? null, fn ($q, string $estado) => $q->where('estado', $estado))
            ->orderByRaw("estado = 'PENDIENTE' desc")
            ->latest('cargado_en')
            ->get();

        return AcuerdoResource::collection($acuerdos);
    }

    public function revisar(RevisarAcuerdoRequest $request, Acuerdo $acuerdo, AcuerdoService $acuerdos): AcuerdoResource
    {
        $acuerdo = $acuerdos->revisar($acuerdo, $request->user(), $request->boolean('aprobar'), $request->validated('observacion'));

        return new AcuerdoResource($acuerdo->load('empresa'));
    }

    /** El PDF lo descargan la administración, los directores y la propia empresa. */
    public function archivo(Request $request, Acuerdo $acuerdo): StreamedResponse
    {
        $usuario = $request->user();
        abort_unless(
            $usuario->tieneRol(Rol::Admin, Rol::Director) || $usuario->loadMissing('empresa')->empresa?->id === $acuerdo->empresa_id,
            403,
        );
        abort_unless(Storage::disk('local')->exists($acuerdo->archivo_url), 404, 'El archivo ya no está disponible.');

        return Storage::disk('local')->download($acuerdo->archivo_url, "acuerdo_{$acuerdo->vigencia_inicio->format('Y-m-d')}.pdf");
    }
}
