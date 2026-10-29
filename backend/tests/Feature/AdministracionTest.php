<?php

namespace Tests\Feature;

use App\Enums\EstadoPostulacion;
use App\Enums\EstadoVacante;
use App\Enums\Rol;
use App\Models\Empresa;
use App\Models\PeriodoAcademico;
use App\Models\Postulacion;
use App\Models\Vacante;
use App\Models\Vinculacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Periodos académicos, informes, bitácora de auditoría y hoja de vida en PDF.
 */
class AdministracionTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_periodo_recoge_las_practicas_de_sus_fechas_y_al_cerrarse_cierra_las_vacantes(): void
    {
        $dentro = Vinculacion::factory()->create(['fecha_inicio' => '2026-08-10']);
        $fuera = Vinculacion::factory()->create(['fecha_inicio' => '2026-03-10']);
        $vacante = Vacante::factory()->create();
        $this->como(Rol::Admin);

        $id = $this->postJson('/api/admin/periodos', ['nombre' => '2026-2', 'fecha_inicio' => '2026-07-15', 'fecha_fin' => '2026-12-15'])
            ->assertCreated()->assertJsonPath('vinculaciones', 1)->json('id');

        $this->assertSame($id, $dentro->fresh()->periodo_id);
        $this->assertNull($fuera->fresh()->periodo_id);

        $this->postJson('/api/admin/periodos', ['nombre' => '2026-1', 'fecha_inicio' => '2026-01-15', 'fecha_fin' => '2026-08-01'])
            ->assertJsonValidationErrors('fecha_inicio');

        $this->postJson("/api/admin/periodos/{$id}/cierre")->assertOk()->assertJsonPath('cerrado_en', fn ($v) => $v !== null);
        $this->assertSame(EstadoVacante::Cerrada, $vacante->fresh()->estado);
        $this->postJson("/api/admin/periodos/{$id}/cierre")->assertStatus(422);
    }

    public function test_la_practica_autorizada_queda_en_el_periodo_abierto(): void
    {
        Notification::fake();
        $periodo = PeriodoAcademico::create(['nombre' => '2026-2', 'fecha_inicio' => now()->subMonth(), 'fecha_fin' => now()->addMonths(3)]);
        $postulacion = Postulacion::factory()->create();

        $this->comoEmpresa($postulacion->vacante->empresa)
            ->patchJson("/api/empresa/postulaciones/{$postulacion->id}", ['decision' => 'SELECCIONADA'])
            ->assertOk();
        $this->assertNotNull($postulacion->fresh()->decidida_en);

        $this->como(Rol::Docente);
        $this->patchJson("/api/docente/postulaciones/{$postulacion->id}", ['decision' => 'ACEPTADA', 'fecha_inicio' => now()->addWeek()->toDateString()])
            ->assertOk();

        $vinculacion = Vinculacion::sole();
        $this->assertSame($periodo->id, $vinculacion->periodo_id);
        $this->assertSame(now()->addWeek()->toDateString(), $vinculacion->fecha_inicio->toDateString());
        $this->assertNotNull($postulacion->fresh()->revisada_en);
    }

    public function test_el_informe_resume_las_practicas_y_se_exporta_a_excel(): void
    {
        $empresa = Empresa::factory()->create(['razon_social' => 'Acme']);
        Vinculacion::factory()->count(2)->for($empresa)->create();
        Postulacion::factory()->create(['estado' => EstadoPostulacion::Aceptada, 'decidida_en' => now(), 'revisada_en' => now()]);
        Postulacion::factory()->create(['estado' => EstadoPostulacion::Rechazada, 'decidida_en' => now(), 'revisada_en' => now()]);
        Postulacion::factory()->create(['estado' => EstadoPostulacion::Descartada, 'decidida_en' => now()]);
        $this->como(Rol::Admin);

        $this->getJson('/api/admin/informes')
            ->assertOk()
            ->assertJsonPath('empresas_con_mas_practicantes.0.empresa', 'Acme')
            ->assertJsonPath('empresas_con_mas_practicantes.0.practicantes', 2)
            ->assertJsonPath('postulaciones.tasa_autorizacion_docente', 50)
            ->assertJsonPath('postulaciones.seleccionadas_por_empresa', 2);

        $respuesta = $this->get('/api/admin/informes/exportar')->assertOk();
        $this->assertStringContainsString('spreadsheetml', $respuesta->headers->get('content-type'));
        $this->assertStringContainsString('informe-practicas-todos.xlsx', $respuesta->headers->get('content-disposition'));
    }

    public function test_la_bitacora_registra_las_decisiones_con_su_autor(): void
    {
        Notification::fake();
        $postulacion = Postulacion::factory()->estado(EstadoPostulacion::Seleccionada)->create();
        $docente = $this->como(Rol::Docente);

        $this->patchJson("/api/docente/postulaciones/{$postulacion->id}", ['decision' => 'RECHAZADA', 'observacion' => 'Falta el semestre'])->assertOk();

        $this->como(Rol::Admin);
        $this->getJson('/api/admin/auditoria?entidad=postulacion')
            ->assertJsonPath('data.0.accion', 'postulacion.no_autorizada')
            ->assertJsonPath('data.0.usuario.id', $docente->id)
            ->assertJsonPath('data.0.detalle.observacion', 'Falta el semestre');
    }

    public function test_la_hoja_de_vida_se_descarga_en_pdf(): void
    {
        $estudiante = $this->estudiante(8);

        $this->comoEstudiante($estudiante);
        $this->get('/api/estudiante/hoja-vida.pdf')->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->comoEmpresa(Empresa::factory()->create());
        $this->get("/api/estudiantes/{$estudiante->id}/hoja-vida.pdf")->assertOk();

        $this->comoEstudiante($this->estudiante(8));
        $this->get("/api/estudiantes/{$estudiante->id}/hoja-vida.pdf")->assertForbidden();
    }

    public function test_el_admin_ajusta_las_horas_de_un_programa(): void
    {
        $programa = $this->programaConRegla();
        $this->como(Rol::Admin);

        $this->patchJson("/api/admin/programas/{$programa->id}", ['horas_requeridas' => 640])->assertOk()->assertJsonPath('horas_requeridas', 640);
    }
}
