<?php

namespace App\Http\Controllers;

use App\Enums\Rol;
use App\Models\Estudiante;
use App\Services\HojaVidaPdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hoja de vida en PDF con el formato institucional PA-GTH-F-10. La descarga el propio
 * estudiante y quien consulta perfiles (empresas, docentes y administración).
 */
class HojaVidaPdfController extends Controller
{
    public function propia(Request $request, HojaVidaPdf $pdf): Response
    {
        return $this->pdf($request, $request->user()->estudiante()->firstOrFail(), $pdf);
    }

    public function deEstudiante(Request $request, Estudiante $estudiante, HojaVidaPdf $pdf): Response
    {
        Gate::authorize('view', $estudiante);

        return $this->pdf($request, $estudiante, $pdf);
    }

    private function pdf(Request $request, Estudiante $estudiante, HojaVidaPdf $pdf): Response
    {
        $usuario = $request->user();
        $nombre = 'hoja-de-vida-'.Str::slug($estudiante->nombre_completo).'.pdf';

        // Mientras una versión editada está en revisión, la empresa recibe la última aprobada.
        $aprobada = $usuario->rol === Rol::Empresa ? $estudiante->hojaVida?->version_aprobada['pdf'] ?? null : null;
        if ($aprobada && Storage::disk('local')->exists($aprobada)) {
            return Storage::disk('local')->download($aprobada, $nombre);
        }

        // Las empresas no ven el documento de identidad (igual que en la API).
        $verDocumento = $usuario->id === $estudiante->usuario_id || $usuario->tieneRol(Rol::Docente, Rol::Admin, Rol::Director);

        return response($pdf->contenido($estudiante, $verDocumento), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$nombre}\"",
        ]);
    }
}
