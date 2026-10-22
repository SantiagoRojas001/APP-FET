<?php

namespace App\Http\Controllers\Estudiante;

use App\Http\Controllers\Controller;
use App\Services\ElegibilidadService;
use App\Services\HojaVidaService;
use BackedEnum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Explica al estudiante por qué modalidades puede postularse y qué le falta.
 */
class ElegibilidadController extends Controller
{
    public function __invoke(Request $request, ElegibilidadService $elegibilidad, HojaVidaService $hojaVida): JsonResponse
    {
        $estudiante = $request->user()->estudiante()->with('programa', 'vinculacionActiva')->firstOrFail();
        $regla = $elegibilidad->reglaVigente($estudiante);

        return response()->json([
            'semestre_actual' => $estudiante->semestre_actual,
            'es_egresado' => $estudiante->es_egresado,
            'programa' => $estudiante->programa->nombre,
            'semestre_minimo' => $regla?->semestre_minimo,
            'habilitado_para_practica' => $elegibilidad->habilitadoParaPractica($estudiante),
            'modalidades_permitidas' => array_map(
                fn (BackedEnum $m) => ['valor' => $m->value, 'etiqueta' => $m->label()],
                $elegibilidad->modalidadesPermitidas($estudiante),
            ),
            'hoja_vida_porcentaje' => $hojaVida->porcentaje($estudiante),
            'vinculacion_activa_id' => $estudiante->vinculacionActiva?->id,
        ]);
    }
}
