<?php

namespace Tests;

use App\Enums\Rol;
use App\Models\Empresa;
use App\Models\Estudiante;
use App\Models\Programa;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    /** Programa de 10 semestres. */
    protected function programa(): Programa
    {
        return Programa::factory()->create(['total_semestres' => 10]);
    }

    /** Estudiante habilitado (promedio registrado y suficiente). */
    protected function estudiante(int $semestre, ?Programa $programa = null): Estudiante
    {
        return Estudiante::factory()
            ->for($programa ?? $this->programa(), 'programa')
            ->enSemestre($semestre)
            ->create();
    }

    protected function comoEstudiante(Estudiante $estudiante): static
    {
        Sanctum::actingAs($estudiante->usuario);

        return $this;
    }

    protected function comoEmpresa(Empresa $empresa): static
    {
        Sanctum::actingAs($empresa->usuario);

        return $this;
    }

    protected function como(Rol $rol): Usuario
    {
        // refresh(): trae los valores por defecto de la base (creado_en, tema), como en una petición real.
        $usuario = Usuario::factory()->rol($rol)->create()->refresh();
        Sanctum::actingAs($usuario);

        return $usuario;
    }
}
