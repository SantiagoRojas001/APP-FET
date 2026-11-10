<?php

namespace Tests\Feature;

use App\Enums\EstadoAcuerdo;
use App\Enums\EstadoSolicitudConvenio;
use App\Enums\EstadoValidacion;
use App\Enums\Rol;
use App\Models\Empresa;
use App\Models\SolicitudConvenio;
use App\Models\Usuario;
use App\Notifications\ConvenioParaRegistro;
use App\Notifications\EmpresaPorValidar;
use App\Notifications\EmpresaValidada;
use App\Notifications\SolicitudConvenioRecibida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Registro público de empresas: con la firma del representante legal y el
 * convenio vigente, o como solicitud de convenio si aún no lo tienen.
 */
class RegistroEmpresaTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('local');
        $this->admin = Usuario::factory()->rol(Rol::Admin)->create();
    }

    /** @return array<string, mixed> */
    private function empresa(array $cambios = []): array
    {
        return [
            'con_convenio' => '1',
            'correo' => 'Talento@Acme.co',
            'nit' => '900.123.456-7',
            'razon_social' => 'Acme S.A.S.',
            'contacto_nombre' => 'Ana Gómez',
            'contacto_telefono' => '3001234567',
            'modalidad_interes' => 'PASANTIA',
            'representante_nombre' => 'Carlos Pérez',
            'representante_documento' => '1075123456',
            'firma' => $this->firmaPng(),
            'acepta_politica' => '1',
            'password' => 'clave1234',
            'password_confirmation' => 'clave1234',
            'convenio' => UploadedFile::fake()->create('convenio.pdf', 200, 'application/pdf'),
            'vigencia_inicio' => now()->subMonth()->toDateString(),
            'vigencia_fin' => now()->addYear()->toDateString(),
            ...$cambios,
        ];
    }

    public function test_la_empresa_se_registra_con_la_firma_del_representante_y_su_convenio(): void
    {
        $this->post('/api/registro/empresa', $this->empresa(['firma' => null, 'convenio' => null]), ['Accept' => 'application/json'])
            ->assertJsonValidationErrors(['firma', 'convenio']);
        $this->post('/api/registro/empresa', $this->empresa(['firma' => $this->firmaPng(vacio: true)]), ['Accept' => 'application/json'])
            ->assertUnprocessable();
        $this->assertDatabaseCount('empresa', 0);

        $this->post('/api/registro/empresa', $this->empresa(), ['Accept' => 'application/json'])->assertCreated();

        $empresa = Empresa::sole();
        $this->assertSame(EstadoValidacion::Pendiente, $empresa->estado_validacion);
        $this->assertSame('Carlos Pérez', $empresa->representante_nombre);
        Storage::disk('local')->assertExists($empresa->firma_representante_url);
        $this->assertSame(EstadoAcuerdo::Pendiente, $empresa->acuerdos()->sole()->estado);
        Notification::assertSentTo($this->admin, EmpresaPorValidar::class);

        // No ingresa hasta que la administración la valide.
        $this->postJson('/api/auth/login', ['correo' => 'talento@acme.co', 'password' => 'clave1234'])->assertJsonValidationErrors('correo');

        Sanctum::actingAs($this->admin);
        $this->getJson('/api/admin/empresas')->assertJsonPath('0.url_firma', "/api/admin/empresas/{$empresa->id}/firma");
        $this->get("/api/admin/empresas/{$empresa->id}/firma")->assertOk();
        $this->patchJson("/api/admin/empresas/{$empresa->id}/validacion", ['estado_validacion' => 'VALIDADA'])->assertOk();
        Notification::assertSentTo($empresa->usuario, EmpresaValidada::class);

        auth()->forgetGuards();
        $this->postJson('/api/auth/login', ['correo' => 'talento@acme.co', 'password' => 'clave1234'])->assertOk();
    }

    public function test_sin_convenio_envia_una_solicitud_y_se_registra_cuando_lo_recibe(): void
    {
        $solicitud = $this->empresa(['con_convenio' => '0']);
        unset($solicitud['password'], $solicitud['password_confirmation'], $solicitud['convenio'], $solicitud['vigencia_inicio'], $solicitud['vigencia_fin']);

        $this->post('/api/registro/empresa', $solicitud, ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'solicitud de convenio'));
        $this->assertDatabaseCount('usuario', 1); // solo el administrador: la solicitud no crea cuenta
        Notification::assertSentTo($this->admin, SolicitudConvenioRecibida::class);

        // Una segunda solicitud de la misma empresa espera la respuesta de la primera.
        $this->post('/api/registro/empresa', [...$solicitud, 'firma' => $this->firmaPng()], ['Accept' => 'application/json'])->assertUnprocessable();

        Sanctum::actingAs($this->admin);
        $id = $this->getJson('/api/admin/solicitudes-convenio')->assertJsonPath('0.estado.valor', 'PENDIENTE')->json('0.id');
        $this->get("/api/admin/solicitudes-convenio/{$id}/firma")->assertOk();
        $this->post("/api/admin/solicitudes-convenio/{$id}/convenio", [], ['Accept' => 'application/json'])->assertJsonValidationErrors('convenio');
        $this->post("/api/admin/solicitudes-convenio/{$id}/convenio", ['convenio' => UploadedFile::fake()->create('convenio.pdf', 100, 'application/pdf')])
            ->assertOk()
            ->assertJsonPath('estado.valor', 'ENVIADO');
        Notification::assertSentOnDemand(ConvenioParaRegistro::class, fn ($aviso, $canales, $destino) => $destino->routes['mail'] === 'talento@acme.co');

        // Con el convenio firmado vuelve a registrarse y la solicitud queda atendida.
        auth()->forgetGuards();
        $this->post('/api/registro/empresa', $this->empresa(), ['Accept' => 'application/json'])->assertCreated();
        $this->assertSame(EstadoSolicitudConvenio::Registrada, SolicitudConvenio::sole()->estado);
    }

    public function test_la_administracion_rechaza_una_solicitud_con_motivo(): void
    {
        $solicitud = SolicitudConvenio::create([
            'nit' => '901', 'razon_social' => 'Beta', 'correo' => 'rrhh@beta.co', 'contacto_nombre' => 'Luis',
            'contacto_telefono' => '300', 'modalidad_interes' => 'PRACTICA', 'representante_nombre' => 'Eva',
            'representante_documento' => '123', 'firma_url' => 'firmas-empresas/x.png',
        ]);
        Sanctum::actingAs($this->admin);

        $this->postJson("/api/admin/solicitudes-convenio/{$solicitud->id}/rechazo", [])->assertJsonValidationErrors('motivo');
        $this->postJson("/api/admin/solicitudes-convenio/{$solicitud->id}/rechazo", ['motivo' => 'La empresa no tiene sede en la región.'])
            ->assertOk()
            ->assertJsonPath('estado.valor', 'RECHAZADA');
        $this->postJson("/api/admin/solicitudes-convenio/{$solicitud->id}/rechazo", ['motivo' => 'Otra vez'])->assertUnprocessable();
    }
}
