<?php

namespace Database\Factories;

use App\Models\Programa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Programa>
 */
class ProgramaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => 'Programa '.fake()->unique()->words(3, true),
            'total_semestres' => 10,
        ];
    }
}
