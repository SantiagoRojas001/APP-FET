<?php

namespace Tests\Feature;

use App\Enums\EstadoValidacion;
use App\Enums\Rol;
use App\Models\Auditoria;
use App\Models\Empresa;
use App\Models\Programa;
use App\Models\Usuario;
use App\Notifications\EmpresaPorValidar;
use App\Notifications\EmpresaValidada;
use App\Notifications\InvitacionCuenta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CuentasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function datosEstudiante(Programa $programa, array $cambios = []): array
    {
        return [
            'correo' => 'nuevo.estudiante@fet.edu.co',
            'programa_id' => $programa->id,
            'codigo' => '20269999',
            'documento' => '1075000000',
            'nombres' => 'Ana',
            'apellidos' => 'Gómez',
            'semestre_actual' => 4,
            ...$cambios,
        ];
    }

    /** Activa la cuenta con el token que llegó en la invitación. */
    private function activar(Usuario $usuario, array $cambios = [])
    {
        $token = null;
        Notification::assertSentTo($usuario, InvitacionCuenta::class, function (InvitacionCuenta $aviso) use (&$token) {
            $token = (fn () => $this->token)->call($aviso);

            return true;
        });

        return $this->postJson('/api/auth/activar-cuenta', [
            'token' => $token,
            'correo' => $usuario->correo,
            'password' => 'clave1234',
            'password_confirmation' => 'clave1234',
            'acepta_politica' => true,
            ...$cambios,
        ]);
    }

    public function test_los_estudiantes_no_se_registran_por_su_cuenta(): void
    {
        $this->postJson('/api/registro/estudiante', [])->assertNotFound();
    }

    public function test_una_empresa_se_registra_y_espera_la_validacion_de_la_fet(): void
    {
        Notification::fake();
        $admin = Usuario::factory()->rol(Rol::Admin)->create();
        $datos = [
            'correo' => 'Talento@Acme.co',
            'password' => 'clave1234',
            'password_confirmation' => 'clave1234',
            'nit' => '900.123.456-7',
            'razon_social' => 'Acme S.A.S.',
            'contacto_nombre' => 'Ana Gómez',
            'contacto_telefono' => '3001234567',
            'modalidad_interes' => 'PASANTIA',
            'acepta_politica' => true,
        ];

        $this->postJson('/api/registro/empresa', [...$datos, 'acepta_politica' => false])->assertJsonValidationErrors('acepta_politica');
        $this->postJson('/api/registro/empresa', $datos)->assertCreated();
        $this->postJson('/api/registro/empresa', $datos)->assertJsonValidationErrors(['correo', 'nit']);

        $empresa = Empresa::sole();
        $this->assertSame(EstadoValidacion::Pendiente, $empresa->estado_validacion);
        $this->assertDatabaseHas('consentimiento_datos', ['usuario_id' => $empresa->usuario_id]);
        Notification::assertSentTo($admin, EmpresaPorValidar::class);

        // No ingresa hasta que la administración la valide; al validarla se le avisa.
        $this->postJson('/api/auth/login', ['correo' => 'talento@acme.co', 'password' => 'clave1234'])
            ->assertJsonValidationErrors('correo');

        Sanctum::actingAs($admin);
        $this->patchJson("/api/admin/empresas/{$empresa->id}/validacion", ['estado_validacion' => 'VALIDADA'])->assertOk();
        Notification::assertSentTo($empresa->usuario, EmpresaValidada::class);

        $this->postJson('/api/auth/login', ['correo' => 'talento@acme.co', 'password' => 'clave1234'])->assertOk();
    }

    public function test_el_admin_crea_un_estudiante_que_activa_su_cuenta_con_la_invitacion(): void
    {
        $programa = Programa::factory()->create(['total_semestres' => 10]);
        $this->como(Rol::Admin);

        $this->postJson('/api/admin/estudiantes', $this->datosEstudiante($programa))->assertCreated();

        $usuario = Usuario::where('correo', 'nuevo.estudiante@fet.edu.co')->firstOrFail();
        $this->assertSame(Rol::Estudiante, $usuario->rol);
        $this->assertNotNull($usuario->estudiante->hojaVida);
        $this->assertDatabaseMissing('consentimiento_datos', ['usuario_id' => $usuario->id]);
        $this->assertTrue(Auditoria::where('accion', 'usuario.creado')->where('entidad_id', $usuario->id)->exists());

        $this->activar($usuario, ['acepta_politica' => false])->assertJsonValidationErrors('acepta_politica');
        $this->activar($usuario)->assertOk();

        $this->assertDatabaseHas('consentimiento_datos', ['usuario_id' => $usuario->id, 'version_politica' => config('fet.version_politica')]);
        $this->postJson('/api/auth/login', ['correo' => $usuario->correo, 'password' => 'clave1234'])->assertOk();
    }

    public function test_los_estudiantes_y_docentes_usan_correo_institucional(): void
    {
        $programa = Programa::factory()->create(['total_semestres' => 6]);
        $this->como(Rol::Admin);

        $this->postJson('/api/admin/estudiantes', $this->datosEstudiante($programa, ['correo' => 'ana@gmail.com', 'semestre_actual' => 9]))
            ->assertJsonValidationErrors(['correo', 'semestre_actual']);
        $this->postJson('/api/admin/usuarios', ['correo' => 'profe@gmail.com', 'rol' => 'DOCENTE'])
            ->assertJsonValidationErrors('correo');
        $this->postJson('/api/admin/usuarios', ['correo' => 'profe@fet.edu.co', 'rol' => 'ESTUDIANTE'])
            ->assertJsonValidationErrors('rol');
    }

    public function test_la_empresa_invitada_queda_validada_y_puede_ingresar_al_activarse(): void
    {
        $this->como(Rol::Admin);

        $this->postJson('/api/admin/empresas', [
            'correo' => 'rrhh@empresa.co',
            'nit' => '901.111.222-3',
            'razon_social' => 'Empresa de Prueba S.A.S.',
            'contacto_nombre' => 'Laura Díaz',
            'contacto_telefono' => '3100000000',
            'modalidad_interes' => 'PASANTIA',
        ])->assertCreated()->assertJsonPath('estado_validacion.valor', 'VALIDADA')->assertJsonPath('pendiente_activacion', true);

        $usuario = Usuario::where('correo', 'rrhh@empresa.co')->firstOrFail();
        $this->activar($usuario)->assertOk();
        $this->postJson('/api/auth/login', ['correo' => 'rrhh@empresa.co', 'password' => 'clave1234'])->assertOk();
    }

    public function test_una_invitacion_invalida_no_activa_y_se_puede_reenviar_hasta_activar(): void
    {
        $this->como(Rol::Admin);
        $this->postJson('/api/admin/usuarios', ['correo' => 'docente.nuevo@fet.edu.co', 'rol' => 'DOCENTE'])->assertCreated();
        $docente = Usuario::where('correo', 'docente.nuevo@fet.edu.co')->firstOrFail();

        $this->postJson('/api/auth/activar-cuenta', [
            'token' => 'inventado', 'correo' => $docente->correo, 'password' => 'clave1234', 'password_confirmation' => 'clave1234', 'acepta_politica' => true,
        ])->assertJsonValidationErrors('token');

        $this->postJson("/api/admin/usuarios/{$docente->id}/invitacion")->assertOk();
        Notification::assertSentToTimes($docente, InvitacionCuenta::class, 2);

        $this->activar($docente)->assertOk();
        $this->postJson("/api/admin/usuarios/{$docente->id}/invitacion")->assertStatus(422);
    }

    public function test_solo_el_admin_corrige_datos_de_estudiantes_y_empresas(): void
    {
        $estudiante = $this->estudiante(5);
        $empresa = Empresa::factory()->create();

        $this->comoEstudiante($estudiante)->patchJson("/api/admin/estudiantes/{$estudiante->id}", ['semestre_actual' => 9])->assertForbidden();

        $this->como(Rol::Admin);
        $this->patchJson("/api/admin/estudiantes/{$estudiante->id}", [
            'programa_id' => $estudiante->programa_id,
            'codigo' => $estudiante->codigo,
            'documento' => $estudiante->documento,
            'nombres' => 'Corregido',
            'apellidos' => $estudiante->apellidos,
            'semestre_actual' => 8,
        ])->assertOk()->assertJsonPath('nombres', 'Corregido');

        $this->patchJson("/api/admin/empresas/{$empresa->id}", [
            'nit' => $empresa->nit,
            'razon_social' => 'Nueva razón social',
            'contacto_nombre' => 'X',
            'contacto_telefono' => '1',
            'modalidad_interes' => 'PRACTICA',
        ])->assertOk()->assertJsonPath('razon_social', 'Nueva razón social');

        $this->assertSame(8, $estudiante->fresh()->semestre_actual);
    }

    public function test_el_comando_crea_la_primera_cuenta_de_administracion(): void
    {
        $this->artisan('fet:crear-admin', ['correo' => 'coordinacion@gmail.com'])->assertFailed();
        $this->artisan('fet:crear-admin', ['correo' => 'Coordinacion@fet.edu.co'])->assertSuccessful();

        $admin = Usuario::where('correo', 'coordinacion@fet.edu.co')->sole();
        $this->assertSame(Rol::Admin, $admin->rol);
        Notification::assertSentTo($admin, InvitacionCuenta::class);
    }
}
