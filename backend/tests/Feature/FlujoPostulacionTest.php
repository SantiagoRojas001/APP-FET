<?php

namespace Tests\Feature;

use App\Enums\EstadoPostulacion;
use App\Enums\EstadoVacante;
use App\Enums\EstadoVinculacion;
use App\Enums\Modalidad;
use App\Enums\Rol;
use App\Models\Empresa;
use App\Models\Postulacion;
use App\Models\Usuario;
use App\Models\Vacante;
use App\Models\Vinculacion;
use App\Notifications\EstudianteSeleccionado;
use App\Notifications\PracticaAutorizada;
use App\Notifications\SeleccionNoAutorizada;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Ciclo completo: el estudiante aplica, la empresa recibe la hoja de vida y
 * selecciona, el docente autoriza y se crea la vinculación con la carta de
 * bienvenida.
 */
class FlujoPostulacionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_flujo_completo_de_postulacion_a_vinculacion(): void
    {
        $empresa = Empresa::factory()->create();
        $vacante = Vacante::factory()->for($empresa)->modalidad(Modalidad::Pasantia)->create();
        $otraVacante = Vacante::factory()->create();
        $estudiante = $this->estudiante(8);
        $docente = Usuario::factory()->rol(Rol::Docente)->create();

        // 1. El estudiante aplica y su hoja de vida llega directo a la empresa
        $id = $this->comoEstudiante($estudiante)
            ->postJson("/api/estudiante/vacantes/{$vacante->id}/postulaciones")
            ->assertCreated()
            ->assertJsonPath('estado.valor', 'ENVIADA')
            ->json('id');
        $otra = $this->postJson("/api/estudiante/vacantes/{$otraVacante->id}/postulaciones")->assertCreated()->json('id');

        $this->comoEmpresa($empresa)->getJson('/api/empresa/postulaciones')->assertOk()->assertJsonCount(1);

        // 2. La empresa lo selecciona y se avisa a los docentes
        $this->patchJson("/api/empresa/postulaciones/{$id}", ['decision' => 'SELECCIONADA'])
            ->assertOk()
            ->assertJsonPath('estado.valor', 'SELECCIONADA');
        $this->assertDatabaseCount('vinculacion', 0);
        Notification::assertSentTo($docente, EstudianteSeleccionado::class);

        // 3. El docente autoriza: se crea la vinculación con él como tutor
        Sanctum::actingAs($docente);
        $this->getJson('/api/docente/postulaciones?estado=SELECCIONADA')->assertJsonCount(1);
        $this->patchJson("/api/docente/postulaciones/{$id}", ['decision' => 'ACEPTADA'])
            ->assertOk()
            ->assertJsonPath('estado.valor', 'ACEPTADA');

        $vinculacion = Vinculacion::sole();
        $this->assertSame($estudiante->id, $vinculacion->estudiante_id);
        $this->assertSame($docente->id, $vinculacion->tutor_id);
        $this->assertSame(Modalidad::Pasantia, $vinculacion->tipo);
        $this->assertSame(8, $vinculacion->semestre_al_vincular);
        $this->assertSame(EstadoVinculacion::Activa, $vinculacion->estado);

        // La carta de bienvenida llega al estudiante y la empresa recibe el aviso
        Notification::assertSentTo($estudiante->usuario, PracticaAutorizada::class, function (PracticaAutorizada $aviso) use ($estudiante) {
            return count($aviso->toMail($estudiante->usuario)->rawAttachments) === 1;
        });
        Notification::assertSentTo($empresa->usuario, PracticaAutorizada::class);

        // Las demás postulaciones se cierran y la hoja de vida queda deshabilitada
        $this->assertSame(EstadoPostulacion::Descartada, Postulacion::find($otra)->estado);
        $this->comoEstudiante($estudiante)
            ->putJson('/api/estudiante/perfil', ['experiencias' => [], 'formaciones' => [], 'cursos' => [], 'investigaciones' => [], 'referencias' => [], 'competencias' => [], 'proyectos' => []])
            ->assertUnprocessable()
            ->assertJsonPath('message', fn (string $m) => str_contains($m, 'deshabilitada'));
        $this->get("/api/vinculaciones/{$vinculacion->id}/carta-bienvenida.pdf")->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->comoEmpresa(Empresa::factory()->create())->getJson('/api/estudiantes')->assertJsonCount(0, 'data');
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

    public function test_el_docente_debe_justificar_si_no_autoriza(): void
    {
        $postulacion = Postulacion::factory()->estado(EstadoPostulacion::Seleccionada)->create();
        $this->como(Rol::Docente);

        $this->patchJson("/api/docente/postulaciones/{$postulacion->id}", ['decision' => 'RECHAZADA'])
            ->assertJsonValidationErrors('observacion');

        $this->patchJson("/api/docente/postulaciones/{$postulacion->id}", ['decision' => 'RECHAZADA', 'observacion' => 'No cumple el semestre.'])
            ->assertOk()
            ->assertJsonPath('estado.valor', 'RECHAZADA')
            ->assertJsonPath('observacion', 'No cumple el semestre.');

        $this->assertDatabaseCount('vinculacion', 0);
        Notification::assertSentTo($postulacion->estudiante->usuario, SeleccionNoAutorizada::class);
    }

    public function test_el_docente_solo_autoriza_postulaciones_seleccionadas_por_la_empresa(): void
    {
        $postulacion = Postulacion::factory()->create();
        $this->como(Rol::Docente);

        $this->patchJson("/api/docente/postulaciones/{$postulacion->id}", ['decision' => 'ACEPTADA'])
            ->assertUnprocessable();

        $this->assertSame(EstadoPostulacion::Enviada, $postulacion->fresh()->estado);
        $this->assertDatabaseCount('vinculacion', 0);
    }

    public function test_una_empresa_no_puede_decidir_postulaciones_de_otra_empresa(): void
    {
        $postulacion = Postulacion::factory()->create();
        $otra = Empresa::factory()->create();

        $this->comoEmpresa($otra)
            ->patchJson("/api/empresa/postulaciones/{$postulacion->id}", ['decision' => 'SELECCIONADA'])
            ->assertNotFound();

        $this->assertSame(EstadoPostulacion::Enviada, $postulacion->fresh()->estado);
    }

    public function test_descartar_no_pasa_al_docente(): void
    {
        $postulacion = Postulacion::factory()->create();

        $this->comoEmpresa($postulacion->vacante->empresa)
            ->patchJson("/api/empresa/postulaciones/{$postulacion->id}", ['decision' => 'DESCARTADA'])
            ->assertOk()
            ->assertJsonPath('estado.valor', 'DESCARTADA');

        $this->assertDatabaseCount('vinculacion', 0);
        Notification::assertNothingSent();
    }
}
