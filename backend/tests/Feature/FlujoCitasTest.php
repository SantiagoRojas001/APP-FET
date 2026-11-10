<?php

namespace Tests\Feature;

use App\Enums\EstadoAcuerdo;
use App\Enums\EstadoCita;
use App\Enums\EstadoHojaVida;
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
use App\Notifications\HojaVidaRevisada;
use App\Notifications\PracticaAutorizada;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * De la hoja de vida a la práctica: el docente la aprueba (o la devuelve con
 * recomendaciones), la empresa con acuerdo asigna una cita y según ella
 * acepta o rechaza; si acepta, el director envía la carta de presentación.
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

    public function test_flujo_completo_de_la_hoja_de_vida_a_la_practica(): void
    {
        $empresa = $this->empresaConAcuerdo();
        $estudiante = Estudiante::factory()->create();
        $estudiante->hojaVida()->update(['estado' => EstadoHojaVida::EnRevision, 'enviada_en' => now()]);
        $docente = Usuario::factory()->rol(Rol::Docente)->create();
        $director = Usuario::factory()->rol(Rol::Director)->create(['programa_id' => $estudiante->programa_id]);

        // 1. El docente devuelve la hoja de vida con recomendaciones; mientras tanto la empresa no la ve
        Sanctum::actingAs($docente);
        $this->getJson('/api/docente/hojas-vida')->assertJsonCount(1);
        $this->patchJson("/api/docente/hojas-vida/{$estudiante->id}", ['aprobar' => false])->assertJsonValidationErrors('observaciones');
        $this->patchJson("/api/docente/hojas-vida/{$estudiante->id}", ['aprobar' => false, 'observaciones' => 'Agrega tus funciones.'])
            ->assertOk()
            ->assertJsonPath('hoja_vida.estado.valor', 'CON_OBSERVACIONES')
            ->assertJsonPath('hoja_vida.observaciones', 'Agrega tus funciones.');
        Notification::assertSentTo($estudiante->usuario, HojaVidaRevisada::class);
        $this->comoEmpresa($empresa)->getJson('/api/empresa/hojas-vida')->assertJsonCount(0, 'data');

        // 2. El estudiante la corrige, la envía de nuevo y el docente la aprueba
        $estudiante->hojaVida()->update(['estado' => EstadoHojaVida::EnRevision]);
        Sanctum::actingAs($docente);
        $this->patchJson("/api/docente/hojas-vida/{$estudiante->id}", ['aprobar' => true])->assertJsonPath('hoja_vida.estado.valor', 'APROBADA');
        $otra = Cita::factory()->for($estudiante)->create(['fecha_hora' => now()->addDays(3)]);

        // 3. La empresa ve la hoja de vida aprobada y asigna una cita
        $this->comoEmpresa($empresa)->getJson('/api/empresa/hojas-vida')->assertJsonCount(1, 'data');
        $id = $this->citaPara($estudiante)->assertCreated()->assertJsonPath('estado.valor', 'PROGRAMADA')->json('id');
        Notification::assertSentTo($estudiante->usuario, CitaAsignada::class, function (CitaAsignada $aviso) use ($estudiante) {
            $datos = $aviso->toArray($estudiante->usuario);

            return str_contains($datos['mensaje'], 'Carrera 5 # 10-38') && str_contains($datos['titulo'], 'Tienes una cita');
        });

        // El estudiante ve a las empresas interesadas (puede tener varias citas)
        Sanctum::actingAs($estudiante->usuario);
        $this->getJson('/api/estudiante/citas')->assertOk()->assertJsonCount(2);

        // 4. Según la cita, la empresa acepta al estudiante y se avisa al director
        $this->comoEmpresa($empresa)->postJson("/api/empresa/citas/{$id}/decision", ['aceptar' => true])
            ->assertUnprocessable()
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'después de la cita'));
        Cita::whereKey($id)->update(['fecha_hora' => now()->subHour()]);
        $this->postJson("/api/empresa/citas/{$id}/decision", ['aceptar' => true, 'fecha_inicio' => now()->addWeek()->toDateString()])
            ->assertOk()
            ->assertJsonPath('estado.valor', 'ACEPTADO');
        Notification::assertSentTo($director, CartaPorEnviar::class);
        $this->assertDatabaseCount('vinculacion', 0);

        // 5. El director envía la carta de presentación: empieza la práctica
        Sanctum::actingAs($director);
        $this->getJson('/api/director/cartas')->assertJsonCount(1);
        $this->get("/api/citas/{$id}/carta-presentacion.pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
        // La carta la firma el director: sin su nombre, documento y firma no se envía.
        $this->postJson("/api/director/cartas/{$id}")->assertUnprocessable()->assertJsonPath('message', fn ($m) => str_contains($m, 'firma'));
        $this->post('/api/director/firma', ['nombre' => 'Marta Díaz', 'documento' => '36.123.456', 'firma' => $this->firmaPng()], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('completa', true);
        $this->get('/api/director/firma/imagen')->assertOk();
        $this->postJson("/api/director/cartas/{$id}")->assertOk()->assertJsonPath('estado.valor', 'PRESENTADO');

        $vinculacion = Vinculacion::sole();
        $this->assertSame($estudiante->id, $vinculacion->estudiante_id);
        $this->assertSame($docente->id, $vinculacion->tutor_id);
        $this->assertSame(now()->addWeek()->toDateString(), $vinculacion->fecha_inicio->toDateString());
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
        $estudiante = Estudiante::factory()->hojaAprobada()->create();
        $this->comoEmpresa($empresa);

        $this->citaPara($estudiante)->assertUnprocessable()->assertJsonPath('message', fn ($m) => str_contains($m, 'convenio'));

        Acuerdo::create([
            'empresa_id' => $empresa->id, 'archivo_url' => 'x.pdf', 'estado' => EstadoAcuerdo::Aprobado,
            'vigencia_inicio' => now()->subYear()->toDateString(), 'vigencia_fin' => now()->subDay()->toDateString(),
        ]);
        $this->citaPara($estudiante)->assertUnprocessable();
    }

    public function test_no_se_asignan_citas_a_hojas_de_vida_sin_aprobar_ni_en_el_pasado(): void
    {
        $this->comoEmpresa($this->empresaConAcuerdo());

        $this->citaPara($this->estudiante(9))->assertUnprocessable();
        $this->citaPara(Estudiante::factory()->hojaAprobada()->create(), '-1 day')->assertJsonValidationErrors('fecha_hora');
    }

    public function test_la_empresa_rechaza_al_estudiante_despues_de_la_cita(): void
    {
        $cita = Cita::factory()->create();

        $this->comoEmpresa($cita->empresa)
            ->postJson("/api/empresa/citas/{$cita->id}/decision", ['aceptar' => false, 'observacion' => 'Buscamos otro perfil.'])
            ->assertOk()
            ->assertJsonPath('estado.valor', 'RECHAZADO');

        Notification::assertNothingSent();
        $this->assertDatabaseCount('vinculacion', 0);
    }

    public function test_el_director_solo_presenta_estudiantes_de_su_programa(): void
    {
        $cita = Cita::factory()->create(['estado' => EstadoCita::Aceptado]);
        Sanctum::actingAs(Usuario::factory()->rol(Rol::Director)->create(['programa_id' => $this->programa()->id]));

        $this->getJson('/api/director/cartas')->assertJsonCount(0);
        $this->postJson("/api/director/cartas/{$cita->id}")->assertNotFound();
        $this->assertSame(EstadoCita::Aceptado, $cita->fresh()->estado);
    }

    public function test_una_empresa_no_decide_citas_de_otra(): void
    {
        $cita = Cita::factory()->create();

        $this->comoEmpresa(Empresa::factory()->create())
            ->postJson("/api/empresa/citas/{$cita->id}/decision", ['aceptar' => true])
            ->assertNotFound();
    }

    public function test_el_docente_recomienda_y_la_empresa_ve_primero_a_los_recomendados(): void
    {
        $empresa = $this->empresaConAcuerdo();
        $vacante = Vacante::factory()->for($empresa)->create();
        $otro = Estudiante::factory()->hojaAprobada()->create(['apellidos' => 'Aaron']);
        $recomendado = Estudiante::factory()->hojaAprobada()->create(['apellidos' => 'Zapata']);

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
            ->assertJsonPath('data.0.ciclo.etiqueta', 'Técnico')
            ->assertJsonPath('data.1.ciclo.valor', 'PROFESIONAL')
            ->assertJsonPath('data.1.practica_actual.tipo.valor', fn ($v) => $v !== null);
        $this->getJson('/api/director/estudiantes?practica=NINGUNA')->assertJsonCount(1, 'data');
    }
}
