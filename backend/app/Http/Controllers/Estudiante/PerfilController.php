<?php

namespace App\Http\Controllers\Estudiante;

use App\Http\Controllers\Controller;
use App\Http\Requests\Estudiante\ActualizarPerfilRequest;
use App\Http\Resources\EstudianteResource;
use App\Models\Estudiante;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Hoja de vida del estudiante autenticado.
 */
class PerfilController extends Controller
{
    public function show(Request $request): EstudianteResource
    {
        return new EstudianteResource($this->cargar($request->user()->estudiante()->firstOrFail()));
    }

    public function update(ActualizarPerfilRequest $request): EstudianteResource
    {
        $estudiante = $request->user()->estudiante()->firstOrFail();

        DB::transaction(function () use ($request, $estudiante) {
            $estudiante->update($request->safe()->only(['telefono', 'disponibilidad']));

            $hojaVida = $estudiante->hojaVida()->firstOrCreate();
            $hojaVida->update([
                ...$request->safe()->only(['perfil_profesional', 'formacion']),
                'actualizado_en' => now(),
            ]);

            $hojaVida->experiencias()->delete();
            $hojaVida->experiencias()->createMany($request->validated('experiencias'));

            $estudiante->competencias()->sync(
                collect($request->validated('competencias'))->mapWithKeys(fn (array $c) => [$c['id'] => ['nivel' => $c['nivel']]])
            );

            $estudiante->proyectos()->delete();
            $estudiante->proyectos()->createMany($request->validated('proyectos'));
        });

        return new EstudianteResource($this->cargar($estudiante->fresh()));
    }

    private function cargar(Estudiante $estudiante): Estudiante
    {
        return $estudiante->load('usuario', 'programa', 'hojaVida.experiencias', 'competencias', 'proyectos');
    }
}
