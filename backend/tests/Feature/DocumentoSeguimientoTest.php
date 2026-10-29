<?php

namespace Tests\Feature;

use App\Enums\EstadoVinculacion;
use App\Enums\Rol;
use App\Models\Empresa;
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
}
