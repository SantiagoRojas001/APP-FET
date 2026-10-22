<?php

namespace App\Http\Controllers;

use App\Enums\Modalidad;
use App\Enums\NivelCompetencia;
use App\Enums\TipoDocumento;
use App\Http\Resources\ProgramaResource;
use App\Models\Competencia;
use App\Models\Programa;
use BackedEnum;
use Illuminate\Http\JsonResponse;

/**
 * Listas que el cliente necesita para formularios (incluido el registro).
 */
class CatalogoController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $opciones = fn (array $casos) => array_map(fn (BackedEnum $c) => ['valor' => $c->value, 'etiqueta' => $c->label()], $casos);

        return response()->json([
            'programas' => ProgramaResource::collection(Programa::orderBy('nombre')->get()),
            'competencias' => Competencia::orderBy('nombre')->get(['id', 'nombre']),
            'modalidades' => $opciones(Modalidad::cases()),
            'niveles_competencia' => $opciones(NivelCompetencia::cases()),
            'tipos_documento' => $opciones(TipoDocumento::cases()),
            'dominio_correo' => config('fet.dominio_correo'),
            'version_politica' => config('fet.version_politica'),
        ]);
    }
}
