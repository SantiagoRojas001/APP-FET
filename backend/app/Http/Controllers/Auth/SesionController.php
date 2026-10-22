<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Rol;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UsuarioResource;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
            ->with('estudiante.programa', 'estudiante.vinculacionActiva', 'empresa')
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
        return new UsuarioResource($request->user()->load('estudiante.programa', 'estudiante.vinculacionActiva', 'empresa'));
    }

    /** Guarda las preferencias de la cuenta (por ahora, el tema visual). */
    public function preferencias(Request $request): UsuarioResource
    {
        $datos = $request->validate(['tema' => ['required', Rule::in(['light', 'dark', 'system'])]]);

        $request->user()->update($datos);

        return $this->show($request);
    }

    public function destroy(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
