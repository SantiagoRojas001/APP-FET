<?php

namespace Database\Factories;

use App\Models\Competencia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Competencia>
 */
class CompetenciaFactory extends Factory
{
    public function definition(): array
    {
        return ['nombre' => fake()->unique()->word()];
    }
}
