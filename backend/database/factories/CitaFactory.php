<?php

namespace Database\Factories;

use App\Models\Cita;
use App\Models\Empresa;
use App\Models\Estudiante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cita>
 */
class CitaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'empresa_id' => Empresa::factory(),
            'estudiante_id' => Estudiante::factory()->publicado(),
            'fecha_hora' => now()->subDay(),
            'direccion' => 'Calle 10 # 5-20, Neiva',
        ];
    }
}
