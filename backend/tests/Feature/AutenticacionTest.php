<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Empresa;
use App\Models\Usuario;
use App\Models\Vinculacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutenticacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_usuario_ingresa_con_sus_credenciales_y_recibe_un_token(): void
    {
        $usuario = Usuario::factory()->rol(Rol::Docente)->create(['correo' => 'docente@fet.edu.co']);

        $respuesta = $this->postJson('/api/auth/login', ['correo' => 'DOCENTE@fet.edu.co', 'password' => 'password']);

        $respuesta->assertOk()
            ->assertJsonPath('usuario.id', $usuario->id)
            ->assertJsonPath('usuario.rol.valor', 'DOCENTE')
            ->assertJsonStructure(['token']);

        $this->withToken($respuesta->json('token'))->getJson('/api/auth/yo')->assertOk()->assertJsonPath('correo', 'docente@fet.edu.co');
    }

    public function test_rechaza_contrasena_incorrecta_sin_revelar_si_el_correo_existe(): void
    {
        Usuario::factory()->create(['correo' => 'a@fet.edu.co']);

        $this->postJson('/api/auth/login', ['correo' => 'a@fet.edu.co', 'password' => 'otra'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.correo.0', 'El correo o la contraseña no son correctos.');

        $this->postJson('/api/auth/login', ['correo' => 'nadie@fet.edu.co', 'password' => 'otra'])
            ->assertJsonPath('errors.correo.0', 'El correo o la contraseña no son correctos.');
    }

    public function test_una_cuenta_inactiva_no_puede_ingresar(): void
    {
        Usuario::factory()->inactivo()->create(['correo' => 'b@fet.edu.co']);

        $this->postJson('/api/auth/login', ['correo' => 'b@fet.edu.co', 'password' => 'password'])->assertUnprocessable();
    }

    public function test_una_empresa_sin_validar_no_puede_ingresar(): void
    {
        $empresa = Empresa::factory()->pendiente()->create();

        $this->postJson('/api/auth/login', ['correo' => $empresa->usuario->correo, 'password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('correo');
    }

    public function test_el_login_limita_los_intentos_fallidos(): void
    {
        foreach (range(1, 5) as $_) {
            $this->postJson('/api/auth/login', ['correo' => 'x@fet.edu.co', 'password' => 'mala']);
        }

        $this->postJson('/api/auth/login', ['correo' => 'x@fet.edu.co', 'password' => 'mala'])->assertTooManyRequests();
    }

    public function test_cerrar_sesion_revoca_el_token(): void
    {
        $usuario = Usuario::factory()->create();
        $token = $usuario->createToken('prueba')->plainTextToken;

        $this->withToken($token)->postJson('/api/auth/logout')->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_el_tema_se_guarda_en_la_cuenta_del_usuario(): void
    {
        $usuario = $this->como(Rol::Docente);

        $this->patchJson('/api/auth/preferencias', ['tema' => 'dark'])->assertOk()->assertJsonPath('tema', 'dark');
        $this->assertSame('dark', $usuario->fresh()->tema);

        $this->patchJson('/api/auth/preferencias', ['tema' => 'azul'])->assertJsonValidationErrors('tema');
    }

    public function test_indica_si_el_estudiante_tiene_una_pasantia_activa(): void
    {
        $vinculacion = Vinculacion::factory()->create();

        $this->comoEstudiante($vinculacion->estudiante)
            ->getJson('/api/auth/yo')
            ->assertJsonPath('vinculacion_activa_id', $vinculacion->id);

        $this->comoEstudiante($this->estudiante(5))
            ->getJson('/api/auth/yo')
            ->assertJsonPath('vinculacion_activa_id', null);
    }
}
