<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Rol;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegistroEmpresaRequest;
use App\Http\Requests\Auth\RegistroEstudianteRequest;
use App\Http\Resources\UsuarioResource;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Registro público de estudiantes y empresas. Ambos aceptan la política de
 * tratamiento de datos, que queda registrada con su versión.
 */
class RegistroController extends Controller
{
    public function estudiante(RegistroEstudianteRequest $request): JsonResponse
    {
        $usuario = DB::transaction(function () use ($request) {
            $usuario = $this->crearUsuario($request->input('correo'), $request->input('password'), Rol::Estudiante);

            $estudiante = $usuario->estudiante()->create($request->safe()->only([
                'programa_id', 'codigo', 'documento', 'nombres', 'apellidos', 'telefono', 'semestre_actual', 'es_egresado',
            ]));
            $estudiante->hojaVida()->create();

            return $usuario;
        });

        $token = $usuario->createToken('app-fet', ['*'], now()->addHours(8));

        return response()->json([
            'token' => $token->plainTextToken,
            'usuario' => new UsuarioResource($usuario->refresh()->load('estudiante.programa', 'estudiante.vinculacionActiva')),
        ], 201);
    }

    public function empresa(RegistroEmpresaRequest $request): JsonResponse
    {
        DB::transaction(function () use ($request) {
            $usuario = $this->crearUsuario($request->input('correo'), $request->input('password'), Rol::Empresa);

            $usuario->empresa()->create($request->safe()->only([
                'nit', 'razon_social', 'sector', 'contacto_nombre', 'contacto_telefono', 'modalidad_interes',
            ]));
        });

        return response()->json([
            'message' => 'Registro recibido. La FET validará tu empresa y te avisará para que puedas ingresar.',
        ], 201);
    }

    private function crearUsuario(string $correo, string $password, Rol $rol): Usuario
    {
        $usuario = Usuario::create([
            'correo' => $correo,
            'password_hash' => $password,
            'rol' => $rol,
        ]);

        $usuario->consentimientos()->create(['version_politica' => config('fet.version_politica')]);

        return $usuario;
    }
}
