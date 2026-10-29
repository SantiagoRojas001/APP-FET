<?php

namespace App\Http\Controllers\Docente;

use App\Enums\Modalidad;
use App\Http\Controllers\Controller;
use App\Http\Requests\Docente\RegistrarEstudianteRequest;
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
 * Registro de estudiantes que eligieron pasantía o práctica y de su promedio.
 */
class EstudianteController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'programa_id' => ['nullable', 'integer'],
            'semestre' => ['nullable', 'integer', 'min:1'],
            'modalidad' => ['nullable', Rule::enum(Modalidad::class)],
            'estado' => ['nullable', Rule::in(['sin_promedio', 'habilitados', 'no_habilitados'])],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $estudiantes = Estudiante::query()
            ->with('usuario', 'programa', 'hojaVida.referencias', 'vinculacionActiva.empresa')
            ->whereNotNull('modalidad_grado')
            ->when($filtros['programa_id'] ?? null, fn (Builder $q, int $id) => $q->where('programa_id', $id))
            ->when($filtros['semestre'] ?? null, fn (Builder $q, int $s) => $q->where('semestre_actual', $s))
            ->when($filtros['modalidad'] ?? null, fn (Builder $q, string $m) => $q->where('modalidad_grado', $m))
            ->when($filtros['estado'] ?? null, fn (Builder $q, string $estado) => match ($estado) {
                'sin_promedio' => $q->whereNull('promedio'),
                'habilitados' => $q->where('habilitado', true),
                'no_habilitados' => $q->where('habilitado', false),
            })
            ->when($filtros['q'] ?? null, fn (Builder $q, string $t) => $q->where(fn (Builder $w) => $w->whereLike('nombres', "%{$t}%")->orWhereLike('apellidos', "%{$t}%")))
            ->orderByRaw('promedio is not null')
            ->orderBy('apellidos')
            ->paginate(50)
            ->withQueryString();

        return EstudianteResource::collection($estudiantes);
    }

    public function store(RegistrarEstudianteRequest $request, RegistroEstudianteService $registro): JsonResponse
    {
        $estudiante = $registro->registrar($request->validated(), $request->user());

        return (new EstudianteResource($estudiante->load('usuario', 'programa')))->response()->setStatusCode(201);
    }

    /** Registra el promedio y envía el correo con el resultado (y las credenciales si queda habilitado). */
    public function promedio(RegistrarPromedioRequest $request, Estudiante $estudiante, RegistroEstudianteService $registro): EstudianteResource
    {
        $estudiante = $registro->registrarPromedio($estudiante, (float) $request->validated('promedio'), $request->user());

        return new EstudianteResource($estudiante->load('usuario', 'programa'));
    }
}
