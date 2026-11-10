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
 * Foto de la hoja de vida. La app comprueba que tenga un rostro y la recorta
 * en formato documento (vertical, 4:5) antes de subirla; aquí se exige ese
 * formato. Se guarda en el disco privado: la ve el propio estudiante y quien
 * consulta perfiles (empresas, docentes y administración).
 */
class FotoEstudianteController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'foto' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:'.config('fet.hoja_vida.foto_max_kb'), 'dimensions:min_width=240,min_height=300'],
        ], ['foto.dimensions' => 'La foto debe medir al menos 240 × 300 píxeles.']);

        [$ancho, $alto] = getimagesize($request->file('foto')->getRealPath()) ?: [1, 0];
        if ($alto / $ancho < 1.15 || $alto / $ancho > 1.4) {
            throw new ReglaNegocioException('La foto debe ser vertical, tipo documento. Súbela desde tu hoja de vida para recortarla.');
        }

        $estudiante = $request->user()->estudiante()->firstOrFail();

        if (! $estudiante->hojaVidaHabilitada()) {
            throw new ReglaNegocioException('Tu hoja de vida está deshabilitada mientras cursas tu práctica.');
        }

        app(HojaVidaService::class)->antesDeEditar($estudiante);
        $hojaVida = $estudiante->hojaVida()->firstOrCreate();

        if ($hojaVida->foto_url) {
            Storage::disk('local')->delete($hojaVida->foto_url);
        }

        $hojaVida->update([
            'foto_url' => $request->file('foto')->store('fotos', 'local'),
            'actualizado_en' => now(),
        ]);

        return response()->json(['url_foto' => "/api/estudiantes/{$estudiante->id}/foto"]);
    }

    public function show(Request $request, Estudiante $estudiante): StreamedResponse
    {
        Gate::authorize('view', $estudiante);

        $ruta = $estudiante->hojaVida()->value('foto_url');
        abort_unless($ruta && Storage::disk('local')->exists($ruta), 404, 'El estudiante no ha subido su foto.');

        return Storage::disk('local')->response($ruta, null, ['Cache-Control' => 'private, max-age=300']);
    }
}
