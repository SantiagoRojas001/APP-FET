<?php

namespace Tests\Unit;

use App\Support\Periodo;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Periodo académico calculado con la fecha: de enero a junio es AAAA-1 y de
 * julio a diciembre AAAA-2.
 */
class PeriodoTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function fechas(): array
    {
        return [
            'primer día del año' => ['2026-01-01', '2026-1'],
            'último día de junio' => ['2026-06-30', '2026-1'],
            'primer día de julio' => ['2026-07-01', '2026-2'],
            'último día del año' => ['2026-12-31', '2026-2'],
        ];
    }

    #[DataProvider('fechas')]
    public function test_el_periodo_sale_de_la_fecha(string $fecha, string $esperado): void
    {
        $this->assertSame($esperado, Periodo::deFecha(CarbonImmutable::parse($fecha)));
    }

    public function test_el_rango_del_primer_semestre_va_de_enero_a_junio(): void
    {
        [$inicio, $fin] = Periodo::rango('2026-1');

        $this->assertSame('2026-01-01', $inicio->toDateString());
        $this->assertSame('2026-06-30', $fin->toDateString());
    }

    public function test_el_rango_del_segundo_semestre_va_de_julio_a_diciembre(): void
    {
        [$inicio, $fin] = Periodo::rango('2026-2');

        $this->assertSame('2026-07-01', $inicio->toDateString());
        $this->assertSame('2026-12-31', $fin->toDateString());
    }

    public function test_toda_fecha_del_rango_pertenece_a_su_periodo(): void
    {
        [$inicio, $fin] = Periodo::rango('2025-2');

        for ($dia = $inicio; $dia->lte($fin); $dia = $dia->addDays(9)) {
            $this->assertSame('2025-2', Periodo::deFecha($dia));
        }
    }
}
