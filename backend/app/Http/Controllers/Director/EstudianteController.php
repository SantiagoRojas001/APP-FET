<?php

namespace App\Http\Controllers\Director;

use App\Enums\Ciclo;
use App\Enums\EstadoVinculacion;
use App\Enums\Modalidad;
use App\Enums\Rol;
use App\Http\Controllers\Controller;
use App\Http\Resources\EstudianteResource;
use App\Models\Estudiante;
use App\Models\Usuario;
use App\Models\Vinculacion;
use App\Services\Auditoria;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Estudiantes del programa del director, con su ciclo académico y si cursan
 * pasantía, práctica o aún no.
 */
class EstudianteController extends Controller
{
    /** Docentes del programa, para asignarlos como tutores. */
    public function docentes(Request $request): JsonResponse
    {
        $docentes = Usuario::query()
            ->where('rol', Rol::Docente)
            ->where('activo', true)
            ->where('programa_id', $request->user()->programa_id)
            ->orderBy('correo')
            ->get();

        return response()->json($docentes->map(fn (Usuario $d) => ['id' => $d->id, 'nombre' => $d->nombre ?? $d->correo])->values());
    }

    /** Asigna (o quita) el tutor de un grupo de estudiantes y de sus prácticas en curso. */
    public function asignarTutor(Request $request): JsonResponse
    {
        $programa = $request->user()->programa_id;
        $datos = $request->validate([
            'estudiante_ids' => ['required', 'array', 'min:1', 'max:200'],
            'estudiante_ids.*' => ['integer', Rule::exists('estudiante', 'id')->where('programa_id', $programa)],
            'tutor_id' => ['nullable', 'integer', Rule::exists('usuario', 'id')->where('rol', Rol::Docente->value)->where('programa_id', $programa)->where('activo', true)],
        ]);

        DB::transaction(function () use ($datos) {
            Estudiante::query()->whereIn('id', $datos['estudiante_ids'])->update(['tutor_id' => $datos['tutor_id']]);
            Vinculacion::query()
                ->whereIn('estudiante_id', $datos['estudiante_ids'])
                ->where('estado', EstadoVinculacion::Activa)
                ->update(['tutor_id' => $datos['tutor_id']]);
        });
        Auditoria::registrar('estudiantes.tutor_asignado', $request->user(), $datos);

        return response()->json(['message' => 'Tutor asignado.']);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'ciclo' => ['nullable', Rule::enum(Ciclo::class)],
            'practica' => ['nullable', Rule::in([...Modalidad::values(), 'NINGUNA'])],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $activa = fn (Builder $v) => $v->where('estado', EstadoVinculacion::Activa);

        $estudiantes = Estudiante::query()
            ->where('programa_id', $request->user()->programa_id)
            ->with('usuario', 'programa', 'tutor', 'hojaVida.referencias', 'vinculacionActiva.empresa')
            ->when($filtros['ciclo'] ?? null, fn (Builder $q, string $c) => $q->delCiclo(Ciclo::from($c)))
            ->when($filtros['practica'] ?? null, fn (Builder $q, string $p) => $p === 'NINGUNA'
                ? $q->whereDoesntHave('vinculaciones', $activa)
                : $q->whereHas('vinculaciones', fn (Builder $v) => $activa($v)->where('tipo', $p)))
            ->when($filtros['q'] ?? null, fn (Builder $q, string $t) => $q->where(fn (Builder $w) => $w->whereLike('nombres', "%{$t}%")->orWhereLike('apellidos', "%{$t}%")))
            ->orderBy('semestre_actual')
            ->orderBy('apellidos')
            ->paginate(50)
            ->withQueryString();

        return EstudianteResource::collection($estudiantes);
    }
}
