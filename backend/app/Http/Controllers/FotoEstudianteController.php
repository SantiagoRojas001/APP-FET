<?php

namespace App\Http\Controllers;

use App\Enums\Rol;
use App\Exceptions\ReglaNegocioException;
use App\Models\Estudiante;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Foto de la hoja de vida. Se guarda en el disco privado: la ve el propio
 * estudiante y quien consulta perfiles (empresas, docentes y administración).
 */
class FotoEstudianteController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'foto' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:'.config('fet.hoja_vida.foto_max_kb'), 'dimensions:min_width=200,min_height=200'],
        ], ['foto.dimensions' => 'La foto debe medir al menos 200 × 200 píxeles.']);

        $estudiante = $request->user()->estudiante()->firstOrFail();

        if (! $estudiante->hojaVidaHabilitada()) {
            throw new ReglaNegocioException('Tu hoja de vida está deshabilitada mientras cursas tu práctica.');
        }

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
        $usuario = $request->user();
        abort_unless($usuario->id === $estudiante->usuario_id || $usuario->tieneRol(Rol::Empresa, Rol::Docente, Rol::Admin), 403);

        $ruta = $estudiante->hojaVida()->value('foto_url');
        abort_unless($ruta && Storage::disk('local')->exists($ruta), 404, 'El estudiante no ha subido su foto.');

        return Storage::disk('local')->response($ruta, null, ['Cache-Control' => 'private, max-age=300']);
    }
}
