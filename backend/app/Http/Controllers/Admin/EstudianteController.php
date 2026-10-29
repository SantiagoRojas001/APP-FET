<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GuardarEstudianteRequest;
use App\Http\Resources\EstudianteResource;
use App\Models\Estudiante;
use App\Services\Auditoria;

/**
 * La administración corrige los datos académicos de los estudiantes. Las
 * cuentas las crean los docentes desde el listado de modalidades de grado.
 */
class EstudianteController extends Controller
{
    private const CAMPOS = ['programa_id', 'codigo', 'documento', 'nombres', 'apellidos', 'telefono', 'semestre_actual', 'es_egresado'];

    public function update(GuardarEstudianteRequest $request, Estudiante $estudiante): EstudianteResource
    {
        $estudiante->update($request->safe()->only(self::CAMPOS));
        Auditoria::registrar('estudiante.actualizado', $estudiante, ['campos' => array_keys($estudiante->getChanges())]);

        return new EstudianteResource($estudiante->load('usuario', 'programa'));
    }
}
