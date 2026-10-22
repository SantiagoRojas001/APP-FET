<?php

namespace App\Http\Resources;

use BackedEnum;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Base de los recursos de la API. Los enums se exponen con su valor (para la
 * lógica del cliente) y su etiqueta en español (para mostrar).
 */
abstract class Recurso extends JsonResource
{
    /**
     * @return array{valor: string, etiqueta: string}|null
     */
    protected function enum(?BackedEnum $valor): ?array
    {
        return $valor ? ['valor' => $valor->value, 'etiqueta' => $valor->label()] : null;
    }
}
