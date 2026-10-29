<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CategoriaReporte;
use App\Enums\EstadoReporte;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AtenderReporteRequest;
use App\Http\Resources\ReporteResource;
use App\Models\Reporte;
use App\Notifications\ReporteAtendido;
use App\Services\Auditoria;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * La administración atiende los reportes de todos los usuarios.
 */
class ReporteController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'estado' => ['nullable', Rule::enum(EstadoReporte::class)],
            'categoria' => ['nullable', Rule::enum(CategoriaReporte::class)],
        ]);

        $reportes = Reporte::query()
            ->with('autor', 'vinculacion.estudiante', 'vinculacion.empresa')
            ->when($filtros['estado'] ?? null, fn ($q, string $estado) => $q->where('estado', $estado))
            ->when($filtros['categoria'] ?? null, fn ($q, string $categoria) => $q->where('categoria', $categoria))
            ->orderByRaw("estado = 'RESUELTO'")
            ->latest('creado_en')
            ->paginate(25)
            ->withQueryString();

        return ReporteResource::collection($reportes);
    }

    public function update(AtenderReporteRequest $request, Reporte $reporte): ReporteResource
    {
        $reporte->update([
            ...$request->validated(),
            'atendido_por' => $request->user()->id,
            'atendido_en' => now(),
        ]);
        $reporte->load('autor', 'vinculacion.estudiante', 'vinculacion.empresa');

        Auditoria::registrar('reporte.atendido', $reporte, ['estado' => $reporte->estado->value]);
        $reporte->autor->notify(new ReporteAtendido($reporte));

        return new ReporteResource($reporte);
    }
}
