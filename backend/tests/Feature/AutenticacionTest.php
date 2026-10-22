<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Empresa;
use App\Models\Programa;
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

    public function test_un_estudiante_se_registra_con_correo_institucional_y_acepta_la_politica(): void
    {
        $programa = Programa::factory()->create(['total_semestres' => 10]);

        $respuesta = $this->postJson('/api/registro/estudiante', [
            'correo' => 'nuevo.estudiante@fet.edu.co',
            'password' => 'clave1234',
            'password_confirmation' => 'clave1234',
            'programa_id' => $programa->id,
            'codigo' => '20269999',
            'documento' => '1075000000',
            'nombres' => 'Ana',
            'apellidos' => 'Gómez',
            'semestre_actual' => 4,
            'acepta_politica' => true,
        ]);

        $respuesta->assertCreated()->assertJsonStructure(['token', 'usuario' => ['estudiante']]);

        $usuario = Usuario::where('correo', 'nuevo.estudiante@fet.edu.co')->firstOrFail();
        $this->assertSame(Rol::Estudiante, $usuario->rol);
        $this->assertNotNull($usuario->estudiante->hojaVida);
        $this->assertDatabaseHas('consentimiento_datos', ['usuario_id' => $usuario->id, 'version_politica' => config('fet.version_politica')]);
    }

    public function test_el_registro_de_estudiante_valida_dominio_politica_y_semestre(): void
    {
        $programa = Programa::factory()->create(['total_semestres' => 6]);

        $this->postJson('/api/registro/estudiante', [
            'correo' => 'ana@gmail.com',
            'password' => 'clave1234',
            'password_confirmation' => 'clave1234',
            'programa_id' => $programa->id,
            'codigo' => '1',
            'documento' => '1',
            'nombres' => 'Ana',
            'apellidos' => 'Gómez',
            'semestre_actual' => 9,
        ])->assertJsonValidationErrors(['correo', 'acepta_politica', 'semestre_actual']);
    }

    public function test_una_empresa_se_registra_pendiente_de_validacion(): void
    {
        $this->postJson('/api/registro/empresa', [
            'correo' => 'rrhh@empresa.co',
            'password' => 'clave1234',
            'password_confirmation' => 'clave1234',
            'nit' => '901.111.222-3',
            'razon_social' => 'Empresa de Prueba S.A.S.',
            'contacto_nombre' => 'Laura Díaz',
            'contacto_telefono' => '3100000000',
            'modalidad_interes' => 'PASANTIA',
            'acepta_politica' => true,
        ])->assertCreated()->assertJsonMissingPath('token');

        $this->assertDatabaseHas('empresa', ['nit' => '901.111.222-3', 'estado_validacion' => 'PENDIENTE']);
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
