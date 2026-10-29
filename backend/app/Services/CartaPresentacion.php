<?php

namespace App\Services;

use App\Models\Cita;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Carta con la que el director de programa presenta al estudiante ante la
 * empresa. Se genera a partir de la cita en la que la empresa lo aceptó.
 */
class CartaPresentacion
{
    public function pdf(Cita $cita): string
    {
        $cita->loadMissing('empresa', 'estudiante.programa', 'estudiante.hojaVida.revisor', 'vacante', 'director');

        return Pdf::loadView('pdf.carta-presentacion', [
            'cita' => $cita,
            'logo' => base64_encode(file_get_contents(resource_path('img/logo-fet.png'))),
        ])->setPaper('letter')->output();
    }
}
