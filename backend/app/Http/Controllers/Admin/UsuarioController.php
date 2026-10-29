<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoReporte;
use App\Enums\EstadoValidacion;
use App\Enums\EstadoVinculacion;
use App\Enums\Rol;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ActualizarUsuarioRequest;
use App\Http\Requests\Admin\CrearUsuarioRequest;
use App\Http\Resources\UsuarioResource;
use App\Models\Empresa;
use App\Models\Estudiante;
use App\Models\Reporte;
use App\Models\Usuario;
use App\Models\Vinculacion;
use App\Services\Auditoria;
use App\Services\CuentaService;
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
            ->withCount('consentimientos')
            ->when($filtros['rol'] ?? null, fn ($q, string $rol) => $q->where('rol', $rol))
            ->when($filtros['q'] ?? null, fn ($q, string $t) => $q->whereLike('correo', "%{$t}%"))
            ->orderBy('rol')
            ->orderBy('correo')
            ->paginate(25)
            ->withQueryString();

        return UsuarioResource::collection($usuarios);
    }

    /** Crea la cuenta de un docente o administrador y le envía la invitación. */
    public function store(CrearUsuarioRequest $request, CuentaService $cuentas): JsonResponse
    {
        $usuario = $cuentas->crear($request->validated('correo'), Rol::from($request->validated('rol')));

        return (new UsuarioResource($usuario->refresh()->loadCount('consentimientos')))->response()->setStatusCode(201);
    }

    /** Envía de nuevo el enlace de activación (el anterior deja de servir). */
    public function invitar(Usuario $usuario, CuentaService $cuentas): JsonResponse
    {
        abort_unless($cuentas->pendienteDeActivacion($usuario), 422, 'Esta cuenta ya está activa. Si olvidó su contraseña, puede recuperarla desde el ingreso.');

        $cuentas->invitar($usuario);
        Auditoria::registrar('usuario.invitacion_reenviada', $usuario);

        return response()->json(['message' => 'Invitación enviada.']);
    }

    public function update(ActualizarUsuarioRequest $request, Usuario $usuario): UsuarioResource
    {
        abort_if($usuario->is($request->user()), 422, 'No puedes desactivar tu propia cuenta.');

        $usuario->update($request->validated());
        Auditoria::registrar($usuario->activo ? 'usuario.activado_por_admin' : 'usuario.desactivado', $usuario);

        if (! $usuario->activo) {
            $usuario->tokens()->delete(); // cierra sus sesiones abiertas
        }

        return new UsuarioResource($usuario->load('estudiante.programa', 'empresa')->loadCount('consentimientos'));
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
            'reportes_abiertos' => Reporte::where('estado', '!=', EstadoReporte::Resuelto)->count(),
        ]);
    }
}
