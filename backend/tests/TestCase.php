<?php

namespace Tests;

use App\Enums\Rol;
use App\Models\Empresa;
use App\Models\Estudiante;
use App\Models\Programa;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    /** Programa de 10 semestres. */
    protected function programa(): Programa
    {
        return Programa::factory()->create(['total_semestres' => 10]);
    }

    /** Estudiante habilitado (promedio registrado y suficiente). */
    protected function estudiante(int $semestre, ?Programa $programa = null): Estudiante
    {
        return Estudiante::factory()
            ->for($programa ?? $this->programa(), 'programa')
            ->enSemestre($semestre)
            ->create();
    }

    protected function comoEstudiante(Estudiante $estudiante): static
    {
        Sanctum::actingAs($estudiante->usuario);

        return $this;
    }

    protected function comoEmpresa(Empresa $empresa): static
    {
        Sanctum::actingAs($empresa->usuario);

        return $this;
    }

    /** Firma como la que se dibuja en la app: PNG transparente con un trazo (o vacío, o relleno). */
    protected function firmaPng(string $color = '#1A1D24', bool $relleno = false, bool $vacio = false): UploadedFile
    {
        $imagen = imagecreatetruecolor(1200, 400);
        imagesavealpha($imagen, true);
        imagealphablending($imagen, false);
        imagefill($imagen, 0, 0, imagecolorallocatealpha($imagen, 0, 0, 0, 127));
        imagealphablending($imagen, true);
        [$r, $g, $b] = sscanf($color, '#%02x%02x%02x');
        $tinta = imagecolorallocate($imagen, $r, $g, $b);
        if ($relleno) {
            imagefilledrectangle($imagen, 0, 0, 1199, 399, $tinta);
        } elseif (! $vacio) {
            imagesetthickness($imagen, 5);
            for ($x = 100; $x < 1000; $x += 40) {
                imageline($imagen, $x, 200 + (int) (80 * sin($x / 60)), $x + 40, 200 + (int) (80 * sin(($x + 40) / 60)), $tinta);
            }
        }
        $ruta = tempnam(sys_get_temp_dir(), 'firma').'.png';
        imagepng($imagen, $ruta);

        return new UploadedFile($ruta, 'firma.png', 'image/png', null, true);
    }

    protected function como(Rol $rol): Usuario
    {
        // refresh(): trae los valores por defecto de la base (creado_en, tema), como en una petición real.
        $usuario = Usuario::factory()->rol($rol)->create()->refresh();
        Sanctum::actingAs($usuario);

        return $usuario;
    }
}
