<?php

namespace App\Notifications;

use App\Models\HojaVida;

/**
 * Al estudiante: el docente aprobó su hoja de vida o la devolvió con
 * recomendaciones para corregir.
 */
class HojaVidaRevisada extends Aviso
{
    public function __construct(private readonly HojaVida $hoja) {}

    protected function titulo(): string
    {
        return $this->hoja->aprobada() ? 'Tu hoja de vida fue aprobada' : 'Corrige tu hoja de vida';
    }

    protected function mensaje(): string
    {
        return $this->hoja->aprobada()
            ? 'Tu docente aprobó tu hoja de vida. Ya la ven las empresas: cuando alguna te asigne una cita, te avisaremos.'
            : "Tu docente revisó tu hoja de vida y te dejó estas recomendaciones: «{$this->hoja->observaciones}». Corrígela y envíala de nuevo.";
    }

    protected function enlace(): string
    {
        return '/hoja-vida/vista';
    }
}
