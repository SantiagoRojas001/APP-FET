<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Empresa;
use App\Models\Parametro;
use App\Models\Usuario;
use App\Models\Vinculacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Acta, plan de trabajo, asistencia y evaluaciones: quién los llena, quién
 * firma y su PDF.
 */
class FormatosPracticaTest extends TestCase
{
    use RefreshDatabase;

    private Vinculacion $practica;

    private Usuario $docente;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::disk('local')->put('firmas/prueba.png', $this->firmaPng()->getContent());

        $this->docente = Usuario::factory()->rol(Rol::Docente)->create();
        $this->practica = Vinculacion::factory()->create(['tutor_id' => $this->docente->id]);
        $this->practica->estudiante->hojaVida()->update(['firma_url' => 'firmas/prueba.png']);
        $this->practica->empresa->update(['representante_nombre' => 'Rosa Díaz', 'representante_documento' => '123456', 'firma_representante_url' => 'firmas/prueba.png']);
    }

    private function url(string $tipo, string $resto = ''): string
    {
        return "/api/vinculaciones/{$this->practica->id}/formatos/{$tipo}{$resto}";
    }

    /** @return array<string, string> */
    private function acta(): array
    {
        return [
            'fecha' => now()->toDateString(), 'hora' => '15:00', 'lugar' => 'Neiva', 'objetivo' => 'Construir un aplicativo',
            'producto' => 'Aplicativo web', 'modo_trabajo' => 'Presencial', 'fecha_plan' => now()->addWeek()->toDateString(),
        ];
    }

    public function test_el_docente_llena_el_acta_las_partes_firman_y_sale_el_pdf(): void
    {
        Sanctum::actingAs($this->practica->estudiante->usuario);
        $this->putJson($this->url('ACTA_INICIO'), $this->acta())->assertForbidden();

        Sanctum::actingAs($this->docente);
        $this->putJson($this->url('ACTA_INICIO'), ['lugar' => 'Neiva'])->assertJsonValidationErrors(['fecha', 'objetivo']);
        $this->putJson($this->url('ACTA_INICIO'), $this->acta())->assertOk();

        // Sin su firma registrada, el docente no firma.
        $this->postJson($this->url('ACTA_INICIO', '/firma'))->assertStatus(422);
        $this->docente->update(['nombre' => 'Ana Tutor', 'documento' => '999', 'firma_url' => 'firmas/prueba.png']);
        $this->postJson($this->url('ACTA_INICIO', '/firma'))->assertOk();

        Sanctum::actingAs($this->practica->estudiante->usuario);
        $this->postJson($this->url('ACTA_INICIO', '/firma'))->assertOk();
        $this->comoEmpresa($this->practica->empresa);
        $this->postJson($this->url('ACTA_INICIO', '/firma'))
            ->assertOk()
            ->assertJsonPath('0.firmas.1.firmada_en', fn ($valor) => $valor !== null)
            ->assertJsonPath('0.puede_firmar', false);
        $this->get($this->url('ACTA_INICIO', '/pdf'))->assertOk()->assertHeader('content-type', 'application/pdf');

        // Cambiar lo diligenciado borra las firmas.
        Sanctum::actingAs($this->docente);
        $this->putJson($this->url('ACTA_INICIO'), [...$this->acta(), 'lugar' => 'Pitalito'])
            ->assertJsonPath('0.firmas.0.firmada_en', null);
    }

    public function test_el_estudiante_llena_el_plan_con_su_cronograma(): void
    {
        Sanctum::actingAs($this->practica->estudiante->usuario);
        $plan = [
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->addWeeks(16)->toDateString(),
            'actividades' => [['competencia' => 'Desarrollo', 'actividad' => 'Módulo de usuarios', 'fecha' => now()->addWeek()->toDateString()]],
        ];

        $this->putJson($this->url('PLAN_TRABAJO'), [...$plan, 'actividades' => []])->assertJsonValidationErrors('actividades');
        $this->putJson($this->url('PLAN_TRABAJO'), $plan)->assertOk()->assertJsonPath('1.disponible', true);
        $this->get($this->url('PLAN_TRABAJO', '/pdf'))->assertOk();

        Sanctum::actingAs($this->docente);
        $this->putJson($this->url('PLAN_TRABAJO'), $plan)->assertForbidden();
    }

    public function test_la_asistencia_sale_de_las_horas_semanales(): void
    {
        Sanctum::actingAs($this->docente);
        $this->get($this->url('REGISTRO_ASISTENCIA', '/pdf'))->assertNotFound();

        $this->practica->evidencias()->create(['semana_inicio' => now()->startOfWeek()->toDateString(), 'horas' => 24, 'actividades' => 'Pruebas', 'estado' => 'APROBADO']);
        $this->get($this->url('REGISTRO_ASISTENCIA', '/pdf'))->assertOk();
    }

    public function test_la_evaluacion_de_convenio_se_llena_al_completar_las_horas(): void
    {
        $datos = ['nombre' => 'Convenio de práctica', 'tipo' => 'Práctica', 'objetivos' => 'Bueno (3)', 'continuar' => 'Sí'];
        Sanctum::actingAs($this->docente);
        $this->putJson($this->url('EVALUACION_CONVENIO'), $datos)->assertStatus(422);

        $this->practica->update(['horas_cumplidas_en' => now()]);
        $this->putJson($this->url('EVALUACION_CONVENIO'), $datos)->assertOk();
        $this->get($this->url('EVALUACION_CONVENIO', '/pdf'))->assertOk();
    }

    public function test_la_empresa_registra_su_tutor_y_otra_no(): void
    {
        $tutor = ['nombre' => 'Luis Mora', 'cargo' => 'Líder', 'correo' => 'luis@empresa.co', 'telefono' => '3001112233'];

        $this->comoEmpresa($this->practica->empresa);
        $this->putJson("/api/empresa/vinculaciones/{$this->practica->id}/tutor", $tutor)
            ->assertOk()
            ->assertJsonPath('tutor_empresa.nombre', 'Luis Mora');

        $this->comoEmpresa(Empresa::factory()->create());
        $this->putJson("/api/empresa/vinculaciones/{$this->practica->id}/tutor", $tutor)->assertForbidden();
    }

    public function test_el_admin_registra_el_snies_del_programa(): void
    {
        $this->como(Rol::Admin);
        $programa = $this->practica->estudiante->programa;

        $this->putJson("/api/admin/programas/{$programa->id}", ['snies' => '12345', 'resolucion' => 'MEN 1 de 2020'])->assertOk();
        $this->assertSame('12345', $programa->fresh()->snies);
        $this->assertSame(384, Parametro::horasRequeridas());
    }
}
