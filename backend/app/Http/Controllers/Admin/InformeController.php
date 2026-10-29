<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PeriodoAcademico;
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
        $nombre = 'informe-practicas-'.($periodo?->nombre ?? 'todos').'.xlsx';

        return response()->download($informes->exportar($periodo), $nombre)->deleteFileAfterSend();
    }

    private function periodo(Request $request): ?PeriodoAcademico
    {
        $id = $request->validate(['periodo_id' => ['nullable', 'integer', 'exists:periodo_academico,id']])['periodo_id'] ?? null;

        return $id ? PeriodoAcademico::find($id) : null;
    }
}
