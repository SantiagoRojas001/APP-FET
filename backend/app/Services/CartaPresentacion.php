<?php

namespace App\Services;

use App\Models\Cita;
use App\Models\Usuario;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Carta con la que el director de programa presenta al estudiante ante la
 * empresa. Se genera a partir de la cita en la que la empresa lo aceptó y la
 * firma el director (nombre, documento y firma): el que la envió o, antes de
 * enviarla, el director que la consulta.
 */
class CartaPresentacion
{
    public function pdf(Cita $cita, ?Usuario $firmante = null): string
    {
        $cita->loadMissing('empresa', 'estudiante.programa', 'estudiante.hojaVida.revisor', 'vacante', 'director');
        $firmante = $cita->director ?? $firmante;
        $firma = $firmante?->firma_url && Storage::disk('local')->exists($firmante->firma_url)
            ? 'data:image/png;base64,'.base64_encode(Storage::disk('local')->get($firmante->firma_url))
            : null;

        return Pdf::loadView('pdf.carta-presentacion', [
            'cita' => $cita,
            'firmante' => $firmante,
            'firma' => $firma,
            'logo' => base64_encode(file_get_contents(resource_path('img/logo-fet.png'))),
        ])->setPaper('letter')->output();
    }
}
