<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Competencia;
use App\Models\Empresa;
use App\Models\Postulacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HojaVidaTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_estudiante_completa_su_hoja_de_vida(): void
    {
        $estudiante = $this->estudiante(5);
        $php = Competencia::factory()->create(['nombre' => 'PHP']);

        $this->comoEstudiante($estudiante)->putJson('/api/estudiante/perfil', [
            'telefono' => '3001234567',
            'disponibilidad' => 'Tiempo completo',
            'perfil_profesional' => 'Desarrollador en formación.',
            'formacion' => 'Bachiller.',
            'experiencias' => [['cargo' => 'Monitor', 'organizacion' => 'FET', 'fecha_inicio' => '2025-02-01']],
            'competencias' => [['id' => $php->id, 'nivel' => 'INTERMEDIO']],
            'proyectos' => [['titulo' => 'API', 'url_repositorio' => 'https://github.com/ana/api']],
        ])->assertOk()
            ->assertJsonPath('hoja_vida.porcentaje', 100)
            ->assertJsonPath('competencias.0.nivel', 'INTERMEDIO')
            ->assertJsonCount(1, 'hoja_vida.experiencias');
    }

    public function test_rechaza_repositorios_que_no_son_enlaces_https(): void
    {
        $estudiante = $this->estudiante(5);

        $this->comoEstudiante($estudiante)->putJson('/api/estudiante/perfil', [
            'experiencias' => [],
            'competencias' => [],
            'proyectos' => [['titulo' => 'X', 'url_repositorio' => 'javascript:alert(1)']],
        ])->assertJsonValidationErrors('proyectos.0.url_repositorio');
    }

    public function test_las_empresas_ven_el_perfil_sin_el_documento_de_identidad(): void
    {
        $estudiante = $this->estudiante(5);
        $this->comoEmpresa(Empresa::factory()->create());

        $this->getJson("/api/estudiantes/{$estudiante->id}")
            ->assertOk()
            ->assertJsonPath('nombre_completo', $estudiante->nombre_completo)
            ->assertJsonMissingPath('documento');
    }

    public function test_los_listados_de_estudiantes_y_postulaciones_cargan_sin_consultas_n_mas_1(): void
    {
        Postulacion::factory()->count(3)->create();
        $this->como(Rol::Docente);

        // Con Model::shouldBeStrict activo, una carga perezosa haría fallar la petición.
        $this->getJson('/api/estudiantes')->assertOk()->assertJsonCount(3, 'data');
        $this->getJson('/api/docente/postulaciones')->assertOk()->assertJsonCount(3);
    }
}
