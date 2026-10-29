<?php

namespace Tests\Feature;

use App\Enums\EstadoAcuerdo;
use App\Enums\EstadoCita;
use App\Enums\EstadoVinculacion;
use App\Enums\Rol;
use App\Models\Acuerdo;
use App\Models\Cita;
use App\Models\Empresa;
use App\Models\Estudiante;
use App\Models\Usuario;
use App\Models\Vacante;
use App\Models\Vinculacion;
use App\Notifications\CartaPorEnviar;
use App\Notifications\CartaPresentacionEnviada;
use App\Notifications\CitaAsignada;
use App\Notifications\EstudianteRecomendado;
use App\Notifications\EstudianteSeleccionado;
use App\Notifications\PracticaAutorizada;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * De la hoja de vida publicada a la práctica: la empresa con acuerdo asigna
 * citas, selecciona, el docente aprueba y el director envía la carta.
 */
class FlujoCitasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('local');
    }

    private function empresaConAcuerdo(): Empresa
    {
        $empresa = Empresa::factory()->create();
        Acuerdo::create([
            'empresa_id' => $empresa->id, 'archivo_url' => 'acuerdos/x.pdf', 'estado' => EstadoAcuerdo::Aprobado,
            'vigencia_inicio' => now()->subMonth()->toDateString(), 'vigencia_fin' => now()->addYear()->toDateString(),
        ]);

        return $empresa;
    }

    private function citaPara(Estudiante $estudiante, string $fecha = '+2 days')
    {
        return $this->postJson('/api/empresa/citas', [
            'estudiante_id' => $estudiante->id,
            'fecha_hora' => now(config('fet.zona_horaria'))->modify($fecha)->format('Y-m-d\TH:i'),
            'direccion' => 'Carrera 5 # 10-38, Neiva',
        ]);
    }

    public function test_flujo_completo_de_la_cita_a_la_practica(): void
    {
        $empresa = $this->empresaConAcuerdo();
        $estudiante = Estudiante::factory()->publicado()->create();
        $otra = Cita::factory()->for($estudiante)->create(['fecha_hora' => now()->addDays(3)]);
        $docente = Usuario::factory()->rol(Rol::Docente)->create();
        $director = Usuario::factory()->rol(Rol::Director)->create(['programa_id' => $estudiante->programa_id]);

        // 1. La empresa ve la hoja de vida publicada y asigna una cita
        $this->comoEmpresa($empresa)->getJson('/api/empresa/hojas-vida')->assertJsonCount(1, 'data');
        $id = $this->citaPara($estudiante)->assertCreated()->assertJsonPath('estado.valor', 'PROGRAMADA')->json('id');
        Notification::assertSentTo($estudiante->usuario, CitaAsignada::class, function (CitaAsignada $aviso) use ($estudiante) {
            $datos = $aviso->toArray($estudiante->usuario);

            return str_contains($datos['mensaje'], 'Carrera 5 # 10-38') && str_contains($datos['titulo'], 'Tienes una cita');
        });

        // El estudiante ve a las empresas interesadas (puede tener varias citas)
        Sanctum::actingAs($estudiante->usuario);
        $this->getJson('/api/estudiante/citas')->assertOk()->assertJsonCount(2);

        // 2. Realizada la cita, la empresa selecciona
        $this->comoEmpresa($empresa)->postJson("/api/empresa/citas/{$id}/decision", ['seleccionar' => true])
            ->assertUnprocessable()
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'después de la cita'));
        Cita::whereKey($id)->update(['fecha_hora' => now()->subHour()]);
        $this->postJson("/api/empresa/citas/{$id}/decision", ['seleccionar' => true])->assertOk()->assertJsonPath('estado.valor', 'SELECCIONADO');
        Notification::assertSentTo($docente, EstudianteSeleccionado::class);

        // 3. El docente vuelve a aprobar
        Sanctum::actingAs($docente);
        $this->getJson('/api/docente/citas')->assertJsonCount(1);
        $this->patchJson("/api/docente/citas/{$id}", ['autorizar' => true])->assertOk()->assertJsonPath('estado.valor', 'AUTORIZADO');
        Notification::assertSentTo($director, CartaPorEnviar::class);
        $this->assertDatabaseCount('vinculacion', 0);

        // 4. El director envía la carta de presentación: empieza la práctica
        Sanctum::actingAs($director);
        $this->getJson('/api/director/cartas')->assertJsonCount(1);
        $this->get("/api/citas/{$id}/carta-presentacion.pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->postJson("/api/director/cartas/{$id}")->assertOk()->assertJsonPath('estado.valor', 'PRESENTADO');

        $vinculacion = Vinculacion::sole();
        $this->assertSame($estudiante->id, $vinculacion->estudiante_id);
        $this->assertSame($docente->id, $vinculacion->tutor_id);
        $this->assertSame(EstadoVinculacion::Activa, $vinculacion->estado);
        $this->assertSame(EstadoCita::Cancelada, $otra->fresh()->estado);
        Notification::assertSentTo($empresa->usuario, CartaPresentacionEnviada::class, fn ($aviso) => count($aviso->toMail($empresa->usuario)->rawAttachments) === 1);
        Notification::assertSentTo($estudiante->usuario, PracticaAutorizada::class);

        // La hoja de vida deja de circular
        $this->comoEmpresa($this->empresaConAcuerdo())->getJson('/api/empresa/hojas-vida')->assertJsonCount(0, 'data');
    }

    public function test_sin_acuerdo_aprobado_y_vigente_no_se_asignan_citas(): void
    {
        $empresa = Empresa::factory()->create();
        $estudiante = Estudiante::factory()->publicado()->create();
        $this->comoEmpresa($empresa);

        $this->citaPara($estudiante)->assertUnprocessable()->assertJsonPath('message', fn ($m) => str_contains($m, 'acuerdo'));

        Acuerdo::create([
            'empresa_id' => $empresa->id, 'archivo_url' => 'x.pdf', 'estado' => EstadoAcuerdo::Aprobado,
            'vigencia_inicio' => now()->subYear()->toDateString(), 'vigencia_fin' => now()->subDay()->toDateString(),
        ]);
        $this->citaPara($estudiante)->assertUnprocessable();
    }

    public function test_no_se_asignan_citas_a_hojas_de_vida_sin_publicar_ni_en_el_pasado(): void
    {
        $this->comoEmpresa($this->empresaConAcuerdo());

        $this->citaPara($this->estudiante(9))->assertUnprocessable();
        $this->citaPara(Estudiante::factory()->publicado()->create(), '-1 day')->assertJsonValidationErrors('fecha_hora');
    }

    public function test_el_director_solo_presenta_estudiantes_de_su_programa(): void
    {
        $cita = Cita::factory()->create(['estado' => EstadoCita::Autorizado]);
        Sanctum::actingAs(Usuario::factory()->rol(Rol::Director)->create(['programa_id' => $this->programa()->id]));

        $this->getJson('/api/director/cartas')->assertJsonCount(0);
        $this->postJson("/api/director/cartas/{$cita->id}")->assertNotFound();
        $this->assertSame(EstadoCita::Autorizado, $cita->fresh()->estado);
    }

    public function test_una_empresa_no_decide_citas_de_otra(): void
    {
        $cita = Cita::factory()->create();

        $this->comoEmpresa(Empresa::factory()->create())
            ->postJson("/api/empresa/citas/{$cita->id}/decision", ['seleccionar' => true])
            ->assertNotFound();
    }

    public function test_el_docente_recomienda_y_la_empresa_ve_primero_a_los_recomendados(): void
    {
        $empresa = $this->empresaConAcuerdo();
        $vacante = Vacante::factory()->for($empresa)->create();
        $otro = Estudiante::factory()->publicado()->create(['apellidos' => 'Aaron']);
        $recomendado = Estudiante::factory()->publicado()->create(['apellidos' => 'Zapata']);

        $this->como(Rol::Docente);
        $this->getJson('/api/docente/vacantes')->assertOk()->assertJsonCount(1);
        $this->postJson("/api/docente/vacantes/{$vacante->id}/recomendaciones", ['estudiante_id' => $recomendado->id, 'comentario' => 'Gran perfil'])->assertCreated();
        $this->postJson("/api/docente/vacantes/{$vacante->id}/recomendaciones", ['estudiante_id' => $recomendado->id])->assertUnprocessable();
        Notification::assertSentTo($empresa->usuario, EstudianteRecomendado::class);

        $this->comoEmpresa($empresa)->getJson('/api/empresa/hojas-vida')
            ->assertJsonPath('data.0.id', $recomendado->id)
            ->assertJsonPath('data.0.recomendado', true)
            ->assertJsonPath('data.0.recomendaciones.0.comentario', 'Gran perfil')
            ->assertJsonPath('data.1.id', $otro->id);

        // Las vacantes ya no las ven los estudiantes
        Sanctum::actingAs($otro->usuario);
        $this->getJson('/api/docente/vacantes')->assertForbidden();
    }

    public function test_el_director_ve_los_estudiantes_de_su_programa_con_ciclo_y_practica(): void
    {
        $programa = $this->programa();
        Estudiante::factory()->for($programa)->enSemestre(3)->create();
        $enPractica = Estudiante::factory()->for($programa)->enSemestre(9)->create();
        Vinculacion::factory()->for($enPractica)->create();
        Estudiante::factory()->create();
        Sanctum::actingAs(Usuario::factory()->rol(Rol::Director)->create(['programa_id' => $programa->id]));

        $this->getJson('/api/director/estudiantes')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.ciclo', 'Técnico')
            ->assertJsonPath('data.1.ciclo', 'Profesional')
            ->assertJsonPath('data.1.practica_actual.tipo.valor', fn ($v) => $v !== null);
        $this->getJson('/api/director/estudiantes?practica=NINGUNA')->assertJsonCount(1, 'data');
    }
}
