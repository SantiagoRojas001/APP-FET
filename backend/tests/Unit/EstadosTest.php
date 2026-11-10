<?php

namespace Tests\Unit;

use App\Enums\EstadoCita;
use App\Enums\EstadoHojaVida;
use App\Enums\EstadoSolicitudConvenio;
use App\Enums\Rol;
use App\Enums\TipoDocumento;
use App\Enums\TipoEvaluacion;
use BackedEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Estados y catálogos (enums): cada valor tiene su etiqueta en español y la
 * lista de valores que usan las restricciones CHECK de la base es completa.
 */
class EstadosTest extends TestCase
{
    /** @return array<string, array{class-string<BackedEnum>}> */
    public static function enums(): array
    {
        return [
            'estados de la cita' => [EstadoCita::class],
            'estados de la hoja de vida' => [EstadoHojaVida::class],
            'estados de la solicitud de convenio' => [EstadoSolicitudConvenio::class],
            'roles' => [Rol::class],
            'tipos de documento' => [TipoDocumento::class],
        ];
    }

    /** @param  class-string<BackedEnum>  $enum */
    #[DataProvider('enums')]
    public function test_cada_valor_tiene_etiqueta_y_values_los_lista_todos(string $enum): void
    {
        foreach ($enum::cases() as $caso) {
            $this->assertNotSame('', $caso->label(), "{$enum}::{$caso->name} no tiene etiqueta.");
        }
        $this->assertSame(array_column($enum::cases(), 'value'), $enum::values());
    }

    public function test_las_citas_abiertas_son_las_programadas_y_las_aceptadas(): void
    {
        $this->assertSame([EstadoCita::Programada, EstadoCita::Aceptado], EstadoCita::abiertos());
    }

    public function test_cada_rol_que_evalua_tiene_su_cuestionario(): void
    {
        $this->assertNotNull(TipoEvaluacion::delRol(Rol::Docente));
        $this->assertNotNull(TipoEvaluacion::delRol(Rol::Empresa));
        $this->assertNotNull(TipoEvaluacion::delRol(Rol::Estudiante));
        $this->assertNull(TipoEvaluacion::delRol(Rol::Admin));
    }
}
