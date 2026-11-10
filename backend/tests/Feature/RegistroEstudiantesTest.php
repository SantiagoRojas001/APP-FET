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
 * El docente registra, al cargar el listado, a los estudiantes que eligieron
 * pasantía o práctica con su promedio: el promedio decide si quedan
 * habilitados (y se registran) o reciben el motivo por correo.
 */
class RegistroEstudiantesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    /** Registra un estudiante como lo hace la confirmación del listado de modalidades. */
    private function registrar(array $cambios = [])
    {
        return $this->postJson('/api/docente/importaciones/confirmacion', ['filas' => [$this->datos($cambios)]]);
    }

    /** @return array<string, mixed> */
    private function datos(array $cambios = []): array
    {
        return [
            'fila' => 1,
            'correo' => 'ana.perez@fet.edu.co',
            'nombres' => 'Ana',
            'apellidos' => 'Pérez',
            'programa_id' => $this->programa()->id,
            'semestre_actual' => 5,
            'modalidad_grado' => 'PASANTIA',
            'promedio' => 4.5,
            ...$cambios,
        ];
    }

    public function test_el_promedio_es_obligatorio_y_solo_se_registran_semestres_habilitados(): void
    {
        $this->como(Rol::Docente);

        $this->registrar(['promedio' => null])
            ->assertOk()
            ->assertJsonPath('creados', 0)
            ->assertJsonPath('errores.0.error', 'Escribe el promedio.');
        $this->registrar(['semestre_actual' => 4])
            ->assertJsonPath('creados', 0)
            ->assertJsonPath('errores.0.error', fn (string $m) => str_contains($m, '3.º, 5.º, 9.º'));

        $this->registrar()->assertOk()->assertJsonPath('creados', 1);
        $this->getJson('/api/docente/estudiantes')
            ->assertJsonPath('data.0.ciclo.etiqueta', 'Tecnólogo')
            ->assertJsonPath('data.0.promedio', 4.5);
        $this->assertDatabaseHas('usuario', ['correo' => 'ana.perez@fet.edu.co', 'rol' => 'ESTUDIANTE']);
    }

    public function test_con_promedio_suficiente_recibe_usuario_y_contrasena_y_debe_cambiarla(): void
    {
        $docente = $this->como(Rol::Docente);
        $this->registrar(['promedio' => '4.2'])->assertJsonPath('creados', 1);
        $estudiante = Estudiante::with('usuario')->sole();
        $this->assertTrue($estudiante->habilitado);

        $password = null;
        Notification::assertSentTo($estudiante->usuario, CredencialesAcceso::class, function (CredencialesAcceso $aviso) use ($estudiante, &$password) {
            $correo = implode("\n", $aviso->toMail($estudiante->usuario)->introLines);
            preg_match('/Contraseña: (\S+)/', $correo, $m);
            $password = $m[1] ?? null;

            return str_contains($correo, 'ana.perez@fet.edu.co') && $password !== null;
        });
        $this->assertDatabaseHas('auditoria', ['accion' => 'estudiante.promedio_registrado', 'entidad_id' => $estudiante->id]);

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

    public function test_con_promedio_bajo_recibe_el_motivo_y_no_queda_registrado(): void
    {
        Parametro::guardar('promedio_minimo', '4.2');
        $this->como(Rol::Docente);

        $this->registrar(['codigo' => '20231045', 'promedio' => 4.19])
            ->assertOk()
            ->assertJsonPath('creados', 0)
            ->assertJsonPath('no_habilitados', 1);

        $this->assertDatabaseMissing('usuario', ['correo' => 'ana.perez@fet.edu.co']);
        Notification::assertSentOnDemand(NoHabilitado::class, function (NoHabilitado $aviso, array $canales, object $destino) {
            $correo = implode("\n", $aviso->toMail($destino)->introLines);

            return $destino->routes['mail'] === 'ana.perez@fet.edu.co'
                && str_contains($correo, 'Ana Pérez · Código: 20231045')
                && str_contains($correo, 'promedio (4,19) es inferior al mínimo requerido (4,20)');
        });
        Notification::assertCount(1);
    }

    public function test_el_docente_solo_registra_y_ve_estudiantes_de_su_programa(): void
    {
        $docente = $this->como(Rol::Docente);
        $docente->update(['programa_id' => $this->programa()->id]);

        $this->registrar()
            ->assertJsonPath('creados', 0)
            ->assertJsonPath('errores.0.error', fn (string $m) => str_contains($m, 'otro programa'));
        $this->registrar(['programa_id' => $docente->programa_id])->assertJsonPath('creados', 1);

        $otro = $this->estudiante(5);
        $this->getJson('/api/docente/estudiantes')->assertJsonCount(1, 'data');
        $this->getJson("/api/estudiantes/{$otro->id}")->assertForbidden();
    }

    public function test_el_promedio_minimo_lo_define_la_administracion(): void
    {
        Parametro::guardar('promedio_minimo', '3.8');
        $this->como(Rol::Docente);

        $this->registrar(['promedio' => 3.9])->assertJsonPath('creados', 1);
    }

    public function test_solo_el_docente_registra_estudiantes(): void
    {
        Sanctum::actingAs($this->estudiante(9)->usuario);
        $this->registrar()->assertForbidden();

        $this->como(Rol::Admin);
        $this->registrar()->assertForbidden();
    }
}
