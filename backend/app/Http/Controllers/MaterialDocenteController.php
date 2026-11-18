<?php

namespace App\Http\Controllers;

use App\Enums\Rol;
use App\Models\MaterialDocente;
use App\Services\Auditoria;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Materiales que el docente comparte con su grupo: los administra él y los
 * consultan los estudiantes que asesora.
 */
class MaterialDocenteController extends Controller
{
    public function deDocente(Request $request): JsonResponse
    {
        return $this->respuesta($this->delDocente($request->user()->id));
    }

    public function deEstudiante(Request $request): JsonResponse
    {
        $tutor = $this->tutorDe($request);

        return $this->respuesta($tutor ? $this->delDocente($tutor) : new Collection);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'titulo' => ['required', 'string', 'max:150'],
            'archivo' => ['required', 'file', 'mimes:'.implode(',', config('fet.documentos.extensiones')), 'max:'.config('fet.documentos.max_kb')],
        ]);

        $material = MaterialDocente::query()->create([
            'docente_id' => $request->user()->id,
            'titulo' => $datos['titulo'],
            'archivo_url' => $request->file('archivo')->store('materiales', 'local'),
        ]);
        Auditoria::registrar('material.creado', $material);

        return response()->json($this->fila($material->refresh()), 201);
    }

    public function destroy(Request $request, MaterialDocente $material): Response
    {
        abort_unless($material->docente_id === $request->user()->id, 403);

        Storage::disk('local')->delete($material->archivo_url);
        $material->delete();

        return response()->noContent();
    }

    public function archivo(Request $request, MaterialDocente $material): StreamedResponse
    {
        $usuario = $request->user();
        abort_unless(match ($usuario->rol) {
            Rol::Docente => $material->docente_id === $usuario->id,
            Rol::Estudiante => $material->docente_id === $this->tutorDe($request),
            default => false,
        }, 403);
        abort_unless(Storage::disk('local')->exists($material->archivo_url), 404, 'El archivo ya no está disponible.');

        return Storage::disk('local')->download($material->archivo_url, "{$material->titulo}.pdf");
    }

    private function tutorDe(Request $request): ?int
    {
        $estudiante = $request->user()->estudiante()->with('vinculacionActiva')->firstOrFail();

        return $estudiante->tutor_id ?? $estudiante->vinculacionActiva?->tutor_id;
    }

    /** @return Collection<int, MaterialDocente> */
    private function delDocente(int $docenteId): Collection
    {
        return MaterialDocente::query()->where('docente_id', $docenteId)->latest('creado_en')->get();
    }

    /** @param  Collection<int, MaterialDocente>  $materiales */
    private function respuesta(Collection $materiales): JsonResponse
    {
        return response()->json($materiales->map(fn (MaterialDocente $m) => $this->fila($m))->values());
    }

    /** @return array<string, mixed> */
    private function fila(MaterialDocente $material): array
    {
        return [
            'id' => $material->id,
            'titulo' => $material->titulo,
            'creado_en' => $material->creado_en?->toIso8601String(),
            'url_descarga' => "/api/materiales/{$material->id}/archivo",
        ];
    }
}
