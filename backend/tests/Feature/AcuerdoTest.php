<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Empresa;
use App\Models\Usuario;
use App\Notifications\AcuerdoPorRevisar;
use App\Notifications\AcuerdoRevisado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * La empresa sube su acuerdo, la administración lo aprueba y el director lo consulta.
 */
class AcuerdoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('local');
    }

    public function test_la_empresa_sube_su_acuerdo_y_la_administracion_lo_aprueba(): void
    {
        $admin = Usuario::factory()->rol(Rol::Admin)->create();
        $empresa = Empresa::factory()->create();
        $this->comoEmpresa($empresa);

        $id = $this->postJson('/api/empresa/acuerdos', [
            'vigencia_inicio' => now()->toDateString(),
            'vigencia_fin' => now()->addYear()->toDateString(),
            'archivo' => UploadedFile::fake()->create('acuerdo.pdf', 100, 'application/pdf'),
        ])->assertCreated()->assertJsonPath('estado.valor', 'PENDIENTE')->json('id');
        Notification::assertSentTo($admin, AcuerdoPorRevisar::class);
        $this->assertFalse($empresa->tieneAcuerdoVigente());

        // El director lo ve, pero no lo aprueba
        Sanctum::actingAs(Usuario::factory()->rol(Rol::Director)->create());
        $this->getJson('/api/director/acuerdos')->assertJsonCount(1);
        $this->get("/api/acuerdos/{$id}/archivo")->assertOk();
        $this->patchJson("/api/admin/acuerdos/{$id}/revision", ['aprobar' => true])->assertForbidden();

        Sanctum::actingAs($admin);
        $this->patchJson("/api/admin/acuerdos/{$id}/revision", ['aprobar' => false])->assertJsonValidationErrors('observacion');
        $this->patchJson("/api/admin/acuerdos/{$id}/revision", ['aprobar' => true])->assertOk()->assertJsonPath('vigente', true);
        $this->patchJson("/api/admin/acuerdos/{$id}/revision", ['aprobar' => true])->assertUnprocessable();

        Notification::assertSentTo($empresa->usuario, AcuerdoRevisado::class);
        $this->assertTrue($empresa->tieneAcuerdoVigente());

        // Otra empresa no descarga el acuerdo
        $this->comoEmpresa(Empresa::factory()->create())->get("/api/acuerdos/{$id}/archivo")->assertForbidden();
    }
}
