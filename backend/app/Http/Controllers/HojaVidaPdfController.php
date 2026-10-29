<?php

namespace App\Http\Controllers;

use App\Enums\Rol;
use App\Models\Estudiante;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Hoja de vida en PDF con el formato institucional PA-GTH-F-10. La descarga el propio
 * estudiante y quien consulta perfiles (empresas, docentes y administración).
 */
class HojaVidaPdfController extends Controller
{
    public function propia(Request $request): Response
    {
        return $this->pdf($request, $request->user()->estudiante()->firstOrFail());
    }

    public function deEstudiante(Request $request, Estudiante $estudiante): Response
    {
        return $this->pdf($request, $estudiante);
    }

    private function pdf(Request $request, Estudiante $estudiante): Response
    {
        $estudiante->cargarHojaVida();
        $usuario = $request->user();

        $pdf = Pdf::loadView('pdf.hoja-vida', [
            'estudiante' => $estudiante,
            // Las empresas no ven el documento de identidad (igual que en la API).
            'verDocumento' => $usuario->id === $estudiante->usuario_id || $usuario->tieneRol(Rol::Docente, Rol::Admin),
            'logo' => base64_encode(file_get_contents(resource_path('img/logo-fet.png'))),
            'foto' => $this->foto($estudiante),
        ])->setPaper('letter');

        return $pdf->download('hoja-de-vida-'.Str::slug($estudiante->nombre_completo).'.pdf');
    }

    /** Foto como data URI para incrustarla en el PDF, o null si no la ha subido. */
    private function foto(Estudiante $estudiante): ?string
    {
        $ruta = $estudiante->hojaVida?->foto_url;

        if (! $ruta || ! Storage::disk('local')->exists($ruta)) {
            return null;
        }

        return 'data:'.Storage::disk('local')->mimeType($ruta).';base64,'.base64_encode(Storage::disk('local')->get($ruta));
    }
}
