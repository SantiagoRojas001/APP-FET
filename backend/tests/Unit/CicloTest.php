<?php

namespace Tests\Unit;

use App\Enums\Ciclo;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Ciclo académico según el semestre: técnico hasta 4.º, tecnólogo en 5.º y
 * 6.º y profesional de 7.º a 10.º.
 */
class CicloTest extends TestCase
{
    /** @return array<string, array{int, Ciclo}> */
    public static function semestres(): array
    {
        return [
            '1.º semestre' => [1, Ciclo::Tecnico],
            '4.º semestre (último técnico)' => [4, Ciclo::Tecnico],
            '5.º semestre (primer tecnólogo)' => [5, Ciclo::Tecnologo],
            '6.º semestre' => [6, Ciclo::Tecnologo],
            '7.º semestre (primer profesional)' => [7, Ciclo::Profesional],
            '10.º semestre' => [10, Ciclo::Profesional],
        ];
    }

    #[DataProvider('semestres')]
    public function test_el_ciclo_sale_del_semestre(int $semestre, Ciclo $esperado): void
    {
        $this->assertSame($esperado, Ciclo::deSemestre($semestre));
    }

    public function test_cada_ciclo_conoce_su_primer_y_ultimo_semestre(): void
    {
        $this->assertSame([1, 4], Ciclo::Tecnico->semestres());
        $this->assertSame([5, 6], Ciclo::Tecnologo->semestres());
        $this->assertSame([7, 10], Ciclo::Profesional->semestres());
    }

    public function test_los_rangos_de_los_ciclos_cubren_los_diez_semestres_sin_cruzarse(): void
    {
        $cubiertos = [];
        foreach (Ciclo::cases() as $ciclo) {
            [$desde, $hasta] = $ciclo->semestres();
            $cubiertos = [...$cubiertos, ...range($desde, $hasta)];
        }

        $this->assertSame(range(1, 10), $cubiertos);
    }

    public function test_las_etiquetas_se_muestran_en_espanol(): void
    {
        $this->assertSame(['Técnico', 'Tecnólogo', 'Profesional'], array_map(fn (Ciclo $c) => $c->label(), Ciclo::cases()));
    }
}
