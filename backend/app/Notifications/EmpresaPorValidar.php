<?php

namespace App\Notifications;

use App\Models\Empresa;

/**
 * A la administración: una empresa se registró y espera validación.
 */
class EmpresaPorValidar extends Aviso
{
    public function __construct(private readonly Empresa $empresa) {}

    protected function titulo(): string
    {
        return 'Empresa por validar';
    }

    protected function mensaje(): string
    {
        return "{$this->empresa->razon_social} (NIT {$this->empresa->nit}) se registró en APP FET. Valídala para que pueda ingresar.";
    }

    protected function enlace(): string
    {
        return "/empresas/{$this->empresa->id}";
    }
}
