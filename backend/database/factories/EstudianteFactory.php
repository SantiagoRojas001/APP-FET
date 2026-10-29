<?php

namespace Database\Factories;

use App\Enums\Modalidad;
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
            'modalidad_grado' => Modalidad::Pasantia,
            'promedio' => 4.5,
            'habilitado' => true,
            'resultado_enviado_en' => now(),
        ];
    }

    public function enSemestre(int $semestre): static
    {
        return $this->state(['semestre_actual' => $semestre]);
    }

    /** Recién registrado por el docente: sin promedio ni resultado enviado. */
    public function sinPromedio(): static
    {
        return $this->state(['promedio' => null, 'habilitado' => null, 'resultado_enviado_en' => null]);
    }

    /** Con la hoja de vida publicada: las empresas lo encuentran. */
    public function publicado(): static
    {
        return $this->afterCreating(fn (Estudiante $estudiante) => $estudiante->hojaVida()->update(['publicada_en' => now()]));
    }

    public function configure(): static
    {
        return $this->afterCreating(fn (Estudiante $estudiante) => $estudiante->hojaVida()->create());
    }
}
