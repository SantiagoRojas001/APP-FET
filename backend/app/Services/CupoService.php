<?php

namespace App\Services;

use App\Enums\Ciclo;
use App\Enums\Modalidad;
use App\Exceptions\ReglaNegocioException;
use App\Models\CupoPasantia;
use App\Models\Estudiante;
use App\Support\Periodo;
use Illuminate\Database\Eloquent\Builder;

/**
 * Cupos de pasantía por programa y periodo. Solo los ciclos técnico y
 * tecnólogo tienen límite; la práctica y el ciclo profesional no.
 */
class CupoService
{
    public function aplica(Modalidad $modalidad, int $semestre): bool
    {
        return $modalidad === Modalidad::Pasantia && Ciclo::deSemestre($semestre) !== Ciclo::Profesional;
    }

    /** @return array{periodo: string, TECNICO: array<string, int>, TECNOLOGO: array<string, int>} */
    public function resumen(int $programaId): array
    {
        $periodo = Periodo::actual();
        $cupo = CupoPasantia::query()->firstOrNew(['programa_id' => $programaId, 'periodo' => $periodo]);

        return [
            'periodo' => $periodo,
            Ciclo::Tecnico->value => $this->ciclo($programaId, Ciclo::Tecnico, $cupo->tecnico, $periodo),
            Ciclo::Tecnologo->value => $this->ciclo($programaId, Ciclo::Tecnologo, $cupo->tecnologo, $periodo),
        ];
    }

    public function actualizar(int $programaId, int $tecnico, int $tecnologo): void
    {
        CupoPasantia::query()->updateOrCreate(
            ['programa_id' => $programaId, 'periodo' => Periodo::actual()],
            ['tecnico' => $tecnico, 'tecnologo' => $tecnologo],
        );
    }

    /** Debe llamarse dentro de la transacción del registro, con el programa bloqueado. */
    public function exigirDisponible(int $programaId, Modalidad $modalidad, int $semestre): void
    {
        if (! $this->aplica($modalidad, $semestre)) {
            return;
        }

        $ciclo = Ciclo::deSemestre($semestre);
        if ($this->resumen($programaId)[$ciclo->value]['disponibles'] < 1) {
            throw new ReglaNegocioException("No quedan cupos de pasantía para el ciclo {$ciclo->label()} en este periodo.");
        }
    }

    /** @return array{limite: int, usados: int, disponibles: int} */
    private function ciclo(int $programaId, Ciclo $ciclo, int $limite, string $periodo): array
    {
        [$inicio, $fin] = Periodo::rango($periodo);

        $usados = Estudiante::query()
            ->where('programa_id', $programaId)
            ->where('modalidad_grado', Modalidad::Pasantia)
            ->delCiclo($ciclo)
            ->whereHas('usuario', fn (Builder $u) => $u->whereBetween('creado_en', [$inicio->startOfDay(), $fin->endOfDay()]))
            ->count();

        return ['limite' => $limite, 'usados' => $usados, 'disponibles' => max(0, $limite - $usados)];
    }
}
