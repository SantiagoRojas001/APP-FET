<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Empresa;
use App\Models\Vacante;
use App\Models\Vinculacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AutorizacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_las_rutas_privadas_exigen_sesion(): void
    {
        $this->getJson('/api/auth/yo')->assertUnauthorized();
        $this->getJson('/api/estudiante/citas')->assertUnauthorized();
    }

    /** @return array<string, array{Rol, string}> */
    public static function rutasDeOtroRol(): array
    {
        return [
            'docente en rutas de estudiante' => [Rol::Docente, '/api/estudiante/citas'],
            'estudiante en rutas de docente' => [Rol::Estudiante, '/api/docente/hojas-vida'],
            'empresa en rutas de docente' => [Rol::Empresa, '/api/docente/vacantes'],
            'empresa en rutas de director' => [Rol::Empresa, '/api/director/estudiantes'],
            'docente en rutas de director' => [Rol::Docente, '/api/director/cartas'],
            'director en rutas de admin' => [Rol::Director, '/api/admin/acuerdos'],
            'empresa buscando estudiantes' => [Rol::Empresa, '/api/estudiantes'],
            'estudiante en rutas de empresa' => [Rol::Estudiante, '/api/empresa/vacantes'],
            'empresa en rutas de admin' => [Rol::Empresa, '/api/admin/usuarios'],
            'docente en rutas de admin' => [Rol::Docente, '/api/admin/empresas'],
            'estudiante viendo otros perfiles' => [Rol::Estudiante, '/api/estudiantes'],
        ];
    }

    #[DataProvider('rutasDeOtroRol')]
    public function test_cada_rol_solo_accede_a_sus_rutas(Rol $rol, string $ruta): void
    {
        $this->como($rol);

        $this->getJson($ruta)->assertForbidden();
    }

    public function test_una_empresa_no_puede_editar_vacantes_ajenas(): void
    {
        $vacante = Vacante::factory()->create();

        $this->comoEmpresa(Empresa::factory()->create())
            ->patchJson("/api/empresa/vacantes/{$vacante->id}", ['titulo' => 'Cambiado'])
            ->assertNotFound();

        $this->assertNotSame('Cambiado', $vacante->fresh()->titulo);
    }

    public function test_el_admin_valida_una_empresa_y_queda_registrado_quien_lo_hizo(): void
    {
        $empresa = Empresa::factory()->pendiente()->create();
        $admin = $this->como(Rol::Admin);

        $this->patchJson("/api/admin/empresas/{$empresa->id}/validacion", ['estado_validacion' => 'VALIDADA'])
            ->assertOk()
            ->assertJsonPath('estado_validacion.valor', 'VALIDADA');

        $this->assertSame($admin->id, $empresa->fresh()->validada_por);
    }

    public function test_al_desactivar_un_usuario_se_cierran_sus_sesiones(): void
    {
        $estudiante = $this->estudiante(5);
        $estudiante->usuario->createToken('prueba');
        $this->como(Rol::Admin);

        $this->patchJson("/api/admin/usuarios/{$estudiante->usuario_id}", ['activo' => false])->assertOk();

        $this->assertSame(0, $estudiante->usuario->tokens()->count());
    }

    public function test_el_admin_configura_el_promedio_minimo_y_los_semestres_habilitados(): void
    {
        $this->como(Rol::Admin);

        $this->getJson('/api/admin/parametros')
            ->assertOk()
            ->assertJsonPath('promedio_minimo', 4.2)
            ->assertJsonPath('semestres_habilitados', [3, 5, 9]);

        $this->putJson('/api/admin/parametros', ['promedio_minimo' => '4,0', 'semestres_habilitados' => [9, 4]])
            ->assertOk()
            ->assertJsonPath('promedio_minimo', 4)
            ->assertJsonPath('semestres_habilitados', [4, 9]);

        $this->putJson('/api/admin/parametros', ['promedio_minimo' => 6, 'semestres_habilitados' => []])
            ->assertJsonValidationErrors(['promedio_minimo', 'semestres_habilitados']);
    }

    public function test_el_admin_lista_usuarios_con_estudiantes_en_pasantia(): void
    {
        Vinculacion::factory()->count(2)->create();
        $this->como(Rol::Admin);

        // Con Model::shouldBeStrict activo, una carga perezosa haría fallar la petición.
        $this->getJson('/api/admin/usuarios?rol=ESTUDIANTE')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.vinculacion_activa_id', fn ($id) => is_int($id));
    }
}
