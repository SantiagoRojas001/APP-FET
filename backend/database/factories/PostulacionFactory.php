<?php

namespace Database\Factories;

use App\Enums\EstadoPostulacion;
use App\Models\Estudiante;
use App\Models\Postulacion;
use App\Models\Vacante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Postulacion>
 */
class PostulacionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'vacante_id' => Vacante::factory(),
            'estudiante_id' => Estudiante::factory(),
            'estado' => EstadoPostulacion::Enviada,
            'fecha' => now(),
        ];
    }

    public function estado(EstadoPostulacion $estado): static
    {
        return $this->state(['estado' => $estado]);
    }
}
