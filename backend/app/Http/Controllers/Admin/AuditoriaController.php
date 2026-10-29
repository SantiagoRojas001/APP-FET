<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditoriaResource;
use App\Models\Auditoria;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Consulta de la bitácora de auditoría.
 */
class AuditoriaController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'entidad' => ['nullable', 'string', 'max:40'],
            'entidad_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
        ]);

        $registros = Auditoria::query()
            ->with('usuario')
            ->when($filtros['entidad'] ?? null, fn ($q, string $e) => $q->where('entidad', $e))
            ->when($filtros['entidad_id'] ?? null, fn ($q, int $id) => $q->where('entidad_id', $id))
            ->when($filtros['q'] ?? null, fn ($q, string $t) => $q->where(fn ($w) => $w
                ->whereLike('accion', "%{$t}%")
                ->orWhereHas('usuario', fn ($u) => $u->whereLike('correo', "%{$t}%"))))
            ->when($filtros['desde'] ?? null, fn ($q, string $d) => $q->whereDate('creado_en', '>=', $d))
            ->when($filtros['hasta'] ?? null, fn ($q, string $h) => $q->whereDate('creado_en', '<=', $h))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return AuditoriaResource::collection($registros);
    }
}
