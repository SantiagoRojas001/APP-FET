<?php

namespace App\Http\Controllers;

use App\Exceptions\ReglaNegocioException;
use App\Models\Estudiante;
use App\Services\FirmaService;
use App\Services\HojaVidaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Firma de la hoja de vida, dibujada por el estudiante en la app (ver
 * FirmaService). Va en el PDF del formato institucional.
 */
class FirmaEstudianteController extends Controller
{
    public function store(Request $request, FirmaService $firmas, HojaVidaService $hojasVida): JsonResponse
    {
        $request->validate(['firma' => ['required', 'file', 'mimes:png', 'max:300', 'dimensions:min_width=300,max_width=2400,min_height=100,max_height=1200']]);

        $estudiante = $request->user()->estudiante()->firstOrFail();
        if (! $estudiante->hojaVidaHabilitada()) {
            throw new ReglaNegocioException('Tu hoja de vida está deshabilitada mientras cursas tu práctica.');
        }

        $png = $firmas->validar($request->file('firma'));
        $hojasVida->antesDeEditar($estudiante);
        $hojaVida = $estudiante->hojaVida()->firstOrCreate();

        if ($hojaVida->firma_url) {
            Storage::disk('local')->delete($hojaVida->firma_url);
        }
        $ruta = 'firmas/'.Str::uuid().'.png';
        Storage::disk('local')->put($ruta, $png);
        $hojaVida->update(['firma_url' => $ruta, 'actualizado_en' => now()]);

        return response()->json(['url_firma' => "/api/estudiantes/{$estudiante->id}/firma"]);
    }

    public function show(Estudiante $estudiante): StreamedResponse
    {
        Gate::authorize('view', $estudiante);

        $ruta = $estudiante->hojaVida()->value('firma_url');
        abort_unless($ruta && Storage::disk('local')->exists($ruta), 404, 'El estudiante no ha registrado su firma.');

        return Storage::disk('local')->response($ruta, null, ['Cache-Control' => 'private, max-age=300']);
    }
}
