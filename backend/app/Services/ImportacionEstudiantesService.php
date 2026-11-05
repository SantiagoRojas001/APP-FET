<?php

namespace App\Services;

use App\Enums\Modalidad;
use App\Exceptions\ReglaNegocioException;
use App\Models\Parametro;
use App\Models\Programa;
use App\Models\Usuario;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader as LectorXlsx;
use Smalot\PdfParser\Page;
use Smalot\PdfParser\Parser as LectorPdf;

/**
 * Registro de estudiantes a partir del listado de modalidades de grado (PDF,
 * Excel o CSV):
 *
 * 1. `leer()` extrae las filas y las interpreta: nombre, correo, programa,
 *    ciclo, modalidad y, si vienen, semestre, promedio, documento y código.
 *    Se queda solo con quienes eligieron pasantía o práctica en un semestre
 *    habilitado; si el listado trae el ciclo y no el semestre, se toma el
 *    semestre habilitado de ese ciclo (técnico 1.º a 4.º, tecnólogo 5.º y
 *    6.º, profesional 7.º a 10.º). No guarda nada: el docente revisa la
 *    vista previa y puede corregir cada fila.
 * 2. `registrar()` crea las cuentas de las filas confirmadas. Si una fila
 *    trae promedio, se registra de una vez y se envía el correo con el
 *    resultado (usuario y contraseña si alcanza el mínimo).
 */
class ImportacionEstudiantesService
{
    public const MAX_FILAS = 2000;

    private const CICLOS = ['Técnico' => [1, 4], 'Tecnólogo' => [5, 6], 'Profesional' => [7, 10]];

    /**
     * Encabezados reconocidos en Excel, CSV o tablas de PDF: el campo según
     * cómo empieza el título de la columna (sin tildes ni mayúsculas).
     */
    private const ENCABEZADOS = [
        'correo' => ['correo', 'email', 'e mail', 'mail'],
        'apellidos' => ['apellido'],
        'nombres' => ['nombres'],
        'nombre' => ['nombre', 'estudiante', 'alumno'],
        'programa' => ['programa', 'carrera'],
        'ciclo' => ['ciclo', 'nivel'],
        'semestre' => ['semestre'],
        'modalidad' => ['modalidad', 'opcion'],
        'promedio' => ['promedio'],
        'documento' => ['documento', 'cedula', 'identificacion', 'c c', 'cc', 'no documento'],
        'codigo' => ['codigo'],
    ];

    /** @var Collection<int, Programa>|null */
    private ?Collection $programas = null;

    public function __construct(private readonly ElegibilidadService $elegibilidad) {}

    /**
     * Lee el archivo y devuelve la vista previa: cada fila con sus datos, si
     * se puede registrar y, si no, por qué.
     *
     * @return array{filas: list<array<string, mixed>>, resumen: array<string, int>}
     */
    public function leer(UploadedFile $archivo): array
    {
        $registros = match (strtolower($archivo->getClientOriginalExtension())) {
            'pdf' => $this->registrosPdf($archivo->getRealPath()),
            'xlsx' => $this->registrosTabla($this->filasXlsx($archivo->getRealPath())),
            default => $this->registrosTabla($this->filasCsv($archivo->getRealPath())),
        };

        if ($registros === []) {
            throw new ReglaNegocioException('No encontramos estudiantes en el documento. Revisa que tenga los correos de los estudiantes (si es un PDF escaneado, conviértelo a texto o súbelo en Excel).');
        }

        if (count($registros) > self::MAX_FILAS) {
            throw new ReglaNegocioException('El documento tiene más de '.self::MAX_FILAS.' estudiantes. Divídelo en partes.');
        }

        $existentes = Usuario::query()->whereIn('correo', array_filter(array_column($registros, 'correo')))->pluck('correo')->flip();
        $filas = array_map(fn (array $r, int $i) => $this->interpretar($r, $i + 1, $existentes->has($r['correo'] ?? '')), $registros, array_keys($registros));

        return ['filas' => $filas, 'resumen' => array_count_values(array_column($filas, 'estado')) + ['nuevo' => 0, 'registrado' => 0, 'omitido' => 0, 'revisar' => 0]];
    }

    /**
     * Crea las cuentas de las filas que el docente confirmó.
     *
     * @param  list<array<string, mixed>>  $filas
     * @return array{creados: int, con_correo: int, pendientes_de_promedio: int, errores: list<array{fila: int, correo: string|null, error: string}>}
     */
    public function registrar(array $filas, Usuario $docente, RegistroEstudianteService $registro): array
    {
        $resultado = ['creados' => 0, 'con_correo' => 0, 'pendientes_de_promedio' => 0, 'errores' => []];

        foreach ($filas as $fila) {
            $validador = Validator::make($fila, [
                'correo' => ['required', 'email', 'max:150', 'unique:usuario,correo'],
                'nombres' => ['required', 'string', 'max:80'],
                'apellidos' => ['required', 'string', 'max:80'],
                'programa_id' => ['required', 'integer', 'exists:programa,id'],
                'semestre_actual' => ['required', 'integer', 'min:1', 'max:12'],
                'modalidad_grado' => ['required', 'in:'.implode(',', Modalidad::values())],
                'promedio' => ['nullable', 'numeric', 'min:0', 'max:5'],
                'codigo' => ['nullable', 'string', 'max:20', 'unique:estudiante,codigo'],
                'documento' => ['nullable', 'string', 'max:20', 'unique:estudiante,documento'],
            ], [
                'correo.unique' => 'Ya hay una cuenta con este correo.',
                'programa_id.required' => 'Elige el programa.',
                'semestre_actual.required' => 'Indica el semestre.',
            ]);

            if ($validador->fails()) {
                $resultado['errores'][] = ['fila' => (int) ($fila['fila'] ?? 0), 'correo' => $fila['correo'] ?? null, 'error' => $validador->errors()->first()];

                continue;
            }

            try {
                $datos = $validador->validated();
                $estudiante = $registro->registrar(collect($datos)->except('promedio')->all(), $docente);
                $resultado['creados']++;

                if (isset($datos['promedio'])) {
                    $registro->registrarPromedio($estudiante, (float) $datos['promedio'], $docente);
                    $resultado['con_correo']++;
                } else {
                    $resultado['pendientes_de_promedio']++;
                }
            } catch (ReglaNegocioException $e) {
                $resultado['errores'][] = ['fila' => (int) ($fila['fila'] ?? 0), 'correo' => $fila['correo'] ?? null, 'error' => $e->getMessage()];
            }
        }

        Auditoria::registrar('estudiantes.importados', $docente, collect($resultado)->except('errores')->all() + ['errores' => count($resultado['errores'])]);

        return $resultado;
    }

    /**
     * Interpreta un registro crudo y decide si se puede registrar.
     *
     * @param  array<string, string|null>  $r
     * @return array<string, mixed>
     */
    private function interpretar(array $r, int $numero, bool $yaRegistrado): array
    {
        $texto = $this->normalizar(implode(' ', array_filter($r)));
        $correo = mb_strtolower(trim((string) ($r['correo'] ?? '')));

        [$nombres, $apellidos] = filled($r['apellidos'] ?? null)
            ? [Str::title(trim((string) ($r['nombres'] ?? ''))), Str::title(trim((string) $r['apellidos']))]
            : $this->separarNombre((string) ($r['nombre'] ?? $r['nombres'] ?? ''));

        $programa = $this->programa((string) ($r['programa'] ?? $texto));
        // El ciclo explícito manda; si no viene, se deduce del texto sin el nombre del programa
        // («Técnica Profesional…» no debe leerse como ciclo profesional) y, por último, del programa.
        $sinPrograma = $programa ? str_replace($this->normalizar($programa->nombre), ' ', $texto) : $texto;
        $ciclo = $this->ciclo((string) ($r['ciclo'] ?? $sinPrograma)) ?? ($programa ? $this->ciclo($programa->nombre) : null);
        $modalidad = $this->modalidad((string) ($r['modalidad'] ?? $texto));
        $semestre = $this->semestre((string) ($r['semestre'] ?? '')) ?? (isset($r['semestre']) ? null : $this->semestre($texto, true));
        $semestre ??= $ciclo ? $this->semestreDelCiclo($ciclo) : null;
        $promedio = $this->promedio((string) ($r['promedio'] ?? ''));

        [$estado, $motivo] = match (true) {
            $yaRegistrado => ['registrado', 'Ya tiene cuenta en APP FET.'],
            ! $modalidad => ['omitido', 'No eligió pasantía ni práctica'.(filled($r['modalidad'] ?? null) ? " ({$r['modalidad']})." : '.')],
            $semestre !== null && ! $this->elegibilidad->semestreHabilitado($semestre) => ['omitido', "Cursa {$semestre}.º semestre, que no está habilitado."],
            ! filter_var($correo, FILTER_VALIDATE_EMAIL) => ['revisar', 'Revisa el correo.'],
            blank($nombres) || blank($apellidos) => ['revisar', 'Revisa el nombre y los apellidos.'],
            ! $programa => ['revisar', 'Elige el programa'.(filled($r['programa'] ?? null) ? " (el documento dice «{$r['programa']}»)." : '.')],
            $semestre === null => ['revisar', 'Indica el semestre.'],
            default => ['nuevo', null],
        };

        return [
            'fila' => $numero,
            'estado' => $estado,
            'motivo' => $motivo,
            'correo' => $correo ?: null,
            'nombres' => $nombres ?: null,
            'apellidos' => $apellidos ?: null,
            'programa_id' => $programa?->id,
            'programa_texto' => $r['programa'] ?? $programa?->nombre,
            'ciclo' => $ciclo,
            'semestre_actual' => $semestre,
            'modalidad_grado' => $modalidad?->value,
            'modalidad_texto' => $r['modalidad'] ?? $modalidad?->label(),
            'promedio' => $promedio,
            'documento' => filled($r['documento'] ?? null) ? preg_replace('/\D/', '', (string) $r['documento']) : null,
            'codigo' => filled($r['codigo'] ?? null) ? trim((string) $r['codigo']) : null,
        ];
    }

    // ---------------------------------------------------------------- Lectura

    /**
     * Registros de un PDF. Primero se reconstruye la tabla con la posición de
     * cada texto en la página (columnas bajo sus encabezados y una fila por
     * correo); si el PDF no tiene una tabla con encabezados, se arma un
     * registro por cada correo del texto.
     *
     * @return list<array<string, string|null>>
     */
    private function registrosPdf(string $ruta): array
    {
        try {
            $pdf = (new LectorPdf)->parseFile($ruta);
        } catch (\Throwable) {
            throw new ReglaNegocioException('No pudimos leer el PDF. Revisa que no esté dañado ni protegido con contraseña.');
        }

        $registros = $this->registrosPorPosicion($pdf->getPages());
        if ($registros !== []) {
            return $registros;
        }

        $lineas = array_values(array_filter(array_map('trim', preg_split('/\R/u', $pdf->getText())), fn ($l) => $l !== ''));

        return $this->registrosPorCorreo($lineas);
    }

    /**
     * Reconstruye la tabla del PDF con las coordenadas de cada texto. Las
     * columnas salen de la fila de encabezados (en cada página, o la de la
     * página anterior si no se repite); cada texto va a la columna más
     * cercana y a la fila del correo más cercano en altura, así los nombres o
     * programas partidos en varias líneas quedan completos.
     *
     * @param  array<int, Page>  $paginas
     * @return list<array<string, string|null>>
     */
    private function registrosPorPosicion(array $paginas): array
    {
        $columnas = null;
        $registros = [];

        foreach ($paginas as $pagina) {
            $items = [];
            foreach ($pagina->getDataTm() as [$matriz, $texto]) {
                $texto = trim((string) $texto);
                if ($texto !== '') {
                    $items[] = ['x' => (float) $matriz[4], 'y' => (float) $matriz[5], 'texto' => $texto];
                }
            }

            [$encabezado, $alturaEncabezado] = $this->filaDeEncabezados($items);
            $columnas = $encabezado ?? $columnas;
            if ($columnas === null) {
                continue;
            }

            $filas = [];
            $resto = [];
            foreach ($items as $item) {
                if ($alturaEncabezado !== null && $item['y'] >= $alturaEncabezado - 10) {
                    continue; // título del documento y encabezados (incluida su segunda línea)
                }
                $item['campo'] = $this->columnaMasCercana($columnas, $item['x']);
                if ($item['campo'] === 'correo' && preg_match('/[\w.+-]+@[\w-]+(\.[\w-]+)+/u', $item['texto'])) {
                    $filas[] = ['y' => $item['y'], 'items' => [$item]];
                } else {
                    $resto[] = $item;
                }
            }

            foreach ($resto as $item) {
                $cercana = null;
                foreach ($filas as $i => $fila) {
                    $distancia = abs($fila['y'] - $item['y']);
                    if ($distancia <= 30 && ($cercana === null || $distancia < $cercana[1])) {
                        $cercana = [$i, $distancia];
                    }
                }
                if ($cercana !== null) {
                    $filas[$cercana[0]]['items'][] = $item;
                }
            }

            foreach ($filas as $fila) {
                usort($fila['items'], fn ($a, $b) => [$b['y'], $a['x']] <=> [$a['y'], $b['x']]);
                $registro = [];
                foreach ($fila['items'] as $item) {
                    $registro[$item['campo']] = trim(($registro[$item['campo']] ?? '').' '.$item['texto']);
                }
                preg_match('/[\w.+-]+@[\w-]+(\.[\w-]+)+/u', $registro['correo'], $m);
                $registro['correo'] = mb_strtolower($m[0]);
                $registros[] = $registro;
            }
        }

        return $registros;
    }

    /**
     * La fila de encabezados de una página: la altura donde aparecen más
     * títulos de columna conocidos, entre ellos el del correo.
     *
     * @param  list<array{x: float, y: float, texto: string}>  $items
     * @return array{0: array<string, float>|null, 1: float|null}
     */
    private function filaDeEncabezados(array $items): array
    {
        $titulos = [];
        foreach ($items as $item) {
            if ($campo = $this->campoDeEncabezado($item['texto'])) {
                $titulos[] = $item + ['campo' => $campo];
            }
        }

        // Los títulos de varias líneas («Modalidad de / grado») quedan a distinta altura: se toman
        // los que estén cerca del título del correo.
        $mejor = null;
        foreach ($titulos as $correo) {
            if ($correo['campo'] !== 'correo') {
                continue;
            }
            $fila = [];
            foreach ($titulos as $titulo) {
                if (abs($titulo['y'] - $correo['y']) <= 15) {
                    $fila[$titulo['campo']] ??= $titulo;
                }
            }
            if (count($fila) >= 2 && count($fila) > count($mejor ?? [])) {
                $mejor = $fila;
            }
        }

        if ($mejor === null) {
            return [null, null];
        }

        return [array_map(fn ($item) => $item['x'], $mejor), min(array_column($mejor, 'y'))];
    }

    /** @param  array<string, float>  $columnas */
    private function columnaMasCercana(array $columnas, float $x): string
    {
        $campos = array_keys($columnas);
        usort($campos, fn ($a, $b) => abs($columnas[$a] - $x) <=> abs($columnas[$b] - $x));

        return $campos[0];
    }

    /**
     * Un registro por correo: la línea del correo, la anterior si allí está
     * el nombre y las siguientes sin correo (programa, ciclo, modalidad…).
     *
     * @param  list<string>  $lineas
     * @return list<array<string, string|null>>
     */
    private function registrosPorCorreo(array $lineas): array
    {
        $bloques = [];

        foreach ($lineas as $i => $linea) {
            if (! preg_match('/[\w.+-]+@[\w-]+(\.[\w-]+)+/u', $linea, $m)) {
                if ($bloques !== []) {
                    $bloques[count($bloques) - 1]['lineas'][] = $linea;
                }

                continue;
            }

            $bloque = ['correo' => mb_strtolower($m[0]), 'lineas' => [$linea], 'nombre' => null];
            $antes = trim(Str::before($linea, $m[0]));
            $resto = trim(str_replace($m[0], '', $linea));

            if ($this->pareceNombre($antes)) {
                $bloque['nombre'] = $antes;
            } elseif ($this->pareceNombre($resto)) {
                $bloque['nombre'] = $resto;
            } elseif ($i > 0 && $this->pareceNombre($lineas[$i - 1])) {
                // Cada celda en su línea: el nombre viene justo antes del correo.
                $bloque['nombre'] = $lineas[$i - 1];
                if ($bloques !== []) {
                    array_pop($bloques[count($bloques) - 1]['lineas']);
                }
            } else {
                $bloque['nombre'] = $this->primerNombre(preg_split('/\t+|\s{3,}|\s\|\s/u', $resto));
            }

            $bloques[] = $bloque;
        }

        return array_map(function (array $b) {
            $texto = implode(' | ', $b['lineas']);

            return ['correo' => $b['correo'], 'nombre' => $b['nombre'], 'texto' => $texto];
        }, $bloques);
    }

    /**
     * Registros de una tabla (Excel, CSV o PDF con encabezados): la primera
     * fila con un encabezado de correo define las columnas.
     *
     * @param  list<list<string|null>>  $filas
     * @return list<array<string, string|null>>
     */
    private function registrosTabla(array $filas): array
    {
        foreach ($filas as $i => $fila) {
            $columnas = $this->columnas($fila);

            if (! isset($columnas['correo'])) {
                continue;
            }

            $registros = [];
            foreach (array_slice($filas, $i + 1) as $datos) {
                $registro = [];
                foreach ($columnas as $campo => $indice) {
                    $valor = trim((string) ($datos[$indice] ?? ''));
                    $registro[$campo] = $valor === '' ? null : $valor;
                }
                if (filled($registro['correo'] ?? null) || filled($registro['nombre'] ?? null)) {
                    $registros[] = $registro;
                }
            }

            return $registros;
        }

        // Sin encabezados: se busca un correo en cada fila.
        return $this->registrosPorCorreo(array_map(fn ($f) => implode("\t", array_map('strval', $f)), $filas));
    }

    /**
     * Índice de cada columna conocida en una fila de encabezados.
     *
     * @param  list<string|null>  $encabezados
     * @return array<string, int>
     */
    private function columnas(array $encabezados): array
    {
        $columnas = [];

        foreach ($encabezados as $i => $titulo) {
            $campo = $this->campoDeEncabezado((string) $titulo);
            if ($campo && ! isset($columnas[$campo])) {
                $columnas[$campo] = $i;
            }
        }

        return $columnas;
    }

    /** Campo al que corresponde un título de columna, o null si no es un encabezado conocido. */
    private function campoDeEncabezado(string $titulo): ?string
    {
        $titulo = trim(preg_replace('/[^a-z ]+/', ' ', $this->normalizar($titulo)));
        if ($titulo === '' || strlen($titulo) > 40) {
            return null;
        }

        foreach (self::ENCABEZADOS as $campo => $inicios) {
            foreach ($inicios as $inicio) {
                // Palabra completa (o su plural): «Programa académico» sí, «Programación de Software» no.
                if (preg_match('/^'.preg_quote($inicio, '/').'(s|es)?( |$)/', $titulo)) {
                    // «Nombres y apellidos» o «Nombre completo» son el nombre completo.
                    return $campo === 'nombres' && str_contains($titulo, 'apellido') ? 'nombre' : $campo;
                }
            }
        }

        return null;
    }

    /** @return list<list<string|null>> */
    private function filasXlsx(string $ruta): array
    {
        $filas = [];
        $lector = new LectorXlsx;
        $lector->open($ruta);

        foreach ($lector->getSheetIterator() as $hoja) {
            foreach ($hoja->getRowIterator() as $fila) {
                $filas[] = array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : (is_scalar($v) ? (string) $v : null), $fila->toArray());
            }
            break; // Solo la primera hoja.
        }
        $lector->close();

        return $filas;
    }

    /** @return list<list<string|null>> */
    private function filasCsv(string $ruta): array
    {
        $contenido = (string) file_get_contents($ruta);
        if (! mb_check_encoding($contenido, 'UTF-8')) {
            $contenido = mb_convert_encoding($contenido, 'UTF-8', 'Windows-1252');
        }
        $contenido = preg_replace('/^\xEF\xBB\xBF/', '', $contenido);
        $primera = strtok($contenido, "\n") ?: '';
        $separador = substr_count($primera, ';') > substr_count($primera, ',') ? ';' : ',';

        return array_map(fn ($l) => str_getcsv($l, $separador), array_filter(preg_split('/\R/u', $contenido), fn ($l) => trim($l) !== ''));
    }

    // ---------------------------------------------------------- Interpretación

    private function normalizar(string $texto): string
    {
        return mb_strtolower(Str::ascii($texto));
    }

    /** Dos a seis palabras con solo letras: un nombre, no un programa, un ciclo ni un encabezado. */
    private function pareceNombre(string $texto): bool
    {
        $texto = trim($texto);
        $palabras = preg_split('/\s+/u', $texto);

        return preg_match('/^[\p{L}\s\'.-]+$/u', $texto) === 1
            && count($palabras) >= 2 && count($palabras) <= 6
            && ! $this->modalidad($texto) && ! $this->ciclo($texto) && ! $this->programa($texto)
            && ! preg_match('/\b(nombre|correo|programa|modalidad|semestre|listado|estudiantes)\b/', $this->normalizar($texto));
    }

    /** @param  list<string>  $celdas */
    private function primerNombre(array $celdas): ?string
    {
        foreach ($celdas as $celda) {
            if ($this->pareceNombre($celda)) {
                return trim($celda);
            }
        }

        return null;
    }

    /**
     * Nombres y apellidos: con dos palabras, una y una; con tres, un nombre y
     * dos apellidos; con cuatro o más, dos nombres y el resto apellidos.
     *
     * @return array{0: string, 1: string}
     */
    private function separarNombre(string $completo): array
    {
        $palabras = array_values(array_filter(preg_split('/\s+/u', Str::title(trim($completo)))));
        $corte = match (count($palabras)) {
            0 => 0,
            1, 2, 3 => 1,
            default => 2,
        };

        return [implode(' ', array_slice($palabras, 0, $corte)), implode(' ', array_slice($palabras, $corte))];
    }

    private function modalidad(string $texto): ?Modalidad
    {
        $texto = $this->normalizar($texto);
        $pasantia = strpos($texto, 'pasant');
        $practica = preg_match('/\bpractica/', $texto, $m, PREG_OFFSET_CAPTURE) ? $m[0][1] : false;

        return match (true) {
            $pasantia !== false && ($practica === false || $pasantia < $practica) => Modalidad::Pasantia,
            $practica !== false => Modalidad::Practica,
            default => null,
        };
    }

    private function ciclo(string $texto): ?string
    {
        $texto = $this->normalizar($texto);

        return match (true) {
            (bool) preg_match('/\btecnolog|\bciclo\s*(2|ii|dos)\b|\bsegundo ciclo/', $texto) => 'Tecnólogo',
            (bool) preg_match('/\btecnic|\bciclo\s*(1|i|uno)\b|\bprimer ciclo/', $texto) => 'Técnico',
            (bool) preg_match('/\bprofesional\b|\bingenier|\buniversitari|\bciclo\s*(3|iii|tres)\b|\btercer ciclo/', $texto) => 'Profesional',
            default => null,
        };
    }

    /** Semestre escrito como número suelto (columna) o como «9.º semestre» / «semestre 9» dentro del texto. */
    private function semestre(string $texto, bool $enTexto = false): ?int
    {
        $texto = $this->normalizar($texto);
        $patron = $enTexto
            ? '/(?:\bsemestre\s*(\d{1,2})\b|\b(\d{1,2})\s*(?:o|°|º|\.o|\.º)?\s*semestre)/u'
            : '/^\s*(\d{1,2})/';

        if (! preg_match($patron, $texto, $m)) {
            return null;
        }
        $valor = (int) ($m[1] ?: ($m[2] ?? 0));

        return $valor >= 1 && $valor <= 12 ? $valor : null;
    }

    /** Primer semestre habilitado dentro del ciclo (3.º, 5.º y 9.º por defecto). */
    private function semestreDelCiclo(string $ciclo): ?int
    {
        [$desde, $hasta] = self::CICLOS[$ciclo];

        foreach (Parametro::semestresHabilitados() as $semestre) {
            if ($semestre >= $desde && $semestre <= $hasta) {
                return $semestre;
            }
        }

        return null;
    }

    private function promedio(string $texto): ?float
    {
        if (! preg_match('/^\s*([0-5](?:[.,]\d{1,2})?)\s*$/', $texto, $m)) {
            return null;
        }

        return (float) str_replace(',', '.', $m[1]);
    }

    /** Programa de la FET al que más se parece el texto. */
    private function programa(string $texto): ?Programa
    {
        $this->programas ??= Programa::query()->orderByDesc('total_semestres')->get();
        $texto = $this->normalizar($texto);
        $mejor = null;
        $puntaje = 0;

        foreach ($this->programas as $programa) {
            $nombre = $this->normalizar($programa->nombre);
            if (str_contains($texto, $nombre)) {
                return $programa;
            }

            $palabras = array_filter(preg_split('/\s+/', $nombre), fn ($p) => strlen($p) > 3);
            $coinciden = count(array_filter($palabras, fn ($p) => str_contains($texto, $p)));
            $valor = $palabras ? $coinciden / count($palabras) : 0;

            if ($valor > $puntaje) {
                [$mejor, $puntaje] = [$programa, $valor];
            }
        }

        return $puntaje >= 0.75 ? $mejor : null;
    }
}
