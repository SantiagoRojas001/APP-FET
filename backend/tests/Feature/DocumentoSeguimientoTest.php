<?php

namespace Tests\Feature;

use App\Enums\EstadoVinculacion;
use App\Enums\Rol;
use App\Models\Empresa;
use App\Models\Estudiante;
use App\Models\Programa;
use App\Models\Usuario;
use App\Models\Vinculacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentoSeguimientoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function subir(Vinculacion $vinculacion, array $datos = [])
    {
        return $this->postJson("/api/vinculaciones/{$vinculacion->id}/documentos", [
            'tipo' => 'ARL',
            'periodo_inicio' => now()->subWeek()->toDateString(),
            'periodo_fin' => now()->toDateString(),
            'archivo' => UploadedFile::fake()->create('asistencia.pdf', 200, 'application/pdf'),
            ...$datos,
        ]);
    }

    public function test_el_estudiante_sube_un_documento_y_el_archivo_queda_privado(): void
    {
        $vinculacion = Vinculacion::factory()->create();
        Sanctum::actingAs($vinculacion->estudiante->usuario);

        $respuesta = $this->subir($vinculacion)->assertCreated()->assertJsonPath('estado_revision.valor', 'PENDIENTE');

        $documento = $vinculacion->documentos()->sole();
        Storage::disk('local')->assertExists($documento->archivo_url);
        $this->assertStringNotContainsString($documento->archivo_url, $respuesta->getContent());
    }

    public function test_solo_acepta_pdf(): void
    {
        $vinculacion = Vinculacion::factory()->create();
        Sanctum::actingAs($vinculacion->estudiante->usuario);

        $this->subir($vinculacion, ['archivo' => UploadedFile::fake()->create('virus.exe', 10)])
            ->assertJsonValidationErrors('archivo');
    }

    public function test_no_se_suben_documentos_a_una_vinculacion_finalizada_ni_ajena(): void
    {
        $finalizada = Vinculacion::factory()->create(['estado' => EstadoVinculacion::Finalizada]);
        Sanctum::actingAs($finalizada->estudiante->usuario);
        $this->subir($finalizada)->assertForbidden();

        $ajena = Vinculacion::factory()->create();
        $this->subir($ajena)->assertForbidden();
    }

    public function test_solo_el_docente_revisa_y_la_empresa_solo_consulta(): void
    {
        $vinculacion = Vinculacion::factory()->create();
        Sanctum::actingAs($vinculacion->estudiante->usuario);
        $id = $this->subir($vinculacion)->json('id');

        $this->comoEmpresa(Empresa::factory()->create());
        $this->patchJson("/api/documentos/{$id}/revision", ['estado_revision' => 'APROBADO'])->assertForbidden();
        $this->getJson("/api/documentos/{$id}/archivo")->assertForbidden();

        // La empresa de la práctica descarga el documento, pero ya no lo revisa.
        $this->comoEmpresa($vinculacion->empresa);
        $this->get("/api/documentos/{$id}/archivo")->assertOk();
        $this->patchJson("/api/documentos/{$id}/revision", ['estado_revision' => 'APROBADO'])->assertForbidden();

        $this->como(Rol::Docente);
        $this->patchJson("/api/documentos/{$id}/revision", ['estado_revision' => 'APROBADO'])
            ->assertOk()
            ->assertJsonPath('estado_revision.valor', 'APROBADO');
        $this->get("/api/documentos/{$id}/archivo")->assertOk()->assertDownload();
    }

    public function test_un_docente_de_otro_programa_no_revisa_documentos(): void
    {
        $vinculacion = Vinculacion::factory()->create();
        Sanctum::actingAs($vinculacion->estudiante->usuario);
        $id = $this->subir($vinculacion)->json('id');

        Sanctum::actingAs(Usuario::factory()->create([
            'rol' => Rol::Docente,
            'programa_id' => Programa::factory()->create()->id,
        ]));

        $this->patchJson("/api/documentos/{$id}/revision", ['estado_revision' => 'APROBADO'])->assertForbidden();
    }

    public function test_el_docente_y_la_empresa_tambien_suben_documentos(): void
    {
        $vinculacion = Vinculacion::factory()->create();

        $this->como(Rol::Docente);
        $this->subir($vinculacion, ['tipo' => 'BITACORA'])
            ->assertCreated()
            ->assertJsonPath('estado_revision.valor', 'APROBADO')
            ->assertJsonPath('subido_por.valor', 'DOCENTE');

        $this->comoEmpresa($vinculacion->empresa);
        $id = $this->subir($vinculacion, ['tipo' => 'OTRO'])
            ->assertCreated()
            ->assertJsonPath('estado_revision.valor', 'PENDIENTE')
            ->json('id');

        $this->postJson("/api/documentos/{$id}/aprobacion-empresa")
            ->assertOk()
            ->assertJsonPath('aprobado_empresa_en', fn ($valor) => $valor !== null);

        $this->comoEmpresa(Empresa::factory()->create());
        $this->subir($vinculacion)->assertForbidden();
        $this->postJson("/api/documentos/{$id}/aprobacion-empresa")->assertForbidden();
    }

    public function test_los_materiales_del_docente_solo_los_ve_su_grupo(): void
    {
        $docente = $this->como(Rol::Docente);
        $id = $this->post('/api/docente/materiales', [
            'titulo' => 'Guía de informes',
            'archivo' => UploadedFile::fake()->create('guia.pdf', 50, 'application/pdf'),
        ])->assertCreated()->json('id');

        $suyo = Estudiante::factory()->create(['tutor_id' => $docente->id]);
        $ajeno = Estudiante::factory()->create();

        $this->comoEstudiante($suyo)->getJson('/api/estudiante/materiales')->assertJsonPath('0.id', $id);
        $this->get("/api/materiales/{$id}/archivo")->assertOk();

        $this->comoEstudiante($ajeno)->getJson('/api/estudiante/materiales')->assertJsonCount(0);
        $this->get("/api/materiales/{$id}/archivo")->assertForbidden();
    }
}
