<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Firmas dibujadas en la app: la del estudiante en su hoja de vida y la del
 * representante legal al registrar una empresa. No se sube un archivo
 * cualquiera: aquí se comprueba que la imagen sea un trazo y no
 * una foto, un logo o un recuadro vacío o relleno:
 *
 * - PNG con fondo transparente (las esquinas lo son);
 * - trazos oscuros de un solo color (los que dibuja el recuadro);
 * - tinta suficiente, sin rellenar la imagen, y repartida a lo ancho.
 *
 * La imagen se vuelve a generar con GD: lo que se guarda son solo píxeles.
 */
class FirmaService
{
    private const TINTA_MINIMA = 0.004;

    private const TINTA_MAXIMA = 0.25;

    /** Valida la firma y la guarda en el disco privado; devuelve su ruta. */
    public function guardar(UploadedFile $archivo, string $carpeta): string
    {
        $ruta = "{$carpeta}/".Str::uuid().'.png';
        Storage::disk('local')->put($ruta, $this->validar($archivo));

        return $ruta;
    }

    /** Devuelve el PNG limpio de la firma o lanza la razón del rechazo. */
    public function validar(UploadedFile $archivo): string
    {
        $imagen = @imagecreatefrompng($archivo->getRealPath());
        if ($imagen === false) {
            throw new ReglaNegocioException('La firma no es una imagen válida. Dibújala de nuevo.');
        }

        imagepalettetotruecolor($imagen);

        return $this->revisar($imagen);
    }

    private function revisar(\GdImage $imagen): string
    {
        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);

        foreach ([[0, 0], [$ancho - 1, 0], [0, $alto - 1], [$ancho - 1, $alto - 1]] as [$x, $y]) {
            if ((imagecolorat($imagen, $x, $y) >> 24 & 0x7F) < 120) {
                throw new ReglaNegocioException('La firma debe dibujarse en el recuadro de la app, sin fondo.');
            }
        }

        $tinta = 0;
        $oscura = 0;
        [$minX, $maxX] = [$ancho, 0];
        // Se revisa un píxel de cada dos en cada eje: suficiente para medir los trazos.
        for ($y = 0; $y < $alto; $y += 2) {
            for ($x = 0; $x < $ancho; $x += 2) {
                $color = imagecolorat($imagen, $x, $y);
                if (($color >> 24 & 0x7F) > 60) {
                    continue; // transparente
                }
                $tinta++;
                // Oscuro y neutro: ningún canal claro (descarta colores, aunque sean intensos).
                $oscura += max($color >> 16 & 0xFF, $color >> 8 & 0xFF, $color & 0xFF) < 100 ? 1 : 0;
                [$minX, $maxX] = [min($minX, $x), max($maxX, $x)];
            }
        }

        $muestras = intdiv($ancho + 1, 2) * intdiv($alto + 1, 2);
        $proporcion = $tinta / max(1, $muestras);

        if ($proporcion < self::TINTA_MINIMA || ($maxX - $minX) < $ancho * 0.15) {
            throw new ReglaNegocioException('La firma está vacía o es muy pequeña. Dibújala de nuevo, ocupando el recuadro.');
        }
        if ($proporcion > self::TINTA_MAXIMA) {
            throw new ReglaNegocioException('La imagen no parece una firma: tiene demasiado relleno.');
        }
        if ($oscura / $tinta < 0.9) {
            throw new ReglaNegocioException('La firma debe ser un trazo oscuro de un solo color, dibujado en la app.');
        }

        imagesavealpha($imagen, true);
        ob_start();
        imagepng($imagen, null, 9);

        return (string) ob_get_clean();
    }
}
