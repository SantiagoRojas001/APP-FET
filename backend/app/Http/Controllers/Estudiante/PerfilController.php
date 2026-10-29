<?php

namespace App\Http\Controllers\Estudiante;

use App\Exceptions\ReglaNegocioException;
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

        if (! $estudiante->hojaVidaHabilitada()) {
            throw new ReglaNegocioException('Tu hoja de vida está deshabilitada mientras cursas tu práctica.');
        }

        DB::transaction(function () use ($request, $estudiante) {
            $estudiante->update($request->safe()->only(['telefono', 'disponibilidad']));

            $hojaVida = $estudiante->hojaVida()->firstOrCreate();
            $hojaVida->update([
                ...$request->safe()->only([
                    'perfil_profesional', 'fecha_nacimiento', 'lugar_nacimiento', 'estado_civil', 'lugar_residencia',
                    'direccion', 'rh', 'contacto_emergencia_nombre', 'contacto_emergencia_telefono',
                ]),
                'actualizado_en' => now(),
            ]);

            foreach (['experiencias', 'formaciones', 'investigaciones', 'referencias'] as $lista) {
                $hojaVida->{$lista}()->delete();
                $hojaVida->{$lista}()->createMany($request->validated($lista));
            }

            // Los cursos se registran por mes: se guarda el primer día.
            $hojaVida->cursos()->delete();
            $hojaVida->cursos()->createMany(array_map(
                fn (array $c) => [...$c, 'fecha' => "{$c['fecha']}-01"],
                $request->validated('cursos'),
            ));

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
        return $estudiante->cargarHojaVida();
    }
}
