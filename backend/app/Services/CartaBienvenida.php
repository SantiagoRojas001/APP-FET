<?php

namespace App\Services;

use App\Models\Vinculacion;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Carta de bienvenida que recibe el estudiante cuando el docente autoriza su
 * práctica. Se genera cada vez a partir de la vinculación.
 */
class CartaBienvenida
{
    public function pdf(Vinculacion $vinculacion): string
    {
        $vinculacion->loadMissing('estudiante.programa', 'empresa', 'vacante', 'tutor');

        return Pdf::loadView('pdf.carta-bienvenida', [
            'v' => $vinculacion,
            'logo' => base64_encode(file_get_contents(resource_path('img/logo-fet.png'))),
        ])->setPaper('letter')->output();
    }
}
