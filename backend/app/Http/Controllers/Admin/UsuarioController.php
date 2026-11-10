<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoAcuerdo;
use App\Enums\EstadoHojaVida;
use App\Enums\EstadoReporte;
use App\Enums\EstadoValidacion;
use App\Enums\EstadoVinculacion;
use App\Enums\Rol;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ActualizarUsuarioRequest;
use App\Http\Requests\Admin\CrearUsuarioRequest;
use App\Http\Resources\UsuarioResource;
use App\Models\Acuerdo;
use App\Models\Empresa;
use App\Models\Estudiante;
use App\Models\Programa;
use App\Models\Reporte;
use App\Models\Usuario;
use App\Models\Vinculacion;
use App\Services\Auditoria;
use App\Services\CuentaService;
use App\Support\Periodo;
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
            ->with('estudiante.programa', 'estudiante.vinculacionActiva', 'empresa', 'programa')
            ->withCount('consentimientos')
            ->when($filtros['rol'] ?? null, fn ($q, string $rol) => $q->where('rol', $rol))
            ->when($filtros['q'] ?? null, fn ($q, string $t) => $q->whereLike('correo', "%{$t}%"))
            ->orderBy('rol')
            ->orderBy('correo')
            ->paginate(25)
            ->withQueryString();

        return UsuarioResource::collection($usuarios);
    }

    /** Crea la cuenta de un docente, director de programa o administrador y le envía la invitación. */
    public function store(CrearUsuarioRequest $request, CuentaService $cuentas): JsonResponse
    {
        $rol = Rol::from($request->validated('rol'));
        $usuario = $cuentas->crear($request->validated('correo'), $rol, function (Usuario $usuario) use ($rol, $request) {
            if ($rol !== Rol::Admin) {
                $usuario->update(['programa_id' => $request->validated('programa_id')]);
            }
        });

        return (new UsuarioResource($usuario->refresh()->load('programa')->loadCount('consentimientos')))->response()->setStatusCode(201);
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
        if ($request->has('programa_id')) {
            abort_unless($usuario->tieneRol(Rol::Docente, Rol::Director), 422, 'Solo los docentes y directores tienen programa.');
            abort_if($usuario->rol === Rol::Director && ! $request->validated('programa_id'), 422, 'El director debe tener un programa.');
            $usuario->update(['programa_id' => $request->validated('programa_id')]);
            Auditoria::registrar('usuario.programa_asignado', $usuario, ['programa_id' => $usuario->programa_id]);
        }

        if ($request->has('activo')) {
            abort_if($usuario->is($request->user()), 422, 'No puedes desactivar tu propia cuenta.');
            $usuario->update(['activo' => $request->boolean('activo')]);
            Auditoria::registrar($usuario->activo ? 'usuario.activado_por_admin' : 'usuario.desactivado', $usuario);

            if (! $usuario->activo) {
                $usuario->tokens()->delete(); // cierra sus sesiones abiertas
            }
        }

        return new UsuarioResource($usuario->load('estudiante.programa', 'empresa', 'programa')->loadCount('consentimientos'));
    }

    /**
     * Panel de administración: lo que está por atender, las cifras generales
     * del periodo actual y el estado de cada programa.
     */
    public function resumen(): JsonResponse
    {
        $activa = fn ($q) => $q->where('estado', EstadoVinculacion::Activa);
        $periodo = Periodo::actual();

        return response()->json([
            'periodo' => $periodo,
            'pendientes' => [
                'empresas_por_validar' => Empresa::where('estado_validacion', EstadoValidacion::Pendiente)->count(),
                'convenios_por_aprobar' => Acuerdo::where('estado', EstadoAcuerdo::Pendiente)->count(),
                'reportes_abiertos' => Reporte::where('estado', '!=', EstadoReporte::Resuelto)->count(),
            ],
            'cifras' => [
                'estudiantes_habilitados' => Estudiante::where('habilitado', true)->where('es_egresado', false)->count(),
                'practicas_activas' => Vinculacion::where('estado', EstadoVinculacion::Activa)->count(),
                'practicas_del_periodo' => Vinculacion::query()->delPeriodo($periodo)->count(),
                'empresas_con_convenio' => Empresa::whereHas('acuerdos', fn ($q) => $q->vigentes())->count(),
            ],
            'programas' => Programa::query()
                ->withCount([
                    'estudiantes as habilitados' => fn ($q) => $q->where('habilitado', true),
                    'estudiantes as hojas_aprobadas' => fn ($q) => $q->whereHas('hojaVida', fn ($h) => $h->where('estado', EstadoHojaVida::Aprobada)),
                    'estudiantes as en_practica' => fn ($q) => $q->whereHas('vinculaciones', $activa),
                ])
                ->orderBy('nombre')
                ->get(['id', 'nombre'])
                ->map(fn (Programa $p) => $p->only('id', 'nombre', 'habilitados', 'hojas_aprobadas', 'en_practica')),
        ]);
    }
}
