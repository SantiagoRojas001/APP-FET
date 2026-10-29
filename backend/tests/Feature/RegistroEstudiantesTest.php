<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Estudiante;
use App\Models\Parametro;
use App\Notifications\CredencialesAcceso;
use App\Notifications\NoHabilitado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * El docente registra a los estudiantes que eligieron pasantía o práctica y
 * su promedio decide si quedan habilitados.
 */
class RegistroEstudiantesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    /** @return array<string, mixed> */
    private function datos(array $cambios = []): array
    {
        return [
            'correo' => 'Ana.Perez@fet.edu.co',
            'nombres' => 'Ana',
            'apellidos' => 'Pérez',
            'programa_id' => $this->programa()->id,
            'semestre_actual' => 5,
            'modalidad_grado' => 'PASANTIA',
            ...$cambios,
        ];
    }

    public function test_el_docente_registra_solo_semestres_habilitados_y_sin_enviar_correo(): void
    {
        $this->como(Rol::Docente);

        $this->postJson('/api/docente/estudiantes', $this->datos(['semestre_actual' => 4]))
            ->assertUnprocessable()
            ->assertJsonPath('message', fn (string $m) => str_contains($m, '3.º, 5.º, 9.º'));

        $this->postJson('/api/docente/estudiantes', $this->datos())
            ->assertCreated()
            ->assertJsonPath('ciclo', 'Tecnólogo')
            ->assertJsonPath('promedio', null);

        $this->assertDatabaseHas('usuario', ['correo' => 'ana.perez@fet.edu.co', 'rol' => 'ESTUDIANTE']);
        Notification::assertNothingSent();

        $this->getJson('/api/docente/estudiantes?estado=sin_promedio')->assertJsonCount(1, 'data');
    }

    public function test_con_promedio_suficiente_recibe_usuario_y_contrasena_y_debe_cambiarla(): void
    {
        $docente = $this->como(Rol::Docente);
        $id = $this->postJson('/api/docente/estudiantes', $this->datos())->json('id');

        $password = null;
        $this->patchJson("/api/docente/estudiantes/{$id}/promedio", ['promedio' => '4,2'])
            ->assertOk()
            ->assertJsonPath('habilitado', true);
        $this->patchJson("/api/docente/estudiantes/{$id}/promedio", ['promedio' => '4,5'])->assertUnprocessable();

        $estudiante = Estudiante::with('usuario')->find($id);
        Notification::assertSentTo($estudiante->usuario, CredencialesAcceso::class, function (CredencialesAcceso $aviso) use ($estudiante, &$password) {
            $correo = implode("\n", $aviso->toMail($estudiante->usuario)->introLines);
            preg_match('/Contraseña: (\S+)/', $correo, $m);
            $password = $m[1] ?? null;

            return str_contains($correo, 'ana.perez@fet.edu.co') && $password !== null;
        });
        $this->assertDatabaseHas('auditoria', ['accion' => 'estudiante.promedio_registrado', 'entidad_id' => $id]);

        // Primer ingreso: con la contraseña del correo solo puede cambiarla.
        auth()->forgetGuards();
        $token = $this->postJson('/api/auth/login', ['correo' => 'ana.perez@fet.edu.co', 'password' => $password])
            ->assertOk()
            ->assertJsonPath('usuario.debe_cambiar_password', true)
            ->json('token');
        $this->withToken($token)->getJson('/api/estudiante/perfil')->assertForbidden();

        $this->withToken($token)->postJson('/api/auth/primer-ingreso', ['password' => $password, 'password_confirmation' => $password, 'acepta_politica' => true])
            ->assertJsonValidationErrors('password');
        $this->withToken($token)->postJson('/api/auth/primer-ingreso', ['password' => 'nueva1234', 'password_confirmation' => 'nueva1234', 'acepta_politica' => true])
            ->assertOk()
            ->assertJsonPath('debe_cambiar_password', false);

        $this->withToken($token)->getJson('/api/estudiante/perfil')->assertOk();
        $this->assertDatabaseHas('consentimiento_datos', ['usuario_id' => $estudiante->usuario_id]);
        $this->assertNotSame($docente->id, $estudiante->usuario_id);
    }

    public function test_con_promedio_bajo_recibe_el_mensaje_y_no_puede_ingresar(): void
    {
        Parametro::guardar('promedio_minimo', '4.2');
        $this->como(Rol::Docente);
        $id = $this->postJson('/api/docente/estudiantes', $this->datos())->json('id');

        $this->patchJson("/api/docente/estudiantes/{$id}/promedio", ['promedio' => 4.19])
            ->assertOk()
            ->assertJsonPath('habilitado', false);

        $estudiante = Estudiante::with('usuario')->find($id);
        $this->assertFalse($estudiante->usuario->activo);
        Notification::assertSentTo($estudiante->usuario, NoHabilitado::class);
        Notification::assertNotSentTo($estudiante->usuario, CredencialesAcceso::class);
    }

    public function test_el_promedio_minimo_lo_define_la_administracion(): void
    {
        Parametro::guardar('promedio_minimo', '3.8');
        $this->como(Rol::Docente);
        $id = $this->postJson('/api/docente/estudiantes', $this->datos())->json('id');

        $this->patchJson("/api/docente/estudiantes/{$id}/promedio", ['promedio' => 3.9])->assertJsonPath('habilitado', true);
    }

    public function test_solo_el_docente_registra_estudiantes(): void
    {
        Sanctum::actingAs($this->estudiante(9)->usuario);
        $this->postJson('/api/docente/estudiantes', $this->datos())->assertForbidden();

        $this->como(Rol::Admin);
        $this->postJson('/api/docente/estudiantes', $this->datos())->assertForbidden();
    }
}
