<?php

namespace Database\Factories;

use App\Enums\EstadoVacante;
use App\Enums\Modalidad;
use App\Models\Empresa;
use App\Models\Vacante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vacante>
 */
class VacanteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'empresa_id' => Empresa::factory(),
            'titulo' => 'Pasante de '.fake()->jobTitle(),
            'descripcion' => fake()->paragraph(),
            'modalidad' => Modalidad::Pasantia,
            'estado' => EstadoVacante::Abierta,
            'publicada_en' => now(),
        ];
    }

    public function modalidad(Modalidad $modalidad): static
    {
        return $this->state(['modalidad' => $modalidad]);
    }
}
