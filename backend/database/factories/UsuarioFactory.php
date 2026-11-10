<?php

namespace Database\Factories;

use App\Enums\Rol;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Usuario>
 */
class UsuarioFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'correo' => fake()->unique()->userName().'@'.config('fet.dominio_correo'),
            'password_hash' => static::$password ??= Hash::make('password'),
            'rol' => Rol::Estudiante,
            'activo' => true,
        ];
    }

    public function rol(Rol $rol): static
    {
        return $this->state(['rol' => $rol]);
    }

    /** Director con los datos con los que firma las cartas de presentación. */
    public function firmante(): static
    {
        return $this->state(['nombre' => fake()->name(), 'documento' => (string) fake()->numberBetween(10000000, 1999999999), 'firma_url' => 'firmas-directores/prueba.png']);
    }

    public function inactivo(): static
    {
        return $this->state(['activo' => false]);
    }
}
