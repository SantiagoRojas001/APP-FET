<?php

namespace Tests\Unit;

use App\Exceptions\ReglaNegocioException;
use App\Services\FirmaService;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\TestCase;

/**
 * Validación de la firma dibujada: PNG transparente con un trazo oscuro de un
 * solo color, ni vacío ni relleno.
 */
class FirmaServiceTest extends TestCase
{
    /** PNG de 1200 × 400 transparente; dibuja un trazo, lo rellena o lo deja vacío. */
    private function imagen(array $rgb = [0x1A, 0x1D, 0x24], string $modo = 'trazo', bool $transparente = true): UploadedFile
    {
        $imagen = imagecreatetruecolor(1200, 400);
        imagesavealpha($imagen, true);
        imagealphablending($imagen, false);
        imagefill($imagen, 0, 0, $transparente ? imagecolorallocatealpha($imagen, 0, 0, 0, 127) : imagecolorallocate($imagen, 255, 255, 255));
        imagealphablending($imagen, true);
        $tinta = imagecolorallocate($imagen, ...$rgb);

        if ($modo === 'relleno') {
            imagefilledrectangle($imagen, 40, 40, 1159, 359, $tinta); // las esquinas siguen transparentes
        } elseif ($modo === 'punto') {
            imagefilledellipse($imagen, 600, 200, 8, 8, $tinta);
        } elseif ($modo === 'trazo') {
            imagesetthickness($imagen, 5);
            for ($x = 100; $x < 1000; $x += 40) {
                imageline($imagen, $x, 200 + (int) (80 * sin($x / 60)), $x + 40, 200 + (int) (80 * sin(($x + 40) / 60)), $tinta);
            }
        }

        $ruta = tempnam(sys_get_temp_dir(), 'firma').'.png';
        imagepng($imagen, $ruta);

        return new UploadedFile($ruta, 'firma.png', 'image/png', null, true);
    }

    private function rechazo(UploadedFile $archivo): string
    {
        try {
            (new FirmaService)->validar($archivo);
        } catch (ReglaNegocioException $e) {
            return $e->getMessage();
        }
        $this->fail('La firma debió rechazarse.');
    }

    public function test_acepta_un_trazo_oscuro_sobre_fondo_transparente(): void
    {
        $png = (new FirmaService)->validar($this->imagen());

        $this->assertStringStartsWith("\x89PNG", $png);
    }

    public function test_la_firma_aceptada_se_vuelve_a_generar_y_conserva_la_transparencia(): void
    {
        $imagen = imagecreatefromstring((new FirmaService)->validar($this->imagen()));

        $this->assertSame(1200, imagesx($imagen));
        $this->assertSame(127, imagecolorat($imagen, 0, 0) >> 24 & 0x7F);
    }

    public function test_rechaza_una_firma_vacia(): void
    {
        $this->assertStringContainsString('vacía', $this->rechazo($this->imagen(modo: 'vacio')));
    }

    public function test_rechaza_un_trazo_demasiado_pequeno(): void
    {
        $this->assertStringContainsString('muy pequeña', $this->rechazo($this->imagen(modo: 'punto')));
    }

    public function test_rechaza_una_imagen_rellena(): void
    {
        $this->assertStringContainsString('relleno', $this->rechazo($this->imagen(modo: 'relleno')));
    }

    public function test_rechaza_trazos_de_color(): void
    {
        $this->assertStringContainsString('trazo oscuro', $this->rechazo($this->imagen([0xE5, 0x39, 0x35])));
    }

    public function test_rechaza_imagenes_con_fondo(): void
    {
        $this->assertStringContainsString('sin fondo', $this->rechazo($this->imagen(transparente: false)));
    }

    public function test_rechaza_un_archivo_que_no_es_png(): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'firma');
        file_put_contents($ruta, '%PDF-1.4 no es una imagen');

        $this->assertStringContainsString('no es una imagen válida', $this->rechazo(new UploadedFile($ruta, 'firma.png', 'image/png', null, true)));
    }
}
