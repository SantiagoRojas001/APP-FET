<?php

namespace Database\Factories;

use App\Enums\EstadoValidacion;
use App\Enums\Modalidad;
use App\Enums\Rol;
use App\Models\Empresa;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Empresa>
 */
class EmpresaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'usuario_id' => Usuario::factory()->rol(Rol::Empresa)->state(['correo' => fake()->unique()->companyEmail()]),
            'nit' => fake()->unique()->numerify('901.###.###-#'),
            'razon_social' => fake()->company(),
            'sector' => 'Tecnología',
            'contacto_nombre' => fake()->name(),
            'contacto_telefono' => fake()->numerify('3#########'),
            'modalidad_interes' => Modalidad::Pasantia,
            'estado_validacion' => EstadoValidacion::Validada,
            'validada_en' => now(),
        ];
    }

    public function pendiente(): static
    {
        return $this->state(['estado_validacion' => EstadoValidacion::Pendiente, 'validada_en' => null]);
    }
}
