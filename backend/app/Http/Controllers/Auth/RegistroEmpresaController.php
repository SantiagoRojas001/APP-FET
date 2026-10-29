<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Rol;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegistroEmpresaRequest;
use App\Models\Usuario;
use App\Notifications\EmpresaPorValidar;
use App\Services\Auditoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Registro público de empresas. La empresa define su contraseña y acepta la
 * política de datos, pero no ingresa hasta que la administración la valide.
 * Los estudiantes y docentes siguen entrando solo por invitación.
 */
class RegistroEmpresaController extends Controller
{
    public function __invoke(RegistroEmpresaRequest $request): JsonResponse
    {
        $empresa = DB::transaction(function () use ($request) {
            $usuario = Usuario::create([
                'correo' => $request->validated('correo'),
                'password_hash' => $request->validated('password'),
                'rol' => Rol::Empresa,
            ]);
            $usuario->consentimientos()->create(['version_politica' => config('fet.version_politica')]);

            return $usuario->empresa()->create($request->safe()->only([
                'nit', 'razon_social', 'sector', 'contacto_nombre', 'contacto_telefono', 'modalidad_interes',
            ]));
        });

        Auditoria::registrar('empresa.registrada', $empresa);
        Notification::send(
            Usuario::query()->where('rol', Rol::Admin)->where('activo', true)->get(),
            new EmpresaPorValidar($empresa),
        );

        return response()->json([
            'message' => 'Registro recibido. La FET validará tu empresa y te avisará por correo para que puedas ingresar.',
        ], 201);
    }
}
