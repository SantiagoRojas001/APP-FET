<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\InformeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Informes de prácticas para acreditación y registro calificado.
 */
class InformeController extends Controller
{
    public function show(Request $request, InformeService $informes): JsonResponse
    {
        return response()->json($informes->resumen($this->periodo($request)));
    }

    public function exportar(Request $request, InformeService $informes): BinaryFileResponse
    {
        $periodo = $this->periodo($request);
        $nombre = 'informe-practicas-'.($periodo ?? 'todos').'.xlsx';

        return response()->download($informes->exportar($periodo), $nombre)->deleteFileAfterSend();
    }

    /** Periodo calculado (2026-1, 2026-2); sin periodo, todos. */
    private function periodo(Request $request): ?string
    {
        return $request->validate(['periodo' => ['nullable', 'string', 'regex:/^20\d{2}-[12]$/']])['periodo'] ?? null;
    }
}
