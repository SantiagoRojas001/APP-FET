<?php

namespace Database\Seeders;

use App\Enums\CategoriaReporte;
use App\Enums\EstadoAcuerdo;
use App\Enums\EstadoCita;
use App\Enums\EstadoHojaVida;
use App\Enums\EstadoReporte;
use App\Enums\EstadoRevision;
use App\Enums\EstadoValidacion;
use App\Enums\Modalidad;
use App\Enums\NivelCompetencia;
use App\Enums\NivelFormacion;
use App\Enums\Rol;
use App\Enums\TipoDocumento;
use App\Enums\TipoInvestigacion;
use App\Enums\TipoReferencia;
use App\Models\Cita;
use App\Models\Competencia;
use App\Models\Empresa;
use App\Models\Estudiante;
use App\Models\EvidenciaSemanal;
use App\Models\Programa;
use App\Models\Reporte;
use App\Models\Usuario;
use App\Models\Vacante;
use App\Models\Vinculacion;
use App\Notifications\HorasCumplidas;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Cuentas y datos de ejemplo para desarrollo y demostraciones.
 * Todas las cuentas usan la contraseña "demo1234".
 */
class DemoSeeder extends Seeder
{
    private const PASSWORD = 'demo1234';

    /**
     * Repositorios públicos reales, para que la vista previa de GitHub muestre
     * datos en la demostración. Los estudiantes reales enlazan los suyos.
     */
    private const REPOSITORIOS = [
        'mateo' => ['API REST con Laravel', 'https://github.com/laravel/laravel'],
        'julian' => ['Interfaz web con Vite', 'https://github.com/vitejs/vite'],
        'sofia' => ['Componentes con Vue.js', 'https://github.com/vuejs/core'],
        'daniel' => ['Panel de datos con Python', 'https://github.com/streamlit/streamlit'],
        'paula' => ['Formularios accesibles en la web', 'https://github.com/w3c/aria-practices'],
        'andres' => ['Autenticación con Laravel Breeze', 'https://github.com/laravel/breeze'],
        'valentina' => ['Análisis de datos con pandas', 'https://github.com/pandas-dev/pandas'],
    ];

    private ?Usuario $admin = null;

    public function run(): void
    {
        $this->admin = $this->usuario('admin@fet.edu.co', Rol::Admin);
        $software = Programa::where('nombre', 'Ingeniería de Software')->value('id');
        $docente = $this->usuario('p.vargas@fet.edu.co', Rol::Docente);
        $docente->update(['programa_id' => $software]);
        $director = $this->usuario('direccion.software@fet.edu.co', Rol::Director);
        $director->update(['programa_id' => $software]);

        $empresas = $this->empresas();
        $this->acuerdos($empresas);
        $vacantes = $this->vacantes($empresas);
        $estudiantes = $this->estudiantes($docente);

        $vinculaciones = $this->vinculaciones($estudiantes, $empresas, $vacantes, $docente);
        $this->citas($estudiantes, $empresas, $vacantes, $vinculaciones, $director);
        $this->recomendaciones($estudiantes, $vacantes, $docente);
        $this->reportes($estudiantes, $empresas);
    }

    /**
     * Acuerdos aprobados y vigentes de las empresas validadas; Nodo Digital
     * además subió una renovación que espera aprobación.
     *
     * @param  array<string, Empresa>  $empresas
     */
    private function acuerdos(array $empresas): void
    {
        foreach (['nodo', 'agro', 'huila', 'csg'] as $clave) {
            $empresa = $empresas[$clave];
            if ($empresa->acuerdos()->exists()) {
                continue;
            }

            $ruta = "acuerdos/empresa-{$empresa->id}/demo-acuerdo-2026.pdf";
            Storage::disk('local')->put($ruta, $this->pdfDeEjemplo("Acuerdo de prácticas {$empresa->razon_social} 2026-2027"));
            $empresa->acuerdos()->create([
                'archivo_url' => $ruta,
                'vigencia_inicio' => '2026-01-01',
                'vigencia_fin' => '2027-12-31',
                'estado' => EstadoAcuerdo::Aprobado,
                'revisado_por' => $this->admin->id,
                'revisado_en' => '2026-01-10 09:00:00',
                'cargado_en' => '2026-01-05 15:00:00',
            ]);
        }

        $nodo = $empresas['nodo'];
        $ruta = "acuerdos/empresa-{$nodo->id}/demo-renovacion-2028.pdf";
        Storage::disk('local')->put($ruta, $this->pdfDeEjemplo('Renovación del acuerdo de prácticas Nodo Digital 2028'));
        $nodo->acuerdos()->create(['archivo_url' => $ruta, 'vigencia_inicio' => '2028-01-01', 'vigencia_fin' => '2029-12-31']);
    }

    /** Trazo de ejemplo como el que se dibuja en la app: PNG transparente con tinta oscura. */
    private function firmaDeEjemplo(Estudiante $estudiante): string
    {
        $imagen = imagecreatetruecolor(1200, 400);
        imagesavealpha($imagen, true);
        imagealphablending($imagen, false);
        imagefill($imagen, 0, 0, imagecolorallocatealpha($imagen, 0, 0, 0, 127));
        imagealphablending($imagen, true);
        imagesetthickness($imagen, 5);
        $tinta = imagecolorallocate($imagen, 0x1A, 0x1D, 0x24);
        $fase = $estudiante->id;
        for ($x = 120; $x < 1040; $x += 12) {
            $y = fn (int $x) => 200 + (int) (90 * sin(($x + $fase * 40) / 55) * cos($x / 230));
            imageline($imagen, $x, $y($x), $x + 12, $y($x + 12), $tinta);
        }
        ob_start();
        imagepng($imagen);
        $ruta = "firmas/demo-{$estudiante->id}.png";
        Storage::disk('local')->put($ruta, (string) ob_get_clean());

        return $ruta;
    }

    private function usuario(string $correo, Rol $rol): Usuario
    {
        $usuario = Usuario::firstOrCreate(['correo' => $correo], ['password_hash' => self::PASSWORD, 'rol' => $rol]);
        $usuario->consentimientos()->firstOrCreate(['version_politica' => config('fet.version_politica')]);

        return $usuario;
    }

    /** @return array<string, Empresa> */
    private function empresas(): array
    {
        $datos = [
            'nodo' => ['talento@nododigital.co', '901.234.567-1', 'Nodo Digital S.A.S.', 'Tecnología', 'Diana Herrera', Modalidad::Pasantia, EstadoValidacion::Validada],
            'agro' => ['practicas@agroandina.coop', '891.100.222-4', 'Cooperativa Agroandina', 'Agroindustria', 'Luis Calderón', Modalidad::Practica, EstadoValidacion::Validada],
            'huila' => ['equipo@huilatech.co', '901.555.010-7', 'Huila Tech Labs', 'Tecnología', 'Marcela Rojas', Modalidad::Practica, EstadoValidacion::Validada],
            'csg' => ['ti@clinicasangabriel.co', '800.765.432-9', 'Clínica San Gabriel', 'Salud', 'Hernán Ortiz', Modalidad::Pasantia, EstadoValidacion::Validada],
            'agrosur' => ['rrhh@agrosur.co', '901.777.456-2', 'AgroSur Exportaciones', 'Comercio exterior', 'Carlos Ome', Modalidad::Pasantia, EstadoValidacion::Pendiente],
        ];

        return collect($datos)->map(function (array $d) {
            [$correo, $nit, $razon, $sector, $contacto, $modalidad, $estado] = $d;
            $usuario = $this->usuario($correo, Rol::Empresa);

            return Empresa::firstOrCreate(['usuario_id' => $usuario->id], [
                'nit' => $nit,
                'razon_social' => $razon,
                'sector' => $sector,
                'contacto_nombre' => $contacto,
                'contacto_telefono' => '3'.substr(preg_replace('/\D/', '', $nit), 0, 9),
                'modalidad_interes' => $modalidad,
                'estado_validacion' => $estado,
                'validada_por' => $estado === EstadoValidacion::Validada ? $this->admin->id : null,
                'validada_en' => $estado === EstadoValidacion::Validada ? now()->subMonths(2) : null,
            ]);
        })->all();
    }

    /**
     * @param  array<string, Empresa>  $empresas
     * @return array<int, Vacante>
     */
    private function vacantes(array $empresas): array
    {
        $datos = [
            1 => ['nodo', 'Pasante de desarrollo web (Laravel y Vue)', Modalidad::Pasantia, 'Neiva, híbrido. 6 meses, tiempo completo. Apoyo de sostenimiento.'],
            2 => ['agro', 'Practicante de soporte y redes', Modalidad::Practica, 'Pitalito, presencial. 6 meses, medio tiempo.'],
            3 => ['huila', 'Practicante de desarrollo front-end', Modalidad::Practica, 'Remoto, medio tiempo. Apoyo de sostenimiento.'],
            4 => ['csg', 'Pasante de análisis de datos', Modalidad::Pasantia, 'Neiva, presencial. 6 meses, tiempo completo.'],
            5 => ['nodo', 'Pasante de QA y pruebas', Modalidad::Pasantia, 'Neiva, híbrido. 4 meses, medio tiempo.'],
            6 => ['huila', 'Practicante de desarrollo móvil (Flutter)', Modalidad::Practica, 'Remoto, tiempo completo. Apoyo de sostenimiento.'],
        ];

        return collect($datos)->map(fn (array $d, int $dias) => Vacante::firstOrCreate(
            ['empresa_id' => $empresas[$d[0]]->id, 'titulo' => $d[1]],
            ['modalidad' => $d[2], 'descripcion' => $d[3], 'publicada_en' => now()->subDays($dias)],
        ))->all();
    }

    /** @return array<string, Estudiante> */
    private function estudiantes(Usuario $docente): array
    {
        $is = Programa::where('nombre', 'Ingeniería de Software')->value('id');

        // alias, programa, código, nombres, apellidos, semestre, promedio (null: sin registrar), modalidad, estado de la hoja de vida
        $datos = [
            'mateo' => ['mateo.suarez', $is, '20211130', 'Mateo', 'Suárez', 9, 4.6, Modalidad::Pasantia, EstadoHojaVida::Aprobada],
            'julian' => ['julian.castro', $is, '20252018', 'Julián', 'Castro', 3, 4.3, Modalidad::Pasantia, EstadoHojaVida::Borrador],
            'sofia' => ['sofia.ramirez', $is, '20211077', 'Sofía', 'Ramírez', 9, 4.4, Modalidad::Practica, EstadoHojaVida::Aprobada],
            'daniel' => ['daniel.gomez', $is, '20211102', 'Daniel', 'Gómez', 9, 4.5, Modalidad::Practica, EstadoHojaVida::EnRevision],
            'paula' => ['paula.ortiz', $is, '20241033', 'Paula', 'Ortiz', 5, 4.3, Modalidad::Pasantia, EstadoHojaVida::ConObservaciones],
            'andres' => ['andres.rojas', $is, '20231190', 'Andrés', 'Rojas', 6, 4.5, Modalidad::Pasantia, EstadoHojaVida::Aprobada],
            'valentina' => ['valentina.torres', $is, '20201055', 'Valentina', 'Torres', 10, 4.8, Modalidad::Pasantia, EstadoHojaVida::Aprobada],
            'camila' => ['camila.perez', $is, '20261004', 'Camila', 'Pérez', 3, null, Modalidad::Practica, EstadoHojaVida::Borrador],
        ];

        $competencias = Competencia::pluck('id', 'nombre');

        return collect($datos)->map(function (array $d, string $clave) use ($competencias, $docente) {
            [$alias, $programa, $codigo, $nombres, $apellidos, $semestre, $promedio, $modalidad, $estadoHoja] = $d;
            $usuario = $this->usuario("{$alias}@fet.edu.co", Rol::Estudiante);
            $habilitado = $promedio === null ? null : true;
            // Julián aún debe cambiar su contraseña inicial.
            $usuario->update(['debe_cambiar_password' => $clave === 'julian']);

            $estudiante = Estudiante::firstOrCreate(['usuario_id' => $usuario->id], [
                'modalidad_grado' => $modalidad,
                'promedio' => $promedio,
                'habilitado' => $habilitado,
                'resultado_enviado_en' => $promedio === null ? null : now()->subMonths(3),
                'registrado_por' => $docente->id,
                'programa_id' => $programa,
                'codigo' => $codigo,
                'documento' => '10'.$codigo,
                'nombres' => $nombres,
                'apellidos' => $apellidos,
                'telefono' => '31'.substr($codigo, -8),
                'semestre_actual' => $semestre,
                'disponibilidad' => $semestre >= 5 ? 'Tiempo completo' : 'Medio tiempo',
            ]);

            if ($estudiante->wasRecentlyCreated) {
                $this->hojaVida($estudiante, $semestre, $estadoHoja, $docente);
                $estudiante->competencias()->attach([
                    $competencias['JavaScript'] => ['nivel' => NivelCompetencia::Intermedio->value],
                    $competencias['Git y GitHub'] => ['nivel' => NivelCompetencia::Basico->value],
                    $competencias['Trabajo en equipo'] => ['nivel' => NivelCompetencia::Avanzado->value],
                ]);
                if (isset(self::REPOSITORIOS[$clave])) {
                    [$titulo, $url] = self::REPOSITORIOS[$clave];
                    $estudiante->proyectos()->create([
                        'titulo' => $titulo,
                        'descripcion' => 'Repositorio de referencia trabajado en clase.',
                        'url_repositorio' => $url,
                        'semestre' => $semestre - 1,
                    ]);
                }
            }

            return $estudiante;
        })->all();
    }

    /**
     * Hoja de vida en el formato PA-GTH-F-10. Los estudiantes de semestres
     * avanzados la tienen completa (salvo la foto); los primeros, a medias.
     */
    private function hojaVida(Estudiante $estudiante, int $semestre, EstadoHojaVida $estado, Usuario $docente): void
    {
        $avanzado = $semestre > 2;
        $revisada = in_array($estado, [EstadoHojaVida::Aprobada, EstadoHojaVida::ConObservaciones], true);
        $hoja = $estudiante->hojaVida()->create([
            'estado' => $estado,
            'enviada_en' => $estado === EstadoHojaVida::Borrador ? null : now()->subDays(5),
            'revisada_por' => $revisada ? $docente->id : null,
            'revisada_en' => $revisada ? now()->subDays(3) : null,
            'observaciones' => $estado === EstadoHojaVida::ConObservaciones
                ? 'Amplía las funciones de tu experiencia laboral y agrega una segunda referencia personal.'
                : null,
            'perfil_profesional' => $avanzado ? "Estudiante de {$semestre}.º semestre interesado en el desarrollo de software y el trabajo en equipo." : null,
            'fecha_nacimiento' => now()->subYears(18 + intdiv($semestre, 2))->subDays($semestre * 23)->toDateString(),
            'lugar_nacimiento' => 'Neiva, Huila',
            'estado_civil' => 'Soltero(a)',
            'lugar_residencia' => 'Neiva',
            'direccion' => $avanzado ? "Calle {$semestre} # 12-34" : null,
            'rh' => $avanzado ? 'O+' : null,
            'contacto_emergencia_nombre' => $avanzado ? "Familiar de {$estudiante->nombres}" : null,
            'contacto_emergencia_telefono' => $avanzado ? '3201234567' : null,
            // Quien ya envió su hoja de vida a revisión la firmó (la firma es obligatoria).
            'firma_url' => $estado === EstadoHojaVida::Borrador ? null : $this->firmaDeEjemplo($estudiante),
        ]);

        if (! $avanzado) {
            return;
        }

        $hoja->cursos()->create([
            'tema' => 'Fundamentos de Scrum', 'duracion_horas' => 40, 'fecha' => now()->subMonths(8)->startOfMonth()->toDateString(),
            'institucion' => 'SENA', 'modalidad' => 'Virtual',
        ]);
        $hoja->formaciones()->create([
            'nivel' => NivelFormacion::Tecnico, 'titulo' => 'Técnico en Sistemas', 'institucion' => 'SENA',
            'pais_ciudad' => 'Colombia, Neiva', 'fecha_grado' => now()->subYears(3)->toDateString(), 'modalidad' => 'Presencial',
        ]);
        $hoja->investigaciones()->create([
            'tipo' => TipoInvestigacion::Grupo, 'descripcion' => 'Semillero de investigación en desarrollo de software de la FET', 'periodo' => '2025-1 a 2026-2',
        ]);
        $hoja->referencias()->createMany([
            ['tipo' => TipoReferencia::Familiar, 'nombre' => "Madre de {$estudiante->nombres}", 'ocupacion' => 'Docente', 'parentesco' => 'Madre', 'telefono' => '3109876543'],
            ['tipo' => TipoReferencia::Personal, 'nombre' => 'Jorge Medina', 'ocupacion' => 'Ingeniero de sistemas', 'telefono' => '3001112233'],
        ]);
    }

    /**
     * @param  array<string, Estudiante>  $estudiantes
     * @param  array<string, Empresa>  $empresas
     * @param  array<int, Vacante>  $vacantes
     */
    /** @return array<string, Vinculacion> */
    private function vinculaciones(array $estudiantes, array $empresas, array $vacantes, Usuario $docente): array
    {
        $creadas = [];
        // A Andrés (tecnólogo, 480 horas) solo le falta que la empresa apruebe su última semana
        // para completarlas; Valentina (profesional, 640) ya las completó y espera su evaluación.
        $datos = [
            ['andres', 'nodo', 1, '2026-07-21', 472],
            ['valentina', 'csg', 4, '2026-07-14', 648],
        ];

        foreach ($datos as [$alias, $empresa, $vacante, $inicio, $horas]) {
            $estudiante = $estudiantes[$alias];

            $vinculacion = $estudiante->vinculaciones()->firstOrCreate(['vacante_id' => $vacantes[$vacante]->id], [
                'empresa_id' => $empresas[$empresa]->id,
                'tutor_id' => $docente->id,
                'tipo' => Modalidad::Pasantia,
                'semestre_al_vincular' => $estudiante->semestre_actual,
                'fecha_inicio' => $inicio,
            ]);

            $creadas[$alias] = $vinculacion;

            if (! $vinculacion->wasRecentlyCreated) {
                continue;
            }

            $this->evidencias($vinculacion, $horas, $alias === 'andres' ? 16 : 0);

            $documentos = [
                [TipoDocumento::Arl, $inicio, '2026-12-18', EstadoRevision::Aprobado, null],
                [TipoDocumento::InformeAvance, '2026-08-04', '2026-08-31', EstadoRevision::Aprobado, null],
                [TipoDocumento::ControlAsistencia, '2026-09-01', '2026-09-15', EstadoRevision::Aprobado, null],
                [TipoDocumento::Bitacora, '2026-09-14', '2026-09-20', EstadoRevision::Observado, 'Describe con más detalle las tareas del módulo.'],
                [TipoDocumento::ControlAsistencia, '2026-09-16', '2026-09-30', EstadoRevision::Pendiente, null],
            ];

            foreach ($documentos as [$tipo, $desde, $hasta, $estado, $obs]) {
                $ruta = "documentos/vinculacion-{$vinculacion->id}/demo-".strtolower($tipo->value)."-{$desde}.pdf";
                Storage::disk('local')->put($ruta, $this->pdfDeEjemplo("{$tipo->label()} del {$desde} al {$hasta}"));

                $vinculacion->documentos()->create([
                    'tipo' => $tipo,
                    'periodo_inicio' => $desde,
                    'periodo_fin' => $hasta,
                    'archivo_url' => $ruta,
                    'cargado_por' => $estudiante->usuario_id,
                    'cargado_en' => "{$hasta} 18:00:00",
                    'estado_revision' => $estado,
                    'observaciones' => $obs,
                ]);
            }
        }

        return $creadas;
    }

    /**
     * Una cita en cada etapa: programada (Mateo con Huila Tech), realizada y
     * pendiente de la decisión de la empresa (Mateo con Nodo Digital),
     * aceptada y pendiente de la carta del director (Sofía con Agroandina) y
     * las de Andrés y Valentina, que ya iniciaron su práctica.
     *
     * @param  array<string, Estudiante>  $estudiantes
     * @param  array<string, Empresa>  $empresas
     * @param  array<int, Vacante>  $vacantes
     * @param  array<string, Vinculacion>  $vinculaciones
     */
    private function citas(array $estudiantes, array $empresas, array $vacantes, array $vinculaciones, Usuario $director): void
    {
        if (Cita::exists()) {
            return;
        }

        $proximaCita = today()->addDays(2)->setTime(15, 0)->shiftTimezone(config('fet.zona_horaria'))->utc();
        $comun = ['direccion' => 'Carrera 5 # 10-38, oficina 302, Neiva', 'indicaciones' => 'Trae tu documento de identidad y la hoja de vida impresa.'];

        Cita::create([...$comun, 'empresa_id' => $empresas['huila']->id, 'estudiante_id' => $estudiantes['mateo']->id, 'vacante_id' => $vacantes[3]->id,
            'fecha_hora' => $proximaCita]);
        Cita::create([...$comun, 'empresa_id' => $empresas['nodo']->id, 'estudiante_id' => $estudiantes['mateo']->id, 'vacante_id' => $vacantes[5]->id,
            'fecha_hora' => now()->subDays(2)]);
        Cita::create([...$comun, 'empresa_id' => $empresas['agro']->id, 'estudiante_id' => $estudiantes['sofia']->id, 'vacante_id' => $vacantes[2]->id,
            'fecha_hora' => now()->subDays(6), 'estado' => EstadoCita::Aceptado, 'decidida_en' => now()->subDays(4),
            'fecha_inicio_practica' => today()->addWeek()]);

        foreach ($vinculaciones as $alias => $vinculacion) {
            $inicio = Carbon::parse($vinculacion->fecha_inicio);
            Cita::create([...$comun, 'empresa_id' => $vinculacion->empresa_id, 'estudiante_id' => $estudiantes[$alias]->id, 'vacante_id' => $vinculacion->vacante_id,
                'fecha_hora' => $inicio->copy()->subDays(14), 'estado' => EstadoCita::Presentado, 'decidida_en' => $inicio->copy()->subDays(12),
                'fecha_inicio_practica' => $inicio,
                'presentada_por' => $director->id, 'presentada_en' => $inicio->copy()->subDays(7), 'vinculacion_id' => $vinculacion->id]);
        }
    }

    /**
     * La docente recomienda a Sofía para la vacante móvil de Huila Tech.
     *
     * @param  array<string, Estudiante>  $estudiantes
     * @param  array<int, Vacante>  $vacantes
     */
    private function recomendaciones(array $estudiantes, array $vacantes, Usuario $docente): void
    {
        $vacantes[6]->recomendaciones()->firstOrCreate(
            ['estudiante_id' => $estudiantes['sofia']->id],
            ['docente_id' => $docente->id, 'comentario' => 'Muy buen desempeño en el curso de desarrollo móvil.'],
        );
    }

    /**
     * Semanas de 40 horas ya aprobadas por la empresa, desde la semana pasada
     * hacia atrás, hasta sumar las horas indicadas. Si se piden horas
     * pendientes, la semana actual queda por aprobar.
     */
    private function evidencias(Vinculacion $vinculacion, int $horas, int $pendientes): void
    {
        $actividades = ['Desarrollo de funcionalidades asignadas', 'Reuniones de seguimiento con el equipo', 'Pruebas y corrección de errores', 'Documentación técnica'];
        $semana = today()->startOfWeek()->subWeek();
        $inicio = $vinculacion->fecha_inicio->copy()->startOfWeek();
        $filas = [];

        for ($total = 0; $total < $horas && $semana->gte($inicio); $semana->subWeek()) {
            $semanales = min(40, $horas - $total);
            $revisada = $semana->copy()->addDays(7)->setTime(9, 0);
            $filas[] = [
                'vinculacion_id' => $vinculacion->id,
                'semana_inicio' => $semana->toDateString(),
                'horas' => $semanales,
                'actividades' => $actividades[count($filas) % 4],
                'estado' => EstadoRevision::Aprobado->value,
                'revisada_por' => $vinculacion->empresa->usuario_id,
                'revisada_en' => $revisada,
                'registrado_en' => $revisada->copy()->subDays(2),
            ];
            $total += $semanales;
        }

        if ($pendientes) {
            $filas[] = [
                'vinculacion_id' => $vinculacion->id,
                'semana_inicio' => today()->startOfWeek()->toDateString(),
                'horas' => $pendientes,
                'actividades' => 'Cierre del módulo de reportes y entrega de la documentación.',
                'estado' => EstadoRevision::Pendiente->value,
                'revisada_por' => null,
                'revisada_en' => null,
                'registrado_en' => now(),
            ];
        }

        EvidenciaSemanal::insert($filas);

        $requeridas = $vinculacion->horasRequeridas();
        if ($total >= $requeridas) {
            $vinculacion->update(['horas_cumplidas_en' => Carbon::parse($filas[0]['revisada_en'])]);
            $vinculacion->load('estudiante.usuario');
            $vinculacion->estudiante->usuario->notify(new HorasCumplidas($vinculacion, $requeridas));
        }
    }

    /**
     * Un reporte de la plataforma y tres de la empresa sobre la pasantía de
     * Andrés, con los que el docente ya puede finalizarla anticipadamente.
     *
     * @param  array<string, Estudiante>  $estudiantes
     * @param  array<string, Empresa>  $empresas
     */
    private function reportes(array $estudiantes, array $empresas): void
    {
        if (Reporte::exists()) {
            return;
        }

        $estudiantes['mateo']->usuario->reportes()->create([
            'categoria' => CategoriaReporte::FallaPlataforma,
            'asunto' => 'No se guarda la foto del repositorio',
            'descripcion' => 'Al agregar el enlace de GitHub en mi hoja de vida, la vista previa no aparece.',
        ]);

        $vinculacion = $estudiantes['andres']->vinculacionActiva()->firstOrFail();
        $quejas = [
            ['Llegadas tarde', 'El practicante llegó tarde tres días de esta semana sin avisar.'],
            ['Inasistencia sin aviso', 'No se presentó el lunes y no respondió los mensajes.'],
            ['Entregas incompletas', 'El módulo asignado se entregó sin las pruebas acordadas.'],
        ];

        foreach ($quejas as $i => [$asunto, $descripcion]) {
            $empresas['nodo']->usuario->reportes()->create([
                'vinculacion_id' => $vinculacion->id,
                'categoria' => CategoriaReporte::Practica,
                'asunto' => $asunto,
                'descripcion' => $descripcion,
                'estado' => $i === 0 ? EstadoReporte::Resuelto : EstadoReporte::Abierto,
                'respuesta' => $i === 0 ? 'El docente tutor habló con el practicante.' : null,
                'atendido_por' => $i === 0 ? $this->admin->id : null,
                'atendido_en' => $i === 0 ? now()->subDays(5) : null,
            ]);
        }
    }

    /** PDF mínimo de una página con un texto, para que las descargas de ejemplo funcionen. */
    private function pdfDeEjemplo(string $texto): string
    {
        $texto = iconv('UTF-8', 'Windows-1252//TRANSLIT', "APP FET - {$texto} (documento de ejemplo)");
        $contenido = 'BT /F1 14 Tf 60 760 Td ('.addcslashes($texto, '()\\').') Tj ET';
        $objetos = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($contenido)." >>\nstream\n{$contenido}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objetos as $i => $objeto) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$objeto}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objetos) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer << /Size '.(count($objetos) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}
