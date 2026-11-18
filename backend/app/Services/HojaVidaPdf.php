<?php

namespace App\Services;

use App\Models\Estudiante;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Hoja de vida en PDF con el formato institucional PA-GTH-F-10.
 */
class HojaVidaPdf
{
    public function contenido(Estudiante $estudiante, bool $verDocumento): string
    {
        $estudiante->cargarHojaVida();

        return Pdf::loadView('pdf.hoja-vida', [
            'estudiante' => $estudiante,
            'verDocumento' => $verDocumento,
            'logo' => base64_encode(file_get_contents(resource_path('img/logo-fet.png'))),
            'foto' => $this->imagen($estudiante->hojaVida?->foto_url),
            'firma' => $this->imagen($estudiante->hojaVida?->firma_url),
        ])->setPaper('letter')->output();
    }

    /** Foto o firma como data URI para incrustarla en el PDF, o null si no la hay. */
    private function imagen(?string $ruta): ?string
    {
        if (! $ruta || ! Storage::disk('local')->exists($ruta)) {
            return null;
        }

        return 'data:'.Storage::disk('local')->mimeType($ruta).';base64,'.base64_encode(Storage::disk('local')->get($ruta));
    }
}
