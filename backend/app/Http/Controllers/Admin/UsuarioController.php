<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoValidacion;
use App\Enums\EstadoVinculacion;
use App\Enums\Rol;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ActualizarUsuarioRequest;
use App\Http\Requests\Admin\CrearUsuarioRequest;
use App\Http\Resources\UsuarioResource;
use App\Models\Empresa;
use App\Models\Estudiante;
use App\Models\Usuario;
use App\Models\Vinculacion;
use App\Services\ElegibilidadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class UsuarioController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'rol' => ['nullable', Rule::enum(Rol::class)],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $usuarios = Usuario::query()
            ->with('estudiante.programa', 'estudiante.vinculacionActiva', 'empresa')
            ->when($filtros['rol'] ?? null, fn ($q, string $rol) => $q->where('rol', $rol))
            ->when($filtros['q'] ?? null, fn ($q, string $t) => $q->whereLike('correo', "%{$t}%"))
            ->orderBy('rol')
            ->orderBy('correo')
            ->paginate(25)
            ->withQueryString();

        return UsuarioResource::collection($usuarios);
    }

    public function store(CrearUsuarioRequest $request): JsonResponse
    {
        $usuario = Usuario::create([
            'correo' => $request->validated('correo'),
            'password_hash' => $request->validated('password'),
            'rol' => $request->validated('rol'),
        ]);

        return (new UsuarioResource($usuario->refresh()))->response()->setStatusCode(201);
    }

    public function update(ActualizarUsuarioRequest $request, Usuario $usuario): UsuarioResource
    {
        abort_if($usuario->is($request->user()), 422, 'No puedes desactivar tu propia cuenta.');

        $usuario->update($request->validated());

        if (! $usuario->activo) {
            $usuario->tokens()->delete(); // cierra sus sesiones abiertas
        }

        return new UsuarioResource($usuario->load('estudiante.programa', 'empresa'));
    }

    /**
     * Cifras para el panel de administración.
     */
    public function resumen(ElegibilidadService $elegibilidad): JsonResponse
    {
        $estudiantes = Estudiante::query()->get(['id', 'programa_id', 'semestre_actual', 'es_egresado']);

        return response()->json([
            'estudiantes' => $estudiantes->count(),
            'estudiantes_habilitados' => $estudiantes->filter(fn ($e) => $elegibilidad->habilitadoParaPractica($e))->count(),
            'docentes' => Usuario::where('rol', Rol::Docente)->count(),
            'empresas_validadas' => Empresa::where('estado_validacion', EstadoValidacion::Validada)->count(),
            'empresas_pendientes' => Empresa::where('estado_validacion', EstadoValidacion::Pendiente)->count(),
            'vinculaciones_activas' => Vinculacion::where('estado', EstadoVinculacion::Activa)->count(),
        ]);
    }
}
