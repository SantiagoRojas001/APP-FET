<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProgramaResource;
use App\Models\Programa;
use App\Services\Auditoria;
use Illuminate\Http\Request;

/**
 * Horas de práctica que exige cada programa.
 */
class ProgramaController extends Controller
{
    public function update(Request $request, Programa $programa): ProgramaResource
    {
        $datos = $request->validate(['horas_requeridas' => ['required', 'integer', 'min:40', 'max:2000']]);

        $anterior = $programa->horas_requeridas;
        $programa->update($datos);
        Auditoria::registrar('programa.horas_actualizadas', $programa, ['antes' => $anterior, 'ahora' => $programa->horas_requeridas]);

        return new ProgramaResource($programa);
    }
}
