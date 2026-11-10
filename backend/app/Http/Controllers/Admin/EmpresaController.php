<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoValidacion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GuardarEmpresaRequest;
use App\Http\Requests\Admin\ValidarEmpresaRequest;
use App\Http\Resources\EmpresaResource;
use App\Models\Empresa;
use App\Notifications\EmpresaValidada;
use App\Services\Auditoria;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Las empresas se registran en /empresasRegistro y esperan validación. La
 * administración corrige sus datos, las valida y puede suspenderlas.
 */
class EmpresaController extends Controller
{
    private const CAMPOS = ['nit', 'razon_social', 'sector', 'contacto_nombre', 'contacto_telefono', 'modalidad_interes'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate(['estado' => ['nullable', Rule::enum(EstadoValidacion::class)]]);

        $empresas = Empresa::query()
            ->with(['usuario' => fn ($q) => $q->withCount('consentimientos')])
            ->when($filtros['estado'] ?? null, fn ($q, string $estado) => $q->where('estado_validacion', $estado))
            ->orderByRaw("estado_validacion = 'PENDIENTE' desc")
            ->orderBy('razon_social')
            ->get();

        return EmpresaResource::collection($empresas);
    }

    public function update(GuardarEmpresaRequest $request, Empresa $empresa): EmpresaResource
    {
        $empresa->update($request->safe()->only(self::CAMPOS));
        Auditoria::registrar('empresa.actualizada', $empresa, ['campos' => array_keys($empresa->getChanges())]);

        return new EmpresaResource($empresa->load(['usuario' => fn ($q) => $q->withCount('consentimientos')]));
    }

    /** Firma del representante legal con la que la empresa se registró. */
    public function firma(Empresa $empresa): StreamedResponse
    {
        $ruta = $empresa->firma_representante_url;
        abort_unless($ruta && Storage::disk('local')->exists($ruta), 404, 'La empresa no registró la firma de su representante.');

        return Storage::disk('local')->response($ruta);
    }

    public function validar(ValidarEmpresaRequest $request, Empresa $empresa): EmpresaResource
    {
        $pendiente = $empresa->estado_validacion === EstadoValidacion::Pendiente;

        $empresa->update([
            'estado_validacion' => $request->validated('estado_validacion'),
            'validada_por' => $request->user()->id,
            'validada_en' => now(),
        ]);
        Auditoria::registrar('empresa.validacion', $empresa, ['estado' => $empresa->estado_validacion->value]);

        // Una empresa rechazada o suspendida pierde sus sesiones abiertas.
        if (! $empresa->estaValidada()) {
            $empresa->usuario->tokens()->delete();
        } elseif ($pendiente) {
            $empresa->usuario->notify(new EmpresaValidada);
        }

        return new EmpresaResource($empresa->load(['usuario' => fn ($q) => $q->withCount('consentimientos')]));
    }
}
