<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Rol;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\PrimerIngresoRequest;
use App\Http\Resources\UsuarioResource;
use App\Models\Usuario;
use App\Services\Auditoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SesionController extends Controller
{
    /** Horas de validez del token de acceso. */
    private const DURACION_TOKEN_HORAS = 8;

    public function store(LoginRequest $request): JsonResponse
    {
        $usuario = Usuario::query()
            ->with('estudiante.programa', 'estudiante.vinculacionActiva', 'empresa', 'programa')
            ->where('correo', mb_strtolower($request->input('correo')))
            ->first();

        if (! $usuario || ! Hash::check($request->input('password'), $usuario->password_hash)) {
            throw ValidationException::withMessages(['correo' => 'El correo o la contraseña no son correctos.']);
        }

        if (! $usuario->activo) {
            throw ValidationException::withMessages(['correo' => 'Tu cuenta está desactivada. Comunícate con la coordinación de prácticas.']);
        }

        if ($usuario->rol === Rol::Empresa && ! $usuario->empresa?->estaValidada()) {
            throw ValidationException::withMessages(['correo' => 'Tu empresa aún no ha sido validada por la FET. Podrás ingresar cuando la administración apruebe el registro.']);
        }

        $token = $usuario->createToken('app-fet', ['*'], now()->addHours(self::DURACION_TOKEN_HORAS));

        return response()->json([
            'token' => $token->plainTextToken,
            'usuario' => new UsuarioResource($usuario),
        ]);
    }

    public function show(Request $request): UsuarioResource
    {
        return new UsuarioResource($request->user()->load('estudiante.programa', 'estudiante.vinculacionActiva', 'empresa', 'programa'));
    }

    /** Primer ingreso: cambia la contraseña inicial y acepta la política de datos. */
    public function primerIngreso(PrimerIngresoRequest $request): UsuarioResource
    {
        $usuario = $request->user();
        abort_unless($usuario->debe_cambiar_password, 422, 'Tu cuenta ya tiene su propia contraseña.');

        DB::transaction(function () use ($usuario, $request) {
            $usuario->update(['password_hash' => $request->validated('password'), 'debe_cambiar_password' => false]);
            $usuario->consentimientos()->firstOrCreate(['version_politica' => config('fet.version_politica')]);
        });
        Auditoria::registrar('usuario.primer_ingreso', $usuario);

        return $this->show($request);
    }

    /** Guarda las preferencias de la cuenta: el tema visual y si ya vio el recorrido guiado. */
    public function preferencias(Request $request): UsuarioResource
    {
        $datos = $request->validate([
            'tema' => ['sometimes', Rule::in(['light', 'dark', 'system'])],
            'tutorial_visto' => ['sometimes', 'boolean'],
        ]);

        $request->user()->update($datos);

        return $this->show($request);
    }

    public function destroy(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
