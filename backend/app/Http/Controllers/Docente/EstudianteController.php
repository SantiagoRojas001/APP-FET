<?php

namespace App\Http\Controllers\Docente;

use App\Enums\Modalidad;
use App\Http\Controllers\Controller;
use App\Http\Requests\Docente\RegistrarPromedioRequest;
use App\Http\Resources\EstudianteResource;
use App\Models\Estudiante;
use App\Services\RegistroEstudianteService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Estudiantes registrados desde el listado de modalidades de grado y registro
 * de su promedio (ver ImportacionController).
 */
class EstudianteController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'programa_id' => ['nullable', 'integer'],
            'semestre' => ['nullable', 'integer', 'min:1'],
            'modalidad' => ['nullable', Rule::enum(Modalidad::class)],
            'estado' => ['nullable', Rule::in(['sin_promedio', 'habilitados'])],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $estudiantes = Estudiante::query()
            ->with('usuario', 'programa', 'hojaVida.referencias', 'vinculacionActiva.empresa')
            ->whereNotNull('modalidad_grado')
            ->delPrograma($request->user()->programaAsignado())
            ->when($filtros['programa_id'] ?? null, fn (Builder $q, int $id) => $q->where('programa_id', $id))
            ->when($filtros['semestre'] ?? null, fn (Builder $q, int $s) => $q->where('semestre_actual', $s))
            ->when($filtros['modalidad'] ?? null, fn (Builder $q, string $m) => $q->where('modalidad_grado', $m))
            ->when($filtros['estado'] ?? null, fn (Builder $q, string $estado) => match ($estado) {
                'sin_promedio' => $q->whereNull('promedio'),
                'habilitados' => $q->where('habilitado', true),
            })
            ->when($filtros['q'] ?? null, fn (Builder $q, string $t) => $q->where(fn (Builder $w) => $w->whereLike('nombres', "%{$t}%")->orWhereLike('apellidos', "%{$t}%")))
            ->orderByRaw('promedio is not null')
            ->orderBy('apellidos')
            ->paginate(50)
            ->withQueryString();

        return EstudianteResource::collection($estudiantes);
    }

    /** Registra el promedio y envía el correo con el resultado (y las credenciales si queda habilitado). */
    /**
     * Registra el promedio y envía el correo con el resultado: las credenciales
     * si queda habilitado; si no, el motivo, y el registro se elimina.
     */
    public function promedio(RegistrarPromedioRequest $request, Estudiante $estudiante, RegistroEstudianteService $registro): JsonResponse
    {
        $this->delPrograma($request, $estudiante);
        $habilitado = $registro->registrarPromedio($estudiante, (float) $request->validated('promedio'), $request->user());

        return $habilitado
            ? (new EstudianteResource($habilitado->load('usuario', 'programa')))->response()
            : response()->json(['eliminado' => true, 'message' => 'No alcanza el promedio mínimo: le enviamos el correo con el motivo y no queda registrado.']);
    }

    private function delPrograma(Request $request, Estudiante $estudiante): void
    {
        $programa = $request->user()->programaAsignado();
        abort_if($programa && $programa !== $estudiante->programa_id, 403, 'El estudiante es de otro programa.');
    }
}
