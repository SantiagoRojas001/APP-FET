<?php

namespace App\Http\Controllers;

use App\Exceptions\ReglaNegocioException;
use App\Models\Estudiante;
use App\Services\HojaVidaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Fotos de la cédula del estudiante. Solo él las sube, y solo él y las
 * empresas que pueden ver su hoja de vida las consultan.
 */
class CedulaEstudianteController extends Controller
{
    private const COLUMNAS = ['frente' => 'cedula_frente_url', 'reverso' => 'cedula_reverso_url'];

    public function store(Request $request, HojaVidaService $hojasVida): JsonResponse
    {
        $datos = $request->validate([
            'lado' => ['required', 'in:frente,reverso'],
            'archivo' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:'.config('fet.hoja_vida.cedula_max_kb'), 'dimensions:min_width=400,min_height=250'],
        ], ['archivo.dimensions' => 'La foto debe medir al menos 400 × 250 píxeles.']);

        $estudiante = $request->user()->estudiante()->firstOrFail();
        if (! $estudiante->hojaVidaHabilitada()) {
            throw new ReglaNegocioException('Tu hoja de vida está deshabilitada mientras cursas tu práctica.');
        }

        $hojasVida->antesDeEditar($estudiante);
        $hoja = $estudiante->hojaVida()->firstOrCreate();
        $columna = self::COLUMNAS[$datos['lado']];

        if ($hoja->{$columna}) {
            Storage::disk('local')->delete($hoja->{$columna});
        }

        $hoja->update([
            $columna => $request->file('archivo')->store('cedulas', 'local'),
            'actualizado_en' => now(),
        ]);

        return response()->json(['url' => "/api/estudiantes/{$estudiante->id}/cedula/{$datos['lado']}"]);
    }

    public function show(Estudiante $estudiante, string $lado): StreamedResponse
    {
        Gate::authorize('verCedula', $estudiante);

        $ruta = $estudiante->hojaVida()->value(self::COLUMNAS[$lado]);
        abort_unless($ruta && Storage::disk('local')->exists($ruta), 404, 'El estudiante no ha subido esta foto.');

        return Storage::disk('local')->response($ruta, null, ['Cache-Control' => 'private, max-age=300']);
    }
}
