<?php

namespace Database\Factories;

use App\Enums\Rol;
use App\Models\Estudiante;
use App\Models\Programa;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Estudiante>
 */
class EstudianteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'usuario_id' => Usuario::factory()->rol(Rol::Estudiante),
            'programa_id' => Programa::factory(),
            'codigo' => (string) fake()->unique()->numerify('2026####'),
            'documento' => (string) fake()->unique()->numerify('10########'),
            'nombres' => fake()->firstName(),
            'apellidos' => fake()->lastName(),
            'telefono' => fake()->numerify('3#########'),
            'semestre_actual' => 5,
            'es_egresado' => false,
            'disponibilidad' => 'Medio tiempo',
        ];
    }

    public function enSemestre(int $semestre): static
    {
        return $this->state(['semestre_actual' => $semestre]);
    }

    public function configure(): static
    {
        return $this->afterCreating(fn (Estudiante $estudiante) => $estudiante->hojaVida()->create());
    }
}
