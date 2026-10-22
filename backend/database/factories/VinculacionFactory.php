<?php

namespace Database\Factories;

use App\Enums\EstadoVinculacion;
use App\Enums\Modalidad;
use App\Models\Empresa;
use App\Models\Estudiante;
use App\Models\Vinculacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vinculacion>
 */
class VinculacionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'estudiante_id' => Estudiante::factory()->enSemestre(8),
            'empresa_id' => Empresa::factory(),
            'tipo' => Modalidad::Pasantia,
            'semestre_al_vincular' => 8,
            'fecha_inicio' => now()->subMonth()->toDateString(),
            'estado' => EstadoVinculacion::Activa,
        ];
    }
}
