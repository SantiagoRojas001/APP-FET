<?php

namespace Tests\Feature;

use App\Enums\EstadoPostulacion;
use App\Enums\EstadoVacante;
use App\Enums\EstadoVinculacion;
use App\Enums\Modalidad;
use App\Enums\Rol;
use App\Models\Empresa;
use App\Models\Postulacion;
use App\Models\Vacante;
use App\Models\Vinculacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ciclo completo: el estudiante aplica, el docente aprueba, la empresa acepta
 * y se crea la vinculación.
 */
class FlujoPostulacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_flujo_completo_de_postulacion_a_vinculacion(): void
    {
        $empresa = Empresa::factory()->create();
        $vacante = Vacante::factory()->for($empresa)->modalidad(Modalidad::Pasantia)->create();
        $estudiante = $this->estudiante(8);

        // 1. El estudiante aplica
        $id = $this->comoEstudiante($estudiante)
            ->postJson("/api/estudiante/vacantes/{$vacante->id}/postulaciones")
            ->assertCreated()
            ->assertJsonPath('estado.valor', 'ENVIADA')
            ->json('id');

        // La empresa todavía no la ve
        $this->comoEmpresa($empresa)->getJson('/api/empresa/postulaciones')->assertOk()->assertJsonCount(0);

        // 2. El docente la aprueba
        $this->como(Rol::Docente);
        $this->patchJson("/api/docente/postulaciones/{$id}", ['decision' => 'APROBADA'])
            ->assertOk()
            ->assertJsonPath('estado.valor', 'APROBADA');

        // 3. La empresa la ve y acepta
        $this->comoEmpresa($empresa)->getJson('/api/empresa/postulaciones')->assertJsonCount(1);
        $this->patchJson("/api/empresa/postulaciones/{$id}", ['decision' => 'ACEPTADA'])
            ->assertOk()
            ->assertJsonPath('estado.valor', 'ACEPTADA');

        $vinculacion = Vinculacion::sole();
        $this->assertSame($estudiante->id, $vinculacion->estudiante_id);
        $this->assertSame(Modalidad::Pasantia, $vinculacion->tipo);
        $this->assertSame(8, $vinculacion->semestre_al_vincular);
        $this->assertSame(EstadoVinculacion::Activa, $vinculacion->estado);
    }

    public function test_un_estudiante_no_habilitado_no_puede_postularse(): void
    {
        $estudiante = $this->estudiante(3);
        $pasantia = Vacante::factory()->modalidad(Modalidad::Pasantia)->create();
        $practica = Vacante::factory()->modalidad(Modalidad::Practica)->create();

        $this->comoEstudiante($estudiante);

        $this->postJson("/api/estudiante/vacantes/{$pasantia->id}/postulaciones")
            ->assertUnprocessable()
            ->assertJsonPath('message', fn (string $m) => str_contains($m, '7.º semestre'));
        $this->postJson("/api/estudiante/vacantes/{$practica->id}/postulaciones")->assertUnprocessable();

        $this->assertDatabaseCount('postulacion', 0);
    }

    public function test_el_listado_de_vacantes_marca_las_disponibles_y_oculta_empresas_sin_validar(): void
    {
        $habilitado = $this->estudiante(8);
        $noHabilitado = $this->estudiante(3, $habilitado->programa);
        Vacante::factory()->modalidad(Modalidad::Pasantia)->create();
        Vacante::factory()->modalidad(Modalidad::Practica)->create();
        Vacante::factory()->for(Empresa::factory()->pendiente())->create();
        Vacante::factory()->create(['estado' => EstadoVacante::Cerrada]);

        $vacantes = $this->comoEstudiante($habilitado)->getJson('/api/estudiante/vacantes')->assertOk()->json();
        $this->assertCount(2, $vacantes);
        $this->assertSame([true, true], array_column($vacantes, 'puede_postularse'));

        $vacantes = $this->comoEstudiante($noHabilitado)->getJson('/api/estudiante/vacantes')->json();
        $this->assertSame([false, false], array_column($vacantes, 'puede_postularse'));
    }

    public function test_no_permite_postularse_dos_veces_a_la_misma_vacante(): void
    {
        $estudiante = $this->estudiante(8);
        $vacante = Vacante::factory()->create();

        $this->comoEstudiante($estudiante)->postJson("/api/estudiante/vacantes/{$vacante->id}/postulaciones")->assertCreated();
        $this->postJson("/api/estudiante/vacantes/{$vacante->id}/postulaciones")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Ya te postulaste a esta vacante.');
    }

    public function test_no_permite_postularse_con_una_vinculacion_activa(): void
    {
        $estudiante = $this->estudiante(8);
        Vinculacion::factory()->for($estudiante)->create();
        $vacante = Vacante::factory()->create();

        $this->comoEstudiante($estudiante)
            ->postJson("/api/estudiante/vacantes/{$vacante->id}/postulaciones")
            ->assertUnprocessable();

        $this->getJson('/api/estudiante/vacantes')
            ->assertJsonPath('0.puede_postularse', false)
            ->assertJsonPath('0.motivo_no_disponible', 'Ya tienes una vinculación activa.');
    }

    public function test_el_docente_debe_justificar_un_rechazo(): void
    {
        $postulacion = Postulacion::factory()->create();
        $this->como(Rol::Docente);

        $this->patchJson("/api/docente/postulaciones/{$postulacion->id}", ['decision' => 'RECHAZADA'])
            ->assertJsonValidationErrors('observacion');

        $this->patchJson("/api/docente/postulaciones/{$postulacion->id}", ['decision' => 'RECHAZADA', 'observacion' => 'Falta la hoja de vida.'])
            ->assertOk()
            ->assertJsonPath('observacion', 'Falta la hoja de vida.');
    }

    public function test_una_postulacion_ya_decidida_no_se_puede_volver_a_decidir(): void
    {
        $postulacion = Postulacion::factory()->estado(EstadoPostulacion::Rechazada)->create();
        $this->como(Rol::Docente);

        $this->patchJson("/api/docente/postulaciones/{$postulacion->id}", ['decision' => 'APROBADA'])
            ->assertUnprocessable();

        $this->assertSame(EstadoPostulacion::Rechazada, $postulacion->fresh()->estado);
    }

    public function test_una_empresa_no_puede_decidir_postulaciones_de_otra_empresa(): void
    {
        $postulacion = Postulacion::factory()->estado(EstadoPostulacion::Aprobada)->create();
        $otra = Empresa::factory()->create();

        $this->comoEmpresa($otra)
            ->patchJson("/api/empresa/postulaciones/{$postulacion->id}", ['decision' => 'ACEPTADA'])
            ->assertNotFound();

        $this->assertDatabaseCount('vinculacion', 0);
    }

    public function test_descartar_no_crea_vinculacion(): void
    {
        $postulacion = Postulacion::factory()->estado(EstadoPostulacion::Aprobada)->create();

        $this->comoEmpresa($postulacion->vacante->empresa)
            ->patchJson("/api/empresa/postulaciones/{$postulacion->id}", ['decision' => 'DESCARTADA'])
            ->assertOk()
            ->assertJsonPath('estado.valor', 'DESCARTADA');

        $this->assertDatabaseCount('vinculacion', 0);
    }
}
