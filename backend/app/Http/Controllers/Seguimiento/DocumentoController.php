<?php

namespace App\Http\Controllers\Seguimiento;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seguimiento\RevisarDocumentoRequest;
use App\Http\Requests\Seguimiento\SubirDocumentoRequest;
use App\Http\Resources\DocumentoSeguimientoResource;
use App\Http\Resources\VinculacionResource;
use App\Models\DocumentoSeguimiento;
use App\Models\Vinculacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controles de asistencia, diarios de campo e informes de una vinculación.
 * Los archivos se guardan en el disco privado y solo se descargan por aquí.
 */
class DocumentoController extends Controller
{
    public function vinculacion(Vinculacion $vinculacion): VinculacionResource
    {
        Gate::authorize('view', $vinculacion);

        return new VinculacionResource($vinculacion->load('estudiante.programa', 'empresa', 'vacante', 'tutor', 'documentos'));
    }

    public function index(Vinculacion $vinculacion): AnonymousResourceCollection
    {
        Gate::authorize('view', $vinculacion);

        return DocumentoSeguimientoResource::collection($vinculacion->documentos);
    }

    public function store(SubirDocumentoRequest $request, Vinculacion $vinculacion): JsonResponse
    {
        Gate::authorize('subirDocumento', $vinculacion);

        $ruta = $request->file('archivo')->store("documentos/vinculacion-{$vinculacion->id}", 'local');

        $documento = $vinculacion->documentos()->create([
            ...$request->safe()->except('archivo'),
            'archivo_url' => $ruta,
            'cargado_por' => $request->user()->id,
        ]);

        return (new DocumentoSeguimientoResource($documento->refresh()))->response()->setStatusCode(201);
    }

    public function revisar(RevisarDocumentoRequest $request, DocumentoSeguimiento $documento): DocumentoSeguimientoResource
    {
        Gate::authorize('revisarDocumentos', $documento->vinculacion);

        $documento->update($request->validated());

        return new DocumentoSeguimientoResource($documento);
    }

    public function descargar(DocumentoSeguimiento $documento): StreamedResponse
    {
        Gate::authorize('view', $documento->vinculacion);

        abort_unless(Storage::disk('local')->exists($documento->archivo_url), 404, 'El archivo ya no está disponible.');

        $nombre = sprintf('%s_%s.pdf', strtolower($documento->tipo->value), $documento->periodo_inicio->format('Y-m-d'));

        return Storage::disk('local')->download($documento->archivo_url, $nombre);
    }
}
