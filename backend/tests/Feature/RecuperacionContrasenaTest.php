<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Notifications\RestablecerContrasena;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class RecuperacionContrasenaTest extends TestCase
{
    use RefreshDatabase;

    public function test_envia_el_enlace_al_correo_registrado(): void
    {
        Notification::fake();
        $usuario = Usuario::factory()->create(['correo' => 'ana@fet.edu.co']);

        $this->postJson('/api/auth/olvide-contrasena', ['correo' => 'ANA@fet.edu.co'])->assertOk();

        Notification::assertSentTo($usuario, RestablecerContrasena::class, function (RestablecerContrasena $n) use ($usuario) {
            $url = $n->toMail($usuario)->actionUrl;

            return str_contains($url, '/#/restablecer?token=') && str_contains($url, 'correo=ana%40fet.edu.co');
        });
    }

    public function test_responde_igual_si_el_correo_no_existe(): void
    {
        Notification::fake();
        Usuario::factory()->create(['correo' => 'ana@fet.edu.co']);

        $existe = $this->postJson('/api/auth/olvide-contrasena', ['correo' => 'ana@fet.edu.co'])->json('message');
        $noExiste = $this->postJson('/api/auth/olvide-contrasena', ['correo' => 'nadie@fet.edu.co'])->assertOk()->json('message');

        $this->assertSame($existe, $noExiste);
        Notification::assertSentTimes(RestablecerContrasena::class, 1);
    }

    public function test_restablece_la_contrasena_con_un_token_valido_y_cierra_las_sesiones(): void
    {
        $usuario = Usuario::factory()->create(['correo' => 'ana@fet.edu.co']);
        $usuario->createToken('sesion-anterior');
        $token = Password::createToken($usuario);

        $this->postJson('/api/auth/restablecer-contrasena', [
            'token' => $token,
            'correo' => 'ana@fet.edu.co',
            'password' => 'nueva1234',
            'password_confirmation' => 'nueva1234',
        ])->assertOk();

        $this->assertTrue(Hash::check('nueva1234', $usuario->fresh()->password_hash));
        $this->assertSame(0, $usuario->tokens()->count());
        $this->postJson('/api/auth/login', ['correo' => 'ana@fet.edu.co', 'password' => 'nueva1234'])->assertOk();
    }

    public function test_rechaza_un_token_invalido(): void
    {
        Usuario::factory()->create(['correo' => 'ana@fet.edu.co']);

        $this->postJson('/api/auth/restablecer-contrasena', [
            'token' => 'inventado',
            'correo' => 'ana@fet.edu.co',
            'password' => 'nueva1234',
            'password_confirmation' => 'nueva1234',
        ])->assertJsonValidationErrors('token');
    }

    public function test_el_token_solo_sirve_una_vez(): void
    {
        $usuario = Usuario::factory()->create(['correo' => 'ana@fet.edu.co']);
        $datos = ['token' => Password::createToken($usuario), 'correo' => 'ana@fet.edu.co', 'password' => 'nueva1234', 'password_confirmation' => 'nueva1234'];

        $this->postJson('/api/auth/restablecer-contrasena', $datos)->assertOk();
        $this->postJson('/api/auth/restablecer-contrasena', $datos)->assertJsonValidationErrors('token');
    }
}
