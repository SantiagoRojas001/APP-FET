<?php

namespace App\Http\Controllers\Seguimiento;

use App\Enums\TipoFormato;
use App\Http\Controllers\Controller;
use App\Models\Vinculacion;
use App\Services\FormatoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Formatos institucionales de una práctica: consulta, diligenciamiento, firma y PDF.
 */
class FormatoController extends Controller
{
    public function index(Request $request, Vinculacion $vinculacion, FormatoService $formatos): JsonResponse
    {
        Gate::authorize('view', $vinculacion);

        return response()->json($formatos->lista($vinculacion, $request->user()));
    }

    public function update(Request $request, Vinculacion $vinculacion, TipoFormato $tipo, FormatoService $formatos): JsonResponse
    {
        Gate::authorize('view', $vinculacion);
        $formatos->guardar($vinculacion, $request->user(), $tipo, $request->all());

        return response()->json($formatos->lista($vinculacion, $request->user()));
    }

    public function firmar(Request $request, Vinculacion $vinculacion, TipoFormato $tipo, FormatoService $formatos): JsonResponse
    {
        Gate::authorize('view', $vinculacion);
        $formatos->firmar($vinculacion, $request->user(), $tipo);

        return response()->json($formatos->lista($vinculacion, $request->user()));
    }

    public function pdf(Vinculacion $vinculacion, TipoFormato $tipo, FormatoService $formatos): Response
    {
        Gate::authorize('view', $vinculacion);

        return response($formatos->pdf($vinculacion, $tipo), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.strtolower($tipo->value).'.pdf"',
        ]);
    }
}
