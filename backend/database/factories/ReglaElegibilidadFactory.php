<?php

namespace Database\Factories;

use App\Models\Programa;
use App\Models\ReglaElegibilidad;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReglaElegibilidad>
 */
class ReglaElegibilidadFactory extends Factory
{
    public function definition(): array
    {
        return [
            'programa_id' => Programa::factory(),
            'semestre_minimo' => 7,
            'vigente_desde' => now()->subYear()->toDateString(),
            'vigente_hasta' => null,
        ];
    }
}
