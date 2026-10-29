<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Usuario;
use App\Models\Vinculacion;
use App\Notifications\ReporteAtendido;
use App\Notifications\ReporteRecibido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Reportes de problemas y notificaciones dentro de la app.
 */
class ReporteTest extends TestCase
{
    use RefreshDatabase;

    public function test_cualquier_usuario_reporta_y_la_administracion_responde(): void
    {
        $admin = Usuario::factory()->rol(Rol::Admin)->create();
        $docente = $this->como(Rol::Docente);

        $id = $this->postJson('/api/reportes', [
            'categoria' => 'FALLA_PLATAFORMA',
            'asunto' => 'No carga el PDF',
            'descripcion' => 'Al abrir el informe aparece un error.',
        ])->assertCreated()->assertJsonPath('estado.valor', 'ABIERTO')->json('id');

        $this->assertDatabaseHas('notifications', ['notifiable_id' => $admin->id, 'type' => ReporteRecibido::class]);
        $this->getJson('/api/reportes')->assertJsonCount(1);

        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/reportes')->assertJsonPath('data.0.id', $id);
        $this->patchJson("/api/admin/reportes/{$id}", ['estado' => 'RESUELTO'])->assertJsonValidationErrors('respuesta');
        $this->patchJson("/api/admin/reportes/{$id}", ['estado' => 'RESUELTO', 'respuesta' => 'Ya quedó corregido.'])
            ->assertOk()->assertJsonPath('estado.valor', 'RESUELTO');

        Sanctum::actingAs($docente);
        $this->getJson('/api/notificaciones')
            ->assertJsonPath('no_leidas', 1)
            ->assertJsonPath('data.0.titulo', 'Tu reporte está resuelto');
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $docente->id, 'type' => ReporteAtendido::class]);

        $this->patchJson('/api/notificaciones/leidas')->assertNoContent();
        $this->getJson('/api/notificaciones/no-leidas')->assertJsonPath('no_leidas', 0);
    }

    public function test_un_reporte_de_practica_solo_se_liga_a_una_vinculacion_propia_y_solo_la_fet_lo_ve(): void
    {
        $vinculacion = Vinculacion::factory()->create();
        $ajena = Vinculacion::factory()->create();
        $datos = ['categoria' => 'PRACTICA', 'asunto' => 'Problema', 'descripcion' => 'Detalle'];

        $this->comoEmpresa($vinculacion->empresa);
        $this->postJson('/api/reportes', [...$datos, 'vinculacion_id' => $ajena->id])->assertForbidden();
        $this->postJson('/api/reportes', [...$datos, 'vinculacion_id' => $vinculacion->id])->assertCreated();

        $this->getJson("/api/vinculaciones/{$vinculacion->id}/reportes")->assertForbidden();
        $this->getJson("/api/vinculaciones/{$vinculacion->id}")->assertJsonMissingPath('reportes');

        $this->como(Rol::Docente);
        $this->getJson("/api/vinculaciones/{$vinculacion->id}/reportes")->assertJsonCount(1);
        $this->getJson("/api/vinculaciones/{$vinculacion->id}")->assertJsonPath('reportes', 1);
    }

    public function test_solo_el_admin_atiende_reportes(): void
    {
        $this->como(Rol::Docente);

        $this->getJson('/api/admin/reportes')->assertForbidden();
    }
}
