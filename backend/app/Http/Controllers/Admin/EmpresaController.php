<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoValidacion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ValidarEmpresaRequest;
use App\Http\Resources\EmpresaResource;
use App\Models\Empresa;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class EmpresaController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate(['estado' => ['nullable', Rule::enum(EstadoValidacion::class)]]);

        $empresas = Empresa::query()
            ->with('usuario')
            ->when($filtros['estado'] ?? null, fn ($q, string $estado) => $q->where('estado_validacion', $estado))
            ->orderByRaw("estado_validacion = 'PENDIENTE' desc")
            ->orderBy('razon_social')
            ->get();

        return EmpresaResource::collection($empresas);
    }

    public function validar(ValidarEmpresaRequest $request, Empresa $empresa): EmpresaResource
    {
        $empresa->update([
            'estado_validacion' => $request->validated('estado_validacion'),
            'validada_por' => $request->user()->id,
            'validada_en' => now(),
        ]);

        return new EmpresaResource($empresa->load('usuario'));
    }
}
