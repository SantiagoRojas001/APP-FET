<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Services\ImportacionEstudiantesService;
use App\Services\RegistroEstudianteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Registro de estudiantes con el listado de modalidades de grado: primero la
 * vista previa (no guarda nada) y luego la confirmación de las filas.
 */
class ImportacionController extends Controller
{
    public function vistaPrevia(Request $request, ImportacionEstudiantesService $importacion): JsonResponse
    {
        $request->validate(
            ['archivo' => ['required', 'file', 'mimes:pdf,xlsx,csv,txt', 'max:10240']],
            ['archivo.mimes' => 'Sube el listado en PDF, Excel (.xlsx) o CSV.'],
        );

        return response()->json($importacion->leer($request->file('archivo')));
    }

    public function confirmar(Request $request, ImportacionEstudiantesService $importacion, RegistroEstudianteService $registro): JsonResponse
    {
        $datos = $request->validate([
            'filas' => ['required', 'array', 'min:1', 'max:'.ImportacionEstudiantesService::MAX_FILAS],
            'filas.*' => ['array'],
        ]);

        return response()->json($importacion->registrar(array_values($datos['filas']), $request->user(), $registro));
    }
}
