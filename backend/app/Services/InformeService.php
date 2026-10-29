<?php

namespace App\Services;

use App\Enums\EstadoCita;
use App\Enums\EstadoRevision;
use App\Enums\EstadoVinculacion;
use App\Models\Cita;
use App\Models\PeriodoAcademico;
use App\Models\Vinculacion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Cifras de las prácticas para la administración (acreditación y registro
 * calificado), opcionalmente de un solo periodo académico, y su exportación
 * a Excel.
 */
class InformeService
{
    /**
     * @return array<string, mixed>
     */
    public function resumen(?PeriodoAcademico $periodo = null): array
    {
        $vinculaciones = $this->vinculaciones($periodo);
        $citas = $this->citas($periodo);

        $porEstado = (clone $citas)->selectRaw('estado, count(*) as total')->groupBy('estado')->pluck('total', 'estado');
        $contar = fn (EstadoCita ...$estados) => collect($estados)->sum(fn ($e) => (int) ($porEstado[$e->value] ?? 0));

        // Tras la cita la empresa decide; los seleccionados pasan al docente y luego a la carta del director.
        $aprobadosDocente = $contar(EstadoCita::Autorizado, EstadoCita::Presentado);
        $seleccionados = $contar(EstadoCita::Seleccionado, EstadoCita::NoAutorizado) + $aprobadosDocente;
        $decididasEmpresa = $seleccionados + $contar(EstadoCita::Descartado);
        $decididasDocente = $aprobadosDocente + $contar(EstadoCita::NoAutorizado);

        return [
            'periodo' => $periodo?->nombre,
            'practicantes_por_programa' => $this->porPrograma($vinculaciones),
            'empresas_con_mas_practicantes' => (clone $vinculaciones)
                ->join('empresa', 'empresa.id', '=', 'vinculacion.empresa_id')
                ->selectRaw('empresa.razon_social as empresa, count(*) as practicantes')
                ->groupBy('empresa.razon_social')
                ->orderByDesc('practicantes')
                ->limit(10)
                ->get()
                ->map(fn ($f) => ['empresa' => $f->empresa, 'practicantes' => (int) $f->practicantes]),
            'citas' => [
                'total' => $porEstado->sum(),
                'seleccionados_por_empresa' => $seleccionados,
                'descartados_por_empresa' => $contar(EstadoCita::Descartado),
                'aprobados_por_docente' => $aprobadosDocente,
                'no_aprobados_por_docente' => $contar(EstadoCita::NoAutorizado),
                'cartas_enviadas' => $contar(EstadoCita::Presentado),
                'tasa_seleccion_empresa' => $decididasEmpresa ? round($seleccionados / $decididasEmpresa * 100, 1) : null,
                'tasa_aprobacion_docente' => $decididasDocente ? round($aprobadosDocente / $decididasDocente * 100, 1) : null,
            ],
            'tiempos_promedio_dias' => [
                'decision_empresa' => $this->promedioDias((clone $citas)->whereNotNull('decidida_en'), 'decidida_en - fecha_hora'),
                'aprobacion_docente' => $this->promedioDias((clone $citas)->whereNotNull('decidida_en')->whereNotNull('autorizada_en'), 'autorizada_en - decidida_en'),
                'carta_director' => $this->promedioDias((clone $citas)->whereNotNull('autorizada_en')->whereNotNull('presentada_en'), 'presentada_en - autorizada_en'),
                'cumplir_horas' => $this->promedioDias((clone $vinculaciones)->whereNotNull('horas_cumplidas_en'), 'horas_cumplidas_en - fecha_inicio::timestamptz'),
                'duracion_practica' => $this->promedioDias(
                    (clone $vinculaciones)->where('estado', '!=', EstadoVinculacion::Activa)->whereNotNull('fecha_fin'),
                    '(fecha_fin - fecha_inicio) * interval \'1 day\'',
                ),
            ],
        ];
    }

    /** Escribe el informe en un archivo .xlsx temporal y devuelve su ruta. */
    public function exportar(?PeriodoAcademico $periodo = null): string
    {
        $datos = $this->resumen($periodo);
        $ruta = sys_get_temp_dir().'/informe-'.Str::uuid().'.xlsx';
        $titulo = new Style(fontBold: true);

        $writer = new Writer;
        $writer->openToFile($ruta);

        $writer->getCurrentSheet()->setName('Resumen');
        $p = $datos['citas'];
        $t = $datos['tiempos_promedio_dias'];
        $writer->addRows([
            Row::fromValuesWithStyle(['Informe de prácticas APP FET', $periodo ? "Periodo {$periodo->nombre}" : 'Todos los periodos'], $titulo),
            Row::fromValues(['Generado', now()->format('d/m/Y H:i')]),
            Row::fromValues([]),
            Row::fromValuesWithStyle(['Citas', 'Valor'], $titulo),
            Row::fromValues(['Total', $p['total']]),
            Row::fromValues(['Seleccionados por la empresa', $p['seleccionados_por_empresa']]),
            Row::fromValues(['Descartados por la empresa', $p['descartados_por_empresa']]),
            Row::fromValues(['Aprobados por el docente', $p['aprobados_por_docente']]),
            Row::fromValues(['No aprobados por el docente', $p['no_aprobados_por_docente']]),
            Row::fromValues(['Cartas de presentación enviadas', $p['cartas_enviadas']]),
            Row::fromValues(['Tasa de selección de empresas (%)', $p['tasa_seleccion_empresa']]),
            Row::fromValues(['Tasa de aprobación docente (%)', $p['tasa_aprobacion_docente']]),
            Row::fromValues([]),
            Row::fromValuesWithStyle(['Tiempo promedio por etapa', 'Días'], $titulo),
            Row::fromValues(['Cita a decisión de la empresa', $t['decision_empresa']]),
            Row::fromValues(['Selección a aprobación docente', $t['aprobacion_docente']]),
            Row::fromValues(['Aprobación docente a carta de presentación', $t['carta_director']]),
            Row::fromValues(['Inicio a completar las horas', $t['cumplir_horas']]),
            Row::fromValues(['Duración de la práctica', $t['duracion_practica']]),
        ]);

        $writer->addNewSheetAndMakeItCurrent()->setName('Por programa');
        $writer->addRow(Row::fromValuesWithStyle(['Programa', 'Activas', 'Finalizadas', 'Terminadas anticipadamente', 'Total'], $titulo));
        foreach ($datos['practicantes_por_programa'] as $f) {
            $writer->addRow(Row::fromValues([$f['programa'], $f['activas'], $f['finalizadas'], $f['terminadas'], $f['total']]));
        }

        $writer->addNewSheetAndMakeItCurrent()->setName('Empresas');
        $writer->addRow(Row::fromValuesWithStyle(['Empresa', 'Practicantes'], $titulo));
        foreach ($datos['empresas_con_mas_practicantes'] as $f) {
            $writer->addRow(Row::fromValues([$f['empresa'], $f['practicantes']]));
        }

        $writer->addNewSheetAndMakeItCurrent()->setName('Prácticas');
        $writer->addRow(Row::fromValuesWithStyle(
            ['Periodo', 'Código', 'Estudiante', 'Programa', 'Empresa', 'Modalidad', 'Estado', 'Inicio', 'Fin', 'Horas aprobadas', 'Horas requeridas', 'Reconocida'],
            $titulo,
        ));
        $this->vinculaciones($periodo)
            ->with('estudiante.programa', 'empresa', 'periodo')
            ->withSum(['evidencias as horas_aprobadas' => fn ($q) => $q->where('estado', EstadoRevision::Aprobado)], 'horas')
            ->orderBy('fecha_inicio')
            ->each(fn (Vinculacion $v) => $writer->addRow(Row::fromValues([
                $v->periodo?->nombre ?? '',
                $v->estudiante->codigo,
                $v->estudiante->nombre_completo,
                $v->estudiante->programa->nombre,
                $v->empresa->razon_social,
                $v->tipo->label(),
                $v->estado->label(),
                $v->fecha_inicio->format('d/m/Y'),
                $v->fecha_fin?->format('d/m/Y') ?? '',
                (float) $v->horas_aprobadas,
                $v->estudiante->programa->horas_requeridas,
                match ($v->reconocida_como_practica) {
                    true => 'Sí',
                    false => 'No',
                    null => '',
                },
            ])));

        $writer->close();

        return $ruta;
    }

    /** @return Builder<Vinculacion> */
    private function vinculaciones(?PeriodoAcademico $periodo): Builder
    {
        return Vinculacion::query()->when($periodo, fn ($q) => $q->where('vinculacion.periodo_id', $periodo->id));
    }

    /** Citas programadas dentro de las fechas del periodo. */
    private function citas(?PeriodoAcademico $periodo): Builder
    {
        return Cita::query()->when($periodo, fn ($q) => $q
            ->whereDate('fecha_hora', '>=', $periodo->fecha_inicio)
            ->whereDate('fecha_hora', '<=', $periodo->fecha_fin));
    }

    /**
     * @param  Builder<Vinculacion>  $vinculaciones
     * @return list<array{programa: string, activas: int, finalizadas: int, terminadas: int, total: int}>
     */
    private function porPrograma(Builder $vinculaciones): array
    {
        $filas = (clone $vinculaciones)
            ->join('estudiante', 'estudiante.id', '=', 'vinculacion.estudiante_id')
            ->join('programa', 'programa.id', '=', 'estudiante.programa_id')
            ->selectRaw('programa.nombre as programa, vinculacion.estado, count(*) as total')
            ->groupBy('programa.nombre', 'vinculacion.estado')
            ->get();

        return $filas->groupBy('programa')->map(function ($grupo, string $programa) {
            $n = fn (EstadoVinculacion $e) => (int) $grupo->firstWhere('estado', $e)?->total;

            return [
                'programa' => $programa,
                'activas' => $n(EstadoVinculacion::Activa),
                'finalizadas' => $n(EstadoVinculacion::Finalizada),
                'terminadas' => $n(EstadoVinculacion::Cancelada),
                'total' => (int) $grupo->sum('total'),
            ];
        })->sortBy('programa')->values()->all();
    }

    /** Promedio en días de un intervalo de PostgreSQL, con un decimal. */
    private function promedioDias(Builder $query, string $intervalo): ?float
    {
        $segundos = $query->toBase()->value(DB::raw("avg(extract(epoch from ({$intervalo})))"));

        return $segundos === null ? null : round($segundos / 86400, 1);
    }
}
