<?php

namespace App\Services;

use App\Enums\Ciclo;
use App\Enums\Modalidad;
use App\Exceptions\ReglaNegocioException;
use App\Models\Parametro;
use App\Models\Programa;
use App\Models\Usuario;
use App\Notifications\NoHabilitado;
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
 *    Se ignora a quien no eligió pasantía ni práctica o es de otro programa
 *    que el del docente. Si el listado trae el ciclo y no el semestre, se
 *    toma el semestre habilitado de ese ciclo. No guarda nada: el docente
 *    revisa la vista previa y puede corregir cada fila.
 * 2. `registrar()` procesa las filas confirmadas, cada una con su promedio
 *    (si el listado no lo trae, lo escribe el docente en la vista previa).
 *    Con el promedio mínimo se crea la cuenta y se envían el usuario y la
 *    contraseña; a quien cursa un semestre no habilitado o no alcanza el
 *    promedio no se le registra: recibe el correo con el motivo.
 */
class ImportacionEstudiantesService
{
    public const MAX_FILAS = 2000;

    /** Palabras genéricas de los nombres de programa: no sirven para reconocerlos. */
    private const GENERICAS = ['ingenieria', 'administracion', 'tecnologia', 'tecnica', 'profesional', 'gestion', 'de', 'del', 'la', 'el', 'en', 'y'];

    /**
     * Encabezados reconocidos en Excel, CSV o tablas de PDF: el campo según
     * las palabras del título de la columna (sin tildes ni mayúsculas), en
     * este orden. Sirven también los títulos en forma de pregunta de los
     * formularios («Ingrese el semestre al que pertenece»); en Google Forms,
     * «Nombre de usuario» es el correo de quien respondió.
     */
    private const ENCABEZADOS = [
        'correo' => ['correo', 'email', 'e mail', 'mail', 'nombre de usuario'],
        'promedio' => ['promedio'],
        'codigo' => ['codigo'],
        'documento' => ['documento', 'cedula', 'identificacion', 'c c', 'cc'],
        'programa' => ['programa', 'carrera'],
        'modalidad' => ['modalidad', 'opcion de grado'],
        'semestre' => ['semestre'],
        'ciclo' => ['ciclo', 'nivel'],
        'apellidos' => ['apellido'],
        'nombres' => ['nombres'],
        'nombre' => ['nombre', 'estudiante', 'alumno'],
    ];

    /** Semestres escritos con palabras o números romanos. */
    private const SEMESTRES = [
        'primer' => 1, 'primero' => 1, 'segundo' => 2, 'tercer' => 3, 'tercero' => 3, 'cuarto' => 4, 'quinto' => 5,
        'sexto' => 6, 'septimo' => 7, 'setimo' => 7, 'octavo' => 8, 'noveno' => 9, 'decimo' => 10,
        'i' => 1, 'ii' => 2, 'iii' => 3, 'iv' => 4, 'v' => 5, 'vi' => 6, 'vii' => 7, 'viii' => 8, 'ix' => 9, 'x' => 10,
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
    public function leer(UploadedFile $archivo, Usuario $docente): array
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
        $filas = array_map(
            fn (array $r, int $i) => $this->interpretar($r, $i + 1, $existentes->has($r['correo'] ?? ''), $docente->programaAsignado()),
            $registros,
            array_keys($registros),
        );

        return ['filas' => $filas, 'resumen' => array_count_values(array_column($filas, 'estado')) + ['nuevo' => 0, 'no_habilitado' => 0, 'registrado' => 0, 'omitido' => 0, 'revisar' => 0]];
    }

    /**
     * Crea las cuentas de las filas que el docente confirmó y envía el correo
     * con el motivo a quienes no quedan habilitados (sin registrarlos).
     *
     * @param  list<array<string, mixed>>  $filas
     * @param  list<array<string, mixed>>  $noHabilitados
     * @return array{creados: int, no_habilitados: int, errores: list<array{fila: int, correo: string|null, error: string}>}
     */
    public function registrar(array $filas, array $noHabilitados, Usuario $docente, RegistroEstudianteService $registro): array
    {
        $resultado = ['creados' => 0, 'no_habilitados' => 0, 'errores' => []];

        foreach ($noHabilitados as $fila) {
            if ($error = $this->notificarNoHabilitado($fila, $registro)) {
                $resultado['errores'][] = ['fila' => (int) ($fila['fila'] ?? 0), 'correo' => $fila['correo'] ?? null, 'error' => $error];
            } else {
                $resultado['no_habilitados']++;
            }
        }

        foreach ($filas as $fila) {
            $validador = Validator::make($fila, [
                'correo' => ['required', 'email', 'max:150', 'unique:usuario,correo'],
                'nombres' => ['required', 'string', 'max:80'],
                'apellidos' => ['required', 'string', 'max:80'],
                'programa_id' => ['required', 'integer', 'exists:programa,id'],
                'semestre_actual' => ['required', 'integer', 'min:1', 'max:12'],
                'modalidad_grado' => ['required', 'in:'.implode(',', Modalidad::values())],
                'promedio' => ['required', 'numeric', 'min:0', 'max:5'],
                'codigo' => ['nullable', 'string', 'max:20', 'unique:estudiante,codigo'],
                'documento' => ['nullable', 'string', 'max:20', 'unique:estudiante,documento'],
            ], [
                'correo.unique' => 'Ya hay una cuenta con este correo.',
                'programa_id.required' => 'Elige el programa.',
                'semestre_actual.required' => 'Indica el semestre.',
                'promedio.required' => 'Escribe el promedio.',
            ]);

            if ($validador->fails()) {
                $resultado['errores'][] = ['fila' => (int) ($fila['fila'] ?? 0), 'correo' => $fila['correo'] ?? null, 'error' => $validador->errors()->first()];

                continue;
            }

            $datos = $validador->validated();
            if (! $this->elegibilidad->promedioSuficiente((float) $datos['promedio'])) {
                $registro->notificarNoHabilitado($datos['correo'], $datos, NoHabilitado::POR_PROMEDIO);
                $resultado['no_habilitados']++;

                continue;
            }

            try {
                $estudiante = $registro->registrar(collect($datos)->except('promedio')->all(), $docente);
                $registro->registrarPromedio($estudiante, (float) $datos['promedio'], $docente);
                $resultado['creados']++;
            } catch (ReglaNegocioException $e) {
                $resultado['errores'][] = ['fila' => (int) ($fila['fila'] ?? 0), 'correo' => $fila['correo'] ?? null, 'error' => $e->getMessage()];
            }
        }

        Auditoria::registrar('estudiantes.importados', $docente, collect($resultado)->except('errores')->all() + ['errores' => count($resultado['errores'])]);

        return $resultado;
    }

    /**
     * Envía el correo a quien no queda habilitado, después de comprobar que
     * de verdad no lo está. Devuelve el error, si lo hay.
     *
     * @param  array<string, mixed>  $fila
     */
    private function notificarNoHabilitado(array $fila, RegistroEstudianteService $registro): ?string
    {
        $validador = Validator::make($fila, [
            'correo' => ['required', 'email', 'max:150', 'unique:usuario,correo'],
            'nombres' => ['required', 'string', 'max:80'],
            'apellidos' => ['required', 'string', 'max:80'],
            'codigo' => ['nullable', 'string', 'max:20'],
            'motivo_no_habilitado' => ['required', 'in:'.NoHabilitado::POR_SEMESTRE.','.NoHabilitado::POR_PROMEDIO],
            'semestre_actual' => ['required_if:motivo_no_habilitado,'.NoHabilitado::POR_SEMESTRE, 'nullable', 'integer', 'min:1', 'max:12'],
            'promedio' => ['required_if:motivo_no_habilitado,'.NoHabilitado::POR_PROMEDIO, 'nullable', 'numeric', 'min:0', 'max:5'],
        ], ['correo.unique' => 'Ya hay una cuenta con este correo.']);

        if ($validador->fails()) {
            return $validador->errors()->first();
        }

        $datos = $validador->validated();
        $porSemestre = $datos['motivo_no_habilitado'] === NoHabilitado::POR_SEMESTRE;
        if ($porSemestre ? $this->elegibilidad->semestreHabilitado((int) $datos['semestre_actual']) : $this->elegibilidad->promedioSuficiente((float) $datos['promedio'])) {
            return 'El estudiante sí está habilitado: regístralo.';
        }

        $registro->notificarNoHabilitado($datos['correo'], [...$datos, 'semestre' => $datos['semestre_actual'] ?? null], $datos['motivo_no_habilitado']);

        return null;
    }

    /**
     * Interpreta un registro crudo y decide si se puede registrar.
     *
     * @param  array<string, string|null>  $r
     * @return array<string, mixed>
     */
    private function interpretar(array $r, int $numero, bool $yaRegistrado, ?int $programaDocente): array
    {
        $texto = $this->normalizar(implode(' ', array_filter($r)));
        $correo = mb_strtolower(trim((string) ($r['correo'] ?? '')));

        [$nombres, $apellidos] = filled($r['apellidos'] ?? null)
            ? [Str::title(trim((string) ($r['nombres'] ?? ''))), Str::title(trim((string) $r['apellidos']))]
            : $this->separarNombre((string) ($r['nombre'] ?? $r['nombres'] ?? ''));

        // Si el listado no dice el programa, es el del docente que lo carga.
        $programa = $this->programa((string) ($r['programa'] ?? $texto))
            ?? (blank($r['programa'] ?? null) && $programaDocente ? $this->programas->firstWhere('id', $programaDocente) : null);
        // El ciclo explícito manda; si no viene, se deduce del texto sin el nombre del programa
        // («Técnica Profesional…» no debe leerse como ciclo profesional) y, por último, del programa.
        $sinPrograma = filled($r['programa'] ?? null) ? str_replace($this->normalizar((string) $r['programa']), ' ', $texto) : $texto;
        $ciclo = $this->ciclo((string) ($r['ciclo'] ?? $sinPrograma)) ?? $this->ciclo((string) ($r['programa'] ?? ''));
        $modalidad = $this->modalidad((string) ($r['modalidad'] ?? $texto));
        $semestre = $this->semestre((string) ($r['semestre'] ?? '')) ?? (isset($r['semestre']) ? null : $this->semestre($texto, true));
        $semestre ??= $ciclo ? $this->semestreDelCiclo($ciclo) : null;
        $promedio = $this->promedio((string) ($r['promedio'] ?? ''));
        $datosCompletos = filter_var($correo, FILTER_VALIDATE_EMAIL) && filled($nombres) && filled($apellidos);
        $noHabilitado = match (true) {
            $semestre !== null && ! $this->elegibilidad->semestreHabilitado($semestre) => NoHabilitado::POR_SEMESTRE,
            $promedio !== null && ! $this->elegibilidad->promedioSuficiente($promedio) => NoHabilitado::POR_PROMEDIO,
            default => null,
        };

        [$estado, $motivo] = match (true) {
            $yaRegistrado => ['registrado', 'Ya tiene cuenta en APP FET.'],
            ! $modalidad => ['omitido', 'No eligió pasantía ni práctica'.(filled($r['modalidad'] ?? null) ? " ({$r['modalidad']})." : '.')],
            $programa && $programaDocente && $programa->id !== $programaDocente => ['omitido', "Es de otro programa ({$programa->nombre})."],
            $noHabilitado && $datosCompletos => ['no_habilitado', $noHabilitado === NoHabilitado::POR_SEMESTRE
                ? "Cursa {$semestre}.º semestre, que no está habilitado."
                : 'Su promedio ('.number_format($promedio, 2, ',', '').') es inferior al mínimo.'],
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
            'motivo_no_habilitado' => $estado === 'no_habilitado' ? $noHabilitado : null,
            'correo' => $correo ?: null,
            'nombres' => $nombres ?: null,
            'apellidos' => $apellidos ?: null,
            'programa_id' => $programa?->id,
            'programa_texto' => $r['programa'] ?? $programa?->nombre,
            'ciclo' => $ciclo?->label(),
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
        $titulo = trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z ]+/', ' ', $this->normalizar($titulo))));
        if ($titulo === '' || strlen($titulo) > 80) {
            return null;
        }

        foreach (self::ENCABEZADOS as $campo => $palabras) {
            foreach ($palabras as $palabra) {
                // Palabra completa (o su plural): «Programa académico» sí, «Programación de Software» no.
                if (preg_match('/(^| )'.preg_quote($palabra, '/').'(s|es)?( |$)/', $titulo)) {
                    // «Nombres y apellidos» o «Nombre completo» son el nombre completo.
                    return in_array($campo, ['apellidos', 'nombres'], true) && str_contains($titulo, 'nombre') && str_contains($titulo, 'apellido') ? 'nombre' : $campo;
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

    private function ciclo(string $texto): ?Ciclo
    {
        $texto = $this->normalizar($texto);

        return match (true) {
            (bool) preg_match('/\btecnolog|\bciclo\s*(2|ii|dos)\b|\bsegundo ciclo/', $texto) => Ciclo::Tecnologo,
            (bool) preg_match('/\btecnic|\bciclo\s*(1|i|uno)\b|\bprimer ciclo/', $texto) => Ciclo::Tecnico,
            (bool) preg_match('/\bprofesional\b|\bingenier|\buniversitari|\bciclo\s*(3|iii|tres)\b|\btercer ciclo/', $texto) => Ciclo::Profesional,
            default => null,
        };
    }

    /**
     * Semestre escrito en su columna («9», «9.º», «noveno», «IX», «semestre 9»)
     * o dentro del texto («9.º semestre», «semestre 9», «noveno semestre»).
     */
    private function semestre(string $texto, bool $enTexto = false): ?int
    {
        $texto = $this->normalizar($texto);
        $palabras = implode('|', array_keys(self::SEMESTRES));
        $patron = $enTexto
            ? '/(?:\bsemestre\s*(\d{1,2}|'.$palabras.')\b|\b(\d{1,2}|'.$palabras.')\s*(?:o|°|º|\.o|\.º|no|vo|mo)?\s*semestre)/u'
            : '/^\s*(?:semestre\s*)?(\d{1,2}|'.$palabras.')\b/u';

        if (! preg_match($patron, $texto, $m)) {
            return null;
        }
        $hallado = $m[1] !== '' ? $m[1] : ($m[2] ?? '');
        $valor = is_numeric($hallado) ? (int) $hallado : (self::SEMESTRES[$hallado] ?? 0);

        return $valor >= 1 && $valor <= 12 ? $valor : null;
    }

    /** Primer semestre habilitado dentro del ciclo (3.º, 5.º y 9.º por defecto). */
    private function semestreDelCiclo(Ciclo $ciclo): ?int
    {
        [$desde, $hasta] = $ciclo->semestres();

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

    /**
     * Programa de la FET que nombra el texto, en cualquiera de sus ciclos: se
     * reconoce por sus palabras propias («Tecnología en Desarrollo de
     * Software» es Ingeniería de Software; «Mantenimiento Eléctrico», Eléctrica).
     */
    private function programa(string $texto): ?Programa
    {
        $this->programas ??= Programa::query()->get();
        $texto = $this->normalizar($texto);

        foreach ($this->programas as $programa) {
            $claves = array_diff(preg_split('/\s+/', $this->normalizar($programa->nombre)), self::GENERICAS);
            // Raíz de cada palabra (6 letras): «electrica» también reconoce «electrico» y «electricidad».
            if ($claves && collect($claves)->every(fn (string $p) => preg_match('/\b'.preg_quote(substr($p, 0, 6), '/').'/', $texto))) {
                return $programa;
            }
        }

        return null;
    }
}
