<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Rol;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GuardarEstudianteRequest;
use App\Http\Resources\EstudianteResource;
use App\Models\Estudiante;
use App\Models\Usuario;
use App\Services\Auditoria;
use App\Services\CuentaService;
use Illuminate\Http\JsonResponse;

/**
 * La administración crea las cuentas de los estudiantes y corrige sus datos
 * académicos. El estudiante solo edita su contacto y su hoja de vida.
 */
class EstudianteController extends Controller
{
    private const CAMPOS = ['programa_id', 'codigo', 'documento', 'nombres', 'apellidos', 'telefono', 'semestre_actual', 'es_egresado'];

    public function store(GuardarEstudianteRequest $request, CuentaService $cuentas): JsonResponse
    {
        $usuario = $cuentas->crear($request->validated('correo'), Rol::Estudiante, function (Usuario $usuario) use ($request) {
            $usuario->estudiante()->create($request->safe()->only(self::CAMPOS))->hojaVida()->create();
        });

        return (new EstudianteResource($usuario->estudiante->load('usuario', 'programa')))->response()->setStatusCode(201);
    }

    public function update(GuardarEstudianteRequest $request, Estudiante $estudiante): EstudianteResource
    {
        $estudiante->update($request->safe()->only(self::CAMPOS));
        Auditoria::registrar('estudiante.actualizado', $estudiante, ['campos' => array_keys($estudiante->getChanges())]);

        return new EstudianteResource($estudiante->load('usuario', 'programa'));
    }
}
