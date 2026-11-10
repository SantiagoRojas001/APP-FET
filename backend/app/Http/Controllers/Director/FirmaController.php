<?php

namespace App\Http\Controllers\Director;

use App\Http\Controllers\Controller;
use App\Services\Auditoria;
use App\Services\FirmaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Datos con los que el director firma las cartas de presentación: nombre,
 * documento de identidad y firma dibujada (ver FirmaService).
 */
class FirmaController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json($this->datos($request));
    }

    public function update(Request $request, FirmaService $firmas): JsonResponse
    {
        $director = $request->user();
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'documento' => ['required', 'string', 'max:20', 'regex:/^[0-9.\-]+$/'],
            'firma' => [$director->firma_url ? 'nullable' : 'required', 'file', 'mimes:png', 'max:300', 'dimensions:min_width=300,max_width=2400,min_height=100,max_height=1200'],
        ], [
            'documento.regex' => 'El documento solo puede tener números, puntos y guion.',
            'firma.required' => 'Dibuja tu firma.',
        ]);

        if ($request->hasFile('firma')) {
            $anterior = $director->firma_url;
            $director->firma_url = $firmas->guardar($request->file('firma'), 'firmas-directores');
            if ($anterior) {
                Storage::disk('local')->delete($anterior);
            }
        }
        $director->fill(['nombre' => $datos['nombre'], 'documento' => $datos['documento']])->save();
        Auditoria::registrar('director.firma_actualizada', $director);

        return response()->json($this->datos($request));
    }

    public function imagen(Request $request): StreamedResponse
    {
        $ruta = $request->user()->firma_url;
        abort_unless($ruta && Storage::disk('local')->exists($ruta), 404, 'Aún no registras tu firma.');

        return Storage::disk('local')->response($ruta);
    }

    /** @return array{nombre: string|null, documento: string|null, url_firma: string|null, completa: bool} */
    private function datos(Request $request): array
    {
        $director = $request->user();

        return [
            'nombre' => $director->nombre,
            'documento' => $director->documento,
            'url_firma' => $director->firma_url ? '/api/director/firma/imagen' : null,
            'completa' => $director->puedeFirmarCartas(),
        ];
    }
}
