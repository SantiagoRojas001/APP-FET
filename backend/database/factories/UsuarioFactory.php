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

    public function inactivo(): static
    {
        return $this->state(['activo' => false]);
    }
}
