<?php

namespace Database\Seeders;

use App\Enums\CategoriaReporte;
use App\Enums\EstadoPostulacion;
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
use App\Models\Competencia;
use App\Models\Empresa;
use App\Models\Estudiante;
use App\Models\EvidenciaSemanal;
use App\Models\PeriodoAcademico;
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
        'laura' => ['Aplicación móvil con Flutter', 'https://github.com/flutter/samples'],
        'mateo' => ['API REST con Laravel', 'https://github.com/laravel/laravel'],
        'julian' => ['Interfaz web con Vite', 'https://github.com/vitejs/vite'],
        'sofia' => ['Componentes con Vue.js', 'https://github.com/vuejs/core'],
        'andres' => ['Autenticación con Laravel Breeze', 'https://github.com/laravel/breeze'],
        'valentina' => ['Análisis de datos con pandas', 'https://github.com/pandas-dev/pandas'],
    ];

    private ?Usuario $admin = null;

    public function run(): void
    {
        $this->admin = $this->usuario('admin@fet.edu.co', Rol::Admin);
        $docente = $this->usuario('p.vargas@fet.edu.co', Rol::Docente);

        $empresas = $this->empresas();
        $vacantes = $this->vacantes($empresas);
        $estudiantes = $this->estudiantes();

        $periodo = $this->periodos();
        $this->postulaciones($estudiantes, $vacantes);
        $this->vinculaciones($estudiantes, $empresas, $vacantes, $docente, $periodo);
        $this->reportes($estudiantes, $empresas);
    }

    /** 2026-1 ya cerrado y 2026-2 en curso. */
    private function periodos(): PeriodoAcademico
    {
        PeriodoAcademico::firstOrCreate(['nombre' => '2026-1'], [
            'fecha_inicio' => '2026-01-19', 'fecha_fin' => '2026-06-30', 'cerrado_en' => '2026-07-01 08:00:00',
        ]);

        return PeriodoAcademico::firstOrCreate(['nombre' => '2026-2'], ['fecha_inicio' => '2026-07-13', 'fecha_fin' => '2026-12-18']);
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
    private function estudiantes(): array
    {
        $is = Programa::where('nombre', 'Ingeniería de Software')->value('id');
        $tds = Programa::where('nombre', 'Tecnología en Desarrollo de Software')->value('id');
        $tps = Programa::where('nombre', 'Técnica Profesional en Programación de Software')->value('id');

        $datos = [
            'laura' => ['laura.mendez', $is, '20231045', 'Laura', 'Méndez', 5],
            'mateo' => ['mateo.suarez', $is, '20211130', 'Mateo', 'Suárez', 9],
            'julian' => ['julian.castro', $tps, '20252018', 'Julián', 'Castro', 3],
            'sofia' => ['sofia.ramirez', $is, '20211077', 'Sofía', 'Ramírez', 9],
            'andres' => ['andres.rojas', $tds, '20231190', 'Andrés', 'Rojas', 6],
            'valentina' => ['valentina.torres', $is, '20201055', 'Valentina', 'Torres', 10],
            'camila' => ['camila.perez', $is, '20261004', 'Camila', 'Pérez', 2],
        ];

        $competencias = Competencia::pluck('id', 'nombre');

        return collect($datos)->map(function (array $d, string $clave) use ($competencias) {
            [$alias, $programa, $codigo, $nombres, $apellidos, $semestre] = $d;
            $usuario = $this->usuario("{$alias}@fet.edu.co", Rol::Estudiante);

            $estudiante = Estudiante::firstOrCreate(['usuario_id' => $usuario->id], [
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
                $this->hojaVida($estudiante, $semestre);
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
    private function hojaVida(Estudiante $estudiante, int $semestre): void
    {
        $avanzado = $semestre > 2;
        $hoja = $estudiante->hojaVida()->create([
            'perfil_profesional' => $avanzado ? "Estudiante de {$semestre}.º semestre interesado en el desarrollo de software y el trabajo en equipo." : null,
            'fecha_nacimiento' => now()->subYears(18 + intdiv($semestre, 2))->subDays($semestre * 23)->toDateString(),
            'lugar_nacimiento' => 'Neiva, Huila',
            'estado_civil' => 'Soltero(a)',
            'lugar_residencia' => 'Neiva',
            'direccion' => $avanzado ? "Calle {$semestre} # 12-34" : null,
            'rh' => $avanzado ? 'O+' : null,
            'contacto_emergencia_nombre' => $avanzado ? "Familiar de {$estudiante->nombres}" : null,
            'contacto_emergencia_telefono' => $avanzado ? '3201234567' : null,
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
     * Una postulación en cada etapa: por decidir en la empresa, seleccionada
     * (espera la autorización docente), descartada y autorizadas.
     *
     * @param  array<string, Estudiante>  $estudiantes
     * @param  array<int, Vacante>  $vacantes
     */
    private function postulaciones(array $estudiantes, array $vacantes): void
    {
        $datos = [
            ['julian', 1, EstadoPostulacion::Enviada, null],
            ['sofia', 5, EstadoPostulacion::Enviada, null],
            ['mateo', 3, EstadoPostulacion::Seleccionada, null],
            ['mateo', 1, EstadoPostulacion::Descartada, null],
            ['andres', 1, EstadoPostulacion::Aceptada, 'Cumple requisitos.'],
            ['valentina', 4, EstadoPostulacion::Aceptada, 'Cumple requisitos.'],
        ];

        foreach ($datos as [$alias, $vacante, $estado, $observacion]) {
            $fecha = now()->subDays(rand(10, 30));
            $vacantes[$vacante]->postulaciones()->firstOrCreate(
                ['estudiante_id' => $estudiantes[$alias]->id],
                [
                    'estado' => $estado,
                    'observacion' => $observacion,
                    'fecha' => $fecha,
                    'decidida_en' => $estado === EstadoPostulacion::Enviada ? null : $fecha->copy()->addDays(rand(1, 4)),
                    'revisada_en' => in_array($estado, [EstadoPostulacion::Aceptada, EstadoPostulacion::Rechazada], true) ? $fecha->copy()->addDays(rand(5, 9)) : null,
                ],
            );
        }
    }

    /**
     * @param  array<string, Estudiante>  $estudiantes
     * @param  array<string, Empresa>  $empresas
     * @param  array<int, Vacante>  $vacantes
     */
    private function vinculaciones(array $estudiantes, array $empresas, array $vacantes, Usuario $docente, PeriodoAcademico $periodo): void
    {
        // A Andrés solo le falta que la empresa apruebe su última semana para completar
        // las horas; Valentina ya las completó y espera que la empresa la evalúe.
        $datos = [
            ['andres', 'nodo', 1, '2026-07-21', 472],
            ['valentina', 'csg', 4, '2026-07-14', 488],
        ];

        foreach ($datos as [$alias, $empresa, $vacante, $inicio, $horas]) {
            $estudiante = $estudiantes[$alias];

            $vinculacion = $estudiante->vinculaciones()->firstOrCreate(['vacante_id' => $vacantes[$vacante]->id], [
                'empresa_id' => $empresas[$empresa]->id,
                'tutor_id' => $docente->id,
                'tipo' => Modalidad::Pasantia,
                'semestre_al_vincular' => $estudiante->semestre_actual,
                'fecha_inicio' => $inicio,
                'periodo_id' => $periodo->id,
            ]);

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

        $requeridas = $vinculacion->estudiante->programa->horas_requeridas;
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

        $estudiantes['laura']->usuario->reportes()->create([
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
