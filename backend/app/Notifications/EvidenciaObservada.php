<?php

namespace App\Notifications;

use App\Models\EvidenciaSemanal;
use App\Models\Vinculacion;

/**
 * Al estudiante: la empresa devolvió una semana con un comentario para corregir.
 */
class EvidenciaObservada extends Aviso
{
    public function __construct(private readonly Vinculacion $vinculacion, private readonly EvidenciaSemanal $evidencia) {}

    protected function titulo(): string
    {
        return 'Corrige tu evidencia semanal';
    }

    protected function mensaje(): string
    {
        return "{$this->vinculacion->empresa->razon_social} devolvió la semana del {$this->evidencia->semana_inicio->format('d/m/Y')}: "
            ."«{$this->evidencia->comentario}». Corrígela para que cuenten esas horas.";
    }

    protected function enlace(): string
    {
        return '/evidencias';
    }
}
