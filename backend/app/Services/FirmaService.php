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

    private const REJILLA = [60, 20];

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
        // Se revisa la cabecera antes de decodificar: un archivo que no es PNG no llega a GD.
        $tipo = getimagesize($archivo->getRealPath())[2] ?? null;
        $imagen = $tipo === IMAGETYPE_PNG ? imagecreatefrompng($archivo->getRealPath()) : false;
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

    /**
     * Parecido aproximado (0 a 100) entre la firma dibujada y el recorte de la
     * cédula que marcó el estudiante. Compara la forma general de los trazos:
     * es solo una ayuda para el docente, no una verificación.
     */
    public function similitud(string $firma, string $recorte): ?int
    {
        $dibujo = @imagecreatefromstring($firma);
        $zona = @imagecreatefromstring($recorte);
        if ($dibujo === false || $zona === false) {
            return null;
        }

        imagepalettetotruecolor($dibujo);
        imagepalettetotruecolor($zona);

        $a = $this->rejilla($dibujo, fn (int $color) => ($color >> 24 & 0x7F) <= 60);
        $umbral = $this->luminanciaMedia($zona) * 0.7;
        $b = $this->rejilla($zona, fn (int $color) => $this->luminancia($color) < $umbral);
        if ($a === [] || $b === []) {
            return null;
        }

        return (int) round(($this->cobertura($a, $b) + $this->cobertura($b, $a)) * 50);
    }

    /**
     * Celdas con tinta, con la firma ajustada a su propio recuadro.
     *
     * @return array<string, true>
     */
    private function rejilla(\GdImage $imagen, callable $esTinta): array
    {
        $puntos = [];
        for ($y = 0; $y < imagesy($imagen); $y++) {
            for ($x = 0; $x < imagesx($imagen); $x++) {
                if ($esTinta(imagecolorat($imagen, $x, $y))) {
                    $puntos[] = [$x, $y];
                }
            }
        }
        if ($puntos === []) {
            return [];
        }

        [$xs, $ys] = [array_column($puntos, 0), array_column($puntos, 1)];
        [$minX, $minY] = [min($xs), min($ys)];
        $anchoTinta = max(1, max($xs) - $minX);
        $altoTinta = max(1, max($ys) - $minY);
        [$columnas, $filas] = self::REJILLA;

        $celdas = [];
        foreach ($puntos as [$x, $y]) {
            $celdas[(int) (($x - $minX) / $anchoTinta * ($columnas - 1)).':'.(int) (($y - $minY) / $altoTinta * ($filas - 1))] = true;
        }

        return $celdas;
    }

    /**
     * Parte de las celdas de $a con una celda de $b a su lado.
     *
     * @param  array<string, true>  $a
     * @param  array<string, true>  $b
     */
    private function cobertura(array $a, array $b): float
    {
        $cerca = 0;
        foreach (array_keys($a) as $celda) {
            [$x, $y] = array_map('intval', explode(':', $celda));
            foreach ([-1, 0, 1] as $dx) {
                foreach ([-1, 0, 1] as $dy) {
                    if (isset($b[($x + $dx).':'.($y + $dy)])) {
                        $cerca++;

                        continue 3;
                    }
                }
            }
        }

        return $cerca / count($a);
    }

    private function luminancia(int $color): float
    {
        return 0.299 * ($color >> 16 & 0xFF) + 0.587 * ($color >> 8 & 0xFF) + 0.114 * ($color & 0xFF);
    }

    private function luminanciaMedia(\GdImage $imagen): float
    {
        $suma = 0;
        for ($y = 0; $y < imagesy($imagen); $y++) {
            for ($x = 0; $x < imagesx($imagen); $x++) {
                $suma += $this->luminancia(imagecolorat($imagen, $x, $y));
            }
        }

        return $suma / max(1, imagesx($imagen) * imagesy($imagen));
    }
}
