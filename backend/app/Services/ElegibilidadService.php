<?php

namespace App\Services;

use App\Enums\Modalidad;
use App\Models\Estudiante;
use App\Models\ReglaElegibilidad;
use App\Models\Vacante;

/**
 * Decide por qué modalidades puede vincularse un estudiante.
 *
 * Un estudiante está habilitado para pasantía o práctica cuando su semestre
 * actual alcanza el semestre mínimo de la regla vigente de su programa. Si no
 * lo está (o es egresado), no puede postularse a ninguna vacante.
 */
class ElegibilidadService
{
    /**
     * Reglas ya consultadas en esta petición, por programa. El servicio se
     * registra como "scoped" para no repetir la consulta al listar muchos
     * estudiantes.
     *
     * @var array<int, ReglaElegibilidad|null>
     */
    private array $reglas = [];

    public function reglaVigente(Estudiante $estudiante): ?ReglaElegibilidad
    {
        $programa = $estudiante->programa_id;

        if (! array_key_exists($programa, $this->reglas)) {
            $this->reglas[$programa] = ReglaElegibilidad::query()
                ->where('programa_id', $programa)
                ->vigentes()
                ->orderByDesc('vigente_desde')
                ->first();
        }

        return $this->reglas[$programa];
    }

    public function habilitadoParaPractica(Estudiante $estudiante): bool
    {
        if ($estudiante->es_egresado) {
            return false;
        }

        $regla = $this->reglaVigente($estudiante);

        return $regla !== null && $estudiante->semestre_actual >= $regla->semestre_minimo;
    }

    /**
     * @return list<Modalidad>
     */
    public function modalidadesPermitidas(Estudiante $estudiante): array
    {
        return $this->habilitadoParaPractica($estudiante) ? Modalidad::cases() : [];
    }

    /**
     * Motivo por el que el estudiante no puede postularse a la vacante, o
     * null si puede hacerlo.
     */
    public function motivoParaNoPostular(Estudiante $estudiante, Vacante $vacante): ?string
    {
        if (in_array($vacante->modalidad, $this->modalidadesPermitidas($estudiante), true)) {
            return null;
        }

        if ($estudiante->es_egresado) {
            return 'Los egresados no pueden postularse a pasantías ni prácticas.';
        }

        $regla = $this->reglaVigente($estudiante);

        return $regla
            ? 'Tu programa habilita la '.mb_strtolower($vacante->modalidad->label())." desde {$regla->semestre_minimo}.º semestre."
            : 'Tu programa aún no tiene una regla de práctica vigente.';
    }
}
