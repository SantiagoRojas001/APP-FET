<?php

namespace Tests\Feature;

use App\Enums\EstadoVinculacion;
use App\Enums\Rol;
use App\Models\Empresa;
use App\Models\Reporte;
use App\Models\Usuario;
use App\Models\Vinculacion;
use App\Notifications\EvidenciaObservada;
use App\Notifications\EvidenciaPorAprobar;
use App\Notifications\HorasCumplidas;
use App\Notifications\PracticaCulminada;
use App\Notifications\PracticanteCumplioHoras;
use App\Notifications\PracticaTerminada;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Horas semanales aprobadas por la empresa, cierre con la evaluación de la
 * empresa, finalización del docente y evaluaciones de cierre.
 */
class PracticaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('local');
    }

    /** Práctica de un programa que exige pocas horas, para no reportar 480. */
    private function practica(int $horasRequeridas = 16): Vinculacion
    {
        $vinculacion = Vinculacion::factory()->create(['fecha_inicio' => now()->subWeeks(4)->toDateString()]);
        $vinculacion->estudiante->programa->update(['horas_requeridas' => $horasRequeridas]);

        return $vinculacion;
    }

    private function reportarSemana(Vinculacion $vinculacion, int $semanasAtras, float $horas = 8, array $extra = [])
    {
        Sanctum::actingAs($vinculacion->estudiante->usuario);

        return $this->postJson("/api/vinculaciones/{$vinculacion->id}/evidencias", [
            'semana_inicio' => now()->startOfWeek()->subWeeks($semanasAtras)->toDateString(),
            'horas' => $horas,
            'actividades' => 'Desarrollo del módulo de reportes',
            ...$extra,
        ]);
    }

    /** Reporta una semana y la empresa la aprueba. */
    private function semanaAprobada(Vinculacion $vinculacion, int $semanasAtras, float $horas = 8): void
    {
        $id = $this->reportarSemana($vinculacion, $semanasAtras, $horas)->assertCreated()->json('id');
        $this->comoEmpresa($vinculacion->empresa)
            ->patchJson("/api/evidencias/{$id}/revision", ['estado' => 'APROBADO'])
            ->assertOk();
    }

    private function respuestas(string $tipo): array
    {
        return collect(config("evaluaciones.{$tipo}"))
            ->mapWithKeys(fn ($p) => [$p['id'] => $p['opciones'][0] ?? 'Respuesta abierta'])
            ->all();
    }

    public function test_solo_cuentan_las_horas_que_la_empresa_aprueba(): void
    {
        $vinculacion = $this->practica(16);

        $this->reportarSemana($vinculacion, 2, 10, ['archivo' => UploadedFile::fake()->image('evidencia.png')])->assertCreated();
        $this->reportarSemana($vinculacion, 1, 6)->assertCreated();
        Notification::assertSentTo($vinculacion->empresa->usuario, EvidenciaPorAprobar::class);

        $this->getJson("/api/vinculaciones/{$vinculacion->id}")
            ->assertJsonPath('horas.aprobadas', 0)
            ->assertJsonPath('horas.semanas_por_revisar', 2);
        $this->assertFalse($vinculacion->fresh()->cumplioHoras());

        // La empresa aprueba las pendientes de una vez y se avisa de las horas cumplidas.
        $this->comoEmpresa($vinculacion->empresa)
            ->postJson("/api/vinculaciones/{$vinculacion->id}/evidencias/aprobacion")
            ->assertOk()
            ->assertJsonPath('aprobadas', 2);

        $vinculacion->refresh();
        $this->assertTrue($vinculacion->cumplioHoras());
        $this->assertSame(EstadoVinculacion::Activa, $vinculacion->estado);
        Notification::assertSentTo($vinculacion->estudiante->usuario, HorasCumplidas::class);
        Notification::assertSentTo($vinculacion->empresa->usuario, PracticanteCumplioHoras::class);

        $this->getJson("/api/vinculaciones/{$vinculacion->id}")
            ->assertJsonPath('horas.aprobadas', 16)
            ->assertJsonPath('horas.requeridas', 16)
            ->assertJsonPath('horas.semanas_por_revisar', 0);
    }

    public function test_la_empresa_devuelve_una_semana_con_comentario_y_el_estudiante_la_corrige(): void
    {
        $vinculacion = $this->practica();
        $id = $this->reportarSemana($vinculacion, 1, 40)->json('id');

        $this->comoEmpresa($vinculacion->empresa);
        $this->patchJson("/api/evidencias/{$id}/revision", ['estado' => 'OBSERVADO'])
            ->assertJsonValidationErrors('comentario');
        $this->patchJson("/api/evidencias/{$id}/revision", ['estado' => 'OBSERVADO', 'comentario' => 'Esa semana fueron 32 horas.'])
            ->assertOk()
            ->assertJsonPath('estado.valor', 'OBSERVADO');
        Notification::assertSentTo($vinculacion->estudiante->usuario, EvidenciaObservada::class);

        Sanctum::actingAs($vinculacion->estudiante->usuario);
        $this->postJson("/api/evidencias/{$id}/correccion", ['horas' => 32, 'actividades' => 'Semana corregida'])
            ->assertOk()
            ->assertJsonPath('estado.valor', 'PENDIENTE')
            ->assertJsonPath('horas', 32);
        $this->postJson("/api/evidencias/{$id}/correccion", ['horas' => 30, 'actividades' => 'Otra vez'])
            ->assertStatus(422);
    }

    public function test_valida_las_evidencias(): void
    {
        $vinculacion = $this->practica();

        $this->reportarSemana($vinculacion, 1, 61)->assertJsonValidationErrors('horas');
        $this->reportarSemana($vinculacion, -1)->assertJsonValidationErrors('semana_inicio');
        $this->reportarSemana($vinculacion, 10)->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'anterior al inicio'));
        $this->reportarSemana($vinculacion, 1, 8, ['archivo' => UploadedFile::fake()->create('virus.exe', 10)])->assertJsonValidationErrors('archivo');
        $this->reportarSemana($vinculacion, 1)->assertCreated();
        $this->reportarSemana($vinculacion, 1)->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'Ya reportaste'));

        // Solo el propio estudiante reporta; solo la empresa de la práctica aprueba.
        $this->comoEmpresa($vinculacion->empresa)
            ->postJson("/api/vinculaciones/{$vinculacion->id}/evidencias", ['semana_inicio' => now()->toDateString(), 'horas' => 4, 'actividades' => 'x'])
            ->assertForbidden();
        $id = $vinculacion->evidencias()->value('id');
        $this->comoEmpresa(Empresa::factory()->create())
            ->patchJson("/api/evidencias/{$id}/revision", ['estado' => 'APROBADO'])
            ->assertForbidden();
        $this->como(Rol::Docente);
        $this->patchJson("/api/evidencias/{$id}/revision", ['estado' => 'APROBADO'])->assertForbidden();
    }

    public function test_la_practica_culmina_cuando_la_empresa_evalua_con_las_horas_cumplidas(): void
    {
        $vinculacion = $this->practica(8);

        $this->comoEmpresa($vinculacion->empresa)
            ->postJson("/api/vinculaciones/{$vinculacion->id}/evaluaciones", ['respuestas' => $this->respuestas('EMPRESA_A_PRACTICANTE')])
            ->assertStatus(422);

        $this->semanaAprobada($vinculacion, 1);

        $this->postJson("/api/vinculaciones/{$vinculacion->id}/evaluaciones", ['respuestas' => $this->respuestas('EMPRESA_A_PRACTICANTE')])
            ->assertCreated();

        $vinculacion->refresh();
        $this->assertSame(EstadoVinculacion::Finalizada, $vinculacion->estado);
        $this->assertTrue($vinculacion->reconocida_como_practica);
        $this->assertSame($vinculacion->empresa->usuario_id, $vinculacion->finalizada_por);
        Notification::assertSentTo($vinculacion->estudiante->usuario, PracticaCulminada::class);
        $this->assertDatabaseHas('auditoria', ['accion' => 'vinculacion.culminada', 'entidad_id' => $vinculacion->id]);
    }

    public function test_otra_empresa_no_evalua(): void
    {
        $vinculacion = $this->practica();
        $this->comoEmpresa(Empresa::factory()->create());

        $this->postJson("/api/vinculaciones/{$vinculacion->id}/evaluaciones", ['respuestas' => $this->respuestas('EMPRESA_A_PRACTICANTE')])->assertForbidden();
    }

    public function test_el_docente_finaliza_con_tres_reportes_y_justifica_si_faltan_horas(): void
    {
        $vinculacion = $this->practica();
        $docente = $this->como(Rol::Docente);

        Reporte::query()->insert(array_fill(0, 2, [
            'autor_id' => $vinculacion->empresa->usuario_id,
            'vinculacion_id' => $vinculacion->id,
            'categoria' => 'PRACTICA',
            'asunto' => 'Inasistencia',
            'descripcion' => 'No asistió',
        ]));

        $this->postJson("/api/docente/vinculaciones/{$vinculacion->id}/finalizacion", ['justificacion' => 'x'])
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'al menos 3'));

        Reporte::query()->insert([
            'autor_id' => $vinculacion->empresa->usuario_id,
            'vinculacion_id' => $vinculacion->id,
            'categoria' => 'PRACTICA',
            'asunto' => 'Inasistencia',
            'descripcion' => 'No asistió otra vez',
        ]);

        $this->postJson("/api/docente/vinculaciones/{$vinculacion->id}/finalizacion")
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'justifica'));

        $this->postJson("/api/docente/vinculaciones/{$vinculacion->id}/finalizacion", ['justificacion' => 'Inasistencias reiteradas sin justificar.'])
            ->assertOk()
            ->assertJsonPath('estado.valor', 'CANCELADA')
            ->assertJsonPath('reconocida_como_practica', false)
            ->assertJsonPath('tutor.id', $docente->id);

        Notification::assertSentTo($vinculacion->estudiante->usuario, PracticaTerminada::class);
        Notification::assertSentTo($vinculacion->empresa->usuario, PracticaTerminada::class);
    }

    public function test_solo_el_docente_tutor_finaliza_si_ya_hay_tutor(): void
    {
        $tutor = Usuario::factory()->rol(Rol::Docente)->create();
        $vinculacion = $this->practica();
        $vinculacion->update(['tutor_id' => $tutor->id]);

        $this->como(Rol::Docente);
        $this->postJson("/api/docente/vinculaciones/{$vinculacion->id}/finalizacion", ['justificacion' => 'x'])->assertForbidden();
    }

    public function test_el_docente_ya_no_cierra_la_practica_cambiando_el_estado(): void
    {
        $vinculacion = $this->practica();
        $this->como(Rol::Docente);

        $this->patchJson("/api/docente/vinculaciones/{$vinculacion->id}", ['estado' => 'FINALIZADA', 'fecha_fin' => now()->toDateString()])
            ->assertJsonValidationErrors('asignarme_tutor');
        $this->assertTrue($vinculacion->fresh()->estaActiva());
    }

    public function test_cada_parte_responde_su_cuestionario_una_vez_y_se_validan_las_opciones(): void
    {
        $vinculacion = $this->practica(8);
        $this->semanaAprobada($vinculacion, 1);
        Sanctum::actingAs($vinculacion->estudiante->usuario);

        $this->getJson("/api/vinculaciones/{$vinculacion->id}/evaluaciones")
            ->assertOk()
            ->assertJsonPath('habilitada', true)
            ->assertJsonPath('cuestionario.tipo.valor', 'PRACTICANTE_A_EMPRESA');

        $malas = [...$this->respuestas('PRACTICANTE_A_EMPRESA'), 'recomendaria' => 'Quizás', 'destacado' => ''];
        $this->postJson("/api/vinculaciones/{$vinculacion->id}/evaluaciones", ['respuestas' => $malas])
            ->assertJsonValidationErrors(['respuestas.recomendaria', 'respuestas.destacado']);

        $this->postJson("/api/vinculaciones/{$vinculacion->id}/evaluaciones", ['respuestas' => $this->respuestas('PRACTICANTE_A_EMPRESA')])
            ->assertCreated()
            ->assertJsonPath('respuestas.0.pregunta', config('evaluaciones.PRACTICANTE_A_EMPRESA.0.texto'));
        $this->postJson("/api/vinculaciones/{$vinculacion->id}/evaluaciones", ['respuestas' => $this->respuestas('PRACTICANTE_A_EMPRESA')])
            ->assertStatus(422);

        // La empresa no lee la evaluación que le hizo el practicante.
        $this->comoEmpresa($vinculacion->empresa)
            ->getJson("/api/vinculaciones/{$vinculacion->id}/evaluaciones")
            ->assertJsonCount(0, 'evaluaciones');

        $this->como(Rol::Docente);
        $this->postJson("/api/vinculaciones/{$vinculacion->id}/evaluaciones", ['respuestas' => $this->respuestas('DOCENTE_A_PRACTICANTE')])
            ->assertCreated()->assertJsonPath('tipo.valor', 'DOCENTE_A_PRACTICANTE');
        $this->getJson("/api/vinculaciones/{$vinculacion->id}/evaluaciones")->assertJsonCount(2, 'evaluaciones');
    }
}
