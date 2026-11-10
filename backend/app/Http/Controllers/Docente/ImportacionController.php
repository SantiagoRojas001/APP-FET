<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Services\ImportacionEstudiantesService;
use App\Services\RegistroEstudianteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Registro de estudiantes con el listado de modalidades de grado: primero la
 * vista previa (no guarda nada) y luego la confirmación, que crea las cuentas
 * y avisa por correo a quienes no quedan habilitados.
 */
class ImportacionController extends Controller
{
    public function vistaPrevia(Request $request, ImportacionEstudiantesService $importacion): JsonResponse
    {
        $request->validate(
            ['archivo' => ['required', 'file', 'mimes:pdf,xlsx,csv,txt', 'max:10240']],
            ['archivo.mimes' => 'Sube el listado en PDF, Excel (.xlsx) o CSV.'],
        );

        return response()->json($importacion->leer($request->file('archivo'), $request->user()));
    }

    public function confirmar(Request $request, ImportacionEstudiantesService $importacion, RegistroEstudianteService $registro): JsonResponse
    {
        $datos = $request->validate([
            'filas' => ['present', 'array', 'max:'.ImportacionEstudiantesService::MAX_FILAS],
            'filas.*' => ['array'],
            'no_habilitados' => ['sometimes', 'array', 'max:'.ImportacionEstudiantesService::MAX_FILAS],
            'no_habilitados.*' => ['array'],
        ]);
        abort_if(! $datos['filas'] && empty($datos['no_habilitados']), 422, 'Elige al menos un estudiante.');

        return response()->json($importacion->registrar(
            array_values($datos['filas']),
            array_values($datos['no_habilitados'] ?? []),
            $request->user(),
            $registro,
        ));
    }
}
