<?php

namespace Tests\Feature;

use App\Enums\EstadoCita;
use App\Enums\Rol;
use App\Models\Cita;
use App\Models\Empresa;
use App\Models\Usuario;
use App\Models\Vinculacion;
use App\Support\Periodo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Periodo académico calculado, informes, bitácora de auditoría y hoja de vida en PDF.
 */
class AdministracionTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_periodo_se_calcula_con_la_fecha_de_inicio_y_filtra_los_informes(): void
    {
        $segundo = Vinculacion::factory()->create(['fecha_inicio' => '2026-08-10']);
        Vinculacion::factory()->create(['fecha_inicio' => '2026-03-10']);
        $this->como(Rol::Admin);

        $this->getJson("/api/vinculaciones/{$segundo->id}")->assertJsonPath('periodo', '2026-2');
        $this->getJson('/api/admin/informes?periodo=2026-1')
            ->assertOk()
            ->assertJsonPath('periodo', '2026-1')
            ->assertJsonPath('practicantes_por_programa.0.total', 1);
        $this->getJson('/api/admin/informes?periodo=2026-3')->assertJsonValidationErrors('periodo');
    }

    public function test_la_practica_presentada_empieza_en_la_fecha_que_acepta_la_empresa(): void
    {
        Notification::fake();
        $cita = Cita::factory()->create();

        $this->comoEmpresa($cita->empresa)
            ->postJson("/api/empresa/citas/{$cita->id}/decision", ['aceptar' => true, 'fecha_inicio' => now()->addWeek()->toDateString()])
            ->assertOk();

        $director = Usuario::factory()->rol(Rol::Director)->create(['programa_id' => $cita->estudiante->programa_id]);
        Sanctum::actingAs($director);
        $this->postJson("/api/director/cartas/{$cita->id}")->assertOk();

        $vinculacion = Vinculacion::sole();
        $this->assertSame(now()->addWeek()->toDateString(), $vinculacion->fecha_inicio->toDateString());
        $this->assertSame(Periodo::deFecha(now()->addWeek()), $vinculacion->periodo());
    }

    public function test_el_informe_resume_las_practicas_y_se_exporta_a_excel(): void
    {
        $empresa = Empresa::factory()->create(['razon_social' => 'Acme']);
        Vinculacion::factory()->count(2)->for($empresa)->create();
        Cita::factory()->create(['estado' => EstadoCita::Presentado, 'decidida_en' => now(), 'presentada_en' => now()]);
        Cita::factory()->create(['estado' => EstadoCita::Rechazado, 'decidida_en' => now()]);
        $this->como(Rol::Admin);

        $this->getJson('/api/admin/informes')
            ->assertOk()
            ->assertJsonPath('empresas_con_mas_practicantes.0.empresa', 'Acme')
            ->assertJsonPath('empresas_con_mas_practicantes.0.practicantes', 2)
            ->assertJsonPath('citas.tasa_aceptacion_empresa', 50)
            ->assertJsonPath('citas.aceptados_por_empresa', 1)
            ->assertJsonPath('citas.cartas_enviadas', 1)
            ->assertJsonPath('hojas_vida.aprobadas', 2);

        $respuesta = $this->get('/api/admin/informes/exportar')->assertOk();
        $this->assertStringContainsString('spreadsheetml', $respuesta->headers->get('content-type'));
        $this->assertStringContainsString('informe-practicas-todos.xlsx', $respuesta->headers->get('content-disposition'));
    }

    public function test_la_bitacora_registra_las_decisiones_con_su_autor(): void
    {
        Notification::fake();
        $estudiante = $this->estudiante(9);
        $estudiante->hojaVida()->update(['estado' => 'EN_REVISION']);
        $docente = $this->como(Rol::Docente);

        $this->patchJson("/api/docente/hojas-vida/{$estudiante->id}", ['aprobar' => false, 'observaciones' => 'Falta una referencia'])->assertOk();

        $this->como(Rol::Admin);
        $this->getJson('/api/admin/auditoria?entidad=estudiante')
            ->assertJsonPath('data.0.accion', 'hoja_vida.observada')
            ->assertJsonPath('data.0.usuario.id', $docente->id)
            ->assertJsonPath('data.0.detalle.observaciones', 'Falta una referencia');
    }

    public function test_la_hoja_de_vida_se_descarga_en_pdf(): void
    {
        $estudiante = $this->estudiante(8);

        $this->comoEstudiante($estudiante);
        $this->get('/api/estudiante/hoja-vida.pdf')->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->comoEmpresa(Empresa::factory()->create());
        $this->get("/api/estudiantes/{$estudiante->id}/hoja-vida.pdf")->assertForbidden();
        $estudiante->hojaVida()->update(['estado' => 'APROBADA']);
        $this->get("/api/estudiantes/{$estudiante->id}/hoja-vida.pdf")->assertOk();

        $this->comoEstudiante($this->estudiante(8));
        $this->get("/api/estudiantes/{$estudiante->id}/hoja-vida.pdf")->assertForbidden();
    }

    public function test_el_resumen_muestra_lo_pendiente_y_las_cifras_por_programa(): void
    {
        Empresa::factory()->create(['estado_validacion' => 'PENDIENTE']);
        Vinculacion::factory()->create(['fecha_inicio' => now()->toDateString()]);
        $this->como(Rol::Admin);

        $this->getJson('/api/admin/resumen')
            ->assertOk()
            ->assertJsonPath('periodo', Periodo::actual())
            ->assertJsonPath('pendientes.empresas_por_validar', 1)
            ->assertJsonPath('cifras.practicas_activas', 1)
            ->assertJsonPath('cifras.practicas_del_periodo', 1)
            ->assertJsonPath('programas', fn (array $p) => collect($p)->sum('en_practica') === 1);
    }
}
