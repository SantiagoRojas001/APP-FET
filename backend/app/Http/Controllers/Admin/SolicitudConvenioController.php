<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoSolicitudConvenio;
use App\Http\Controllers\Controller;
use App\Http\Resources\SolicitudConvenioResource;
use App\Models\SolicitudConvenio;
use App\Services\RegistroEmpresaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Solicitudes de convenio de las empresas que aún no lo tienen: la
 * administración les envía el convenio (y se registran con él) o las rechaza.
 */
class SolicitudConvenioController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate(['estado' => ['nullable', Rule::enum(EstadoSolicitudConvenio::class)]]);

        return SolicitudConvenioResource::collection(
            SolicitudConvenio::query()
                ->when($filtros['estado'] ?? null, fn ($q, string $estado) => $q->where('estado', $estado))
                ->orderByRaw("estado = 'PENDIENTE' desc")
                ->latest('creado_en')
                ->get(),
        );
    }

    public function enviar(Request $request, SolicitudConvenio $solicitud, RegistroEmpresaService $registro): SolicitudConvenioResource
    {
        $request->validate(
            ['convenio' => ['required', 'file', 'mimes:pdf', 'max:10240']],
            ['convenio.required' => 'Adjunta el convenio en PDF.'],
        );

        return new SolicitudConvenioResource($registro->enviarConvenio($solicitud, $request->user(), $request->file('convenio')));
    }

    public function rechazar(Request $request, SolicitudConvenio $solicitud, RegistroEmpresaService $registro): SolicitudConvenioResource
    {
        $motivo = $request->validate(
            ['motivo' => ['required', 'string', 'max:1000']],
            ['motivo.required' => 'Escribe el motivo para que la empresa lo conozca.'],
        )['motivo'];

        return new SolicitudConvenioResource($registro->rechazarSolicitud($solicitud, $request->user(), $motivo));
    }

    public function firma(SolicitudConvenio $solicitud): StreamedResponse
    {
        return $this->archivo($solicitud->firma_url);
    }

    public function convenio(SolicitudConvenio $solicitud): StreamedResponse
    {
        return $this->archivo($solicitud->convenio_url, 'convenio.pdf');
    }

    private function archivo(?string $ruta, ?string $nombre = null): StreamedResponse
    {
        abort_unless($ruta && Storage::disk('local')->exists($ruta), 404, 'El archivo ya no está disponible.');

        return $nombre ? Storage::disk('local')->download($ruta, $nombre) : Storage::disk('local')->response($ruta);
    }
}
