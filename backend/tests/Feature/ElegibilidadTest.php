<?php

namespace Tests\Feature;

use App\Enums\Modalidad;
use App\Models\ReglaElegibilidad;
use App\Models\Vacante;
use App\Services\ElegibilidadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ElegibilidadTest extends TestCase
{
    use RefreshDatabase;

    private function servicio(): ElegibilidadService
    {
        return new ElegibilidadService;
    }

    public function test_habilita_la_practica_desde_el_semestre_minimo_del_programa(): void
    {
        $programa = $this->programaConRegla(7);

        $this->assertFalse($this->servicio()->habilitadoParaPractica($this->estudiante(6, $programa)));
        $this->assertTrue($this->servicio()->habilitadoParaPractica($this->estudiante(7, $programa)));
    }

    public function test_sin_regla_vigente_no_permite_ninguna_modalidad(): void
    {
        $programa = $this->programaConRegla(3);
        ReglaElegibilidad::query()->update(['vigente_hasta' => now()->subDay()->toDateString()]);

        $estudiante = $this->estudiante(9, $programa);

        $this->assertSame([], $this->servicio()->modalidadesPermitidas($estudiante));
    }

    public function test_los_egresados_no_pueden_postularse(): void
    {
        $estudiante = $this->estudiante(10);
        $estudiante->update(['es_egresado' => true]);

        $vacante = Vacante::factory()->modalidad(Modalidad::Pasantia)->create();

        $this->assertFalse($this->servicio()->habilitadoParaPractica($estudiante));
        $this->assertStringContainsString('egresado', $this->servicio()->motivoParaNoPostular($estudiante, $vacante));
    }

    public function test_usa_la_regla_mas_reciente_cuando_cambia(): void
    {
        $programa = $this->programaConRegla(7);
        ReglaElegibilidad::query()->update(['vigente_hasta' => now()->subDay()->toDateString()]);
        ReglaElegibilidad::factory()->for($programa)->create(['semestre_minimo' => 5, 'vigente_desde' => now()->toDateString()]);

        $this->assertTrue($this->servicio()->habilitadoParaPractica($this->estudiante(5, $programa)));
    }

    public function test_el_estudiante_consulta_su_elegibilidad(): void
    {
        $estudiante = $this->estudiante(5);

        $this->comoEstudiante($estudiante)
            ->getJson('/api/estudiante/elegibilidad')
            ->assertOk()
            ->assertJsonPath('habilitado_para_practica', false)
            ->assertJsonPath('semestre_minimo', 7)
            ->assertJsonCount(0, 'modalidades_permitidas');
    }
}
