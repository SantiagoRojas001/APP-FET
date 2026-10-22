<?php

namespace Database\Seeders;

use App\Enums\EstadoPostulacion;
use App\Enums\EstadoRevision;
use App\Enums\EstadoValidacion;
use App\Enums\Modalidad;
use App\Enums\NivelCompetencia;
use App\Enums\Rol;
use App\Enums\TipoDocumento;
use App\Models\Competencia;
use App\Models\Empresa;
use App\Models\Estudiante;
use App\Models\Programa;
use App\Models\Usuario;
use App\Models\Vacante;
use Illuminate\Database\Seeder;
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

        $this->postulaciones($estudiantes, $vacantes);
        $this->vinculaciones($estudiantes, $empresas, $vacantes, $docente);
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
                $estudiante->hojaVida()->create([
                    'perfil_profesional' => $semestre > 2 ? "Estudiante de {$semestre}.º semestre interesado en el desarrollo de software y el trabajo en equipo." : null,
                    'formacion' => "Bachiller académico. Cursando {$semestre}.º semestre en la FET.",
                ]);
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
     * @param  array<string, Estudiante>  $estudiantes
     * @param  array<int, Vacante>  $vacantes
     */
    private function postulaciones(array $estudiantes, array $vacantes): void
    {
        $datos = [
            ['julian', 1, EstadoPostulacion::Aprobada, 'Cumple el semestre mínimo de su programa.'],
            ['sofia', 5, EstadoPostulacion::Enviada, null],
            ['mateo', 3, EstadoPostulacion::Enviada, null],
            ['mateo', 1, EstadoPostulacion::Rechazada, 'Completa tu hoja de vida (perfil y proyectos) antes de postularte.'],
            ['andres', 1, EstadoPostulacion::Aceptada, 'Cumple requisitos.'],
            ['valentina', 4, EstadoPostulacion::Aceptada, 'Cumple requisitos.'],
        ];

        foreach ($datos as [$alias, $vacante, $estado, $observacion]) {
            $vacantes[$vacante]->postulaciones()->firstOrCreate(
                ['estudiante_id' => $estudiantes[$alias]->id],
                ['estado' => $estado, 'observacion' => $observacion, 'fecha' => now()->subDays(rand(3, 20))],
            );
        }
    }

    /**
     * @param  array<string, Estudiante>  $estudiantes
     * @param  array<string, Empresa>  $empresas
     * @param  array<int, Vacante>  $vacantes
     */
    private function vinculaciones(array $estudiantes, array $empresas, array $vacantes, Usuario $docente): void
    {
        $datos = [
            ['andres', 'nodo', 1, '2026-08-04'],
            ['valentina', 'csg', 4, '2026-07-28'],
        ];

        foreach ($datos as [$alias, $empresa, $vacante, $inicio]) {
            $estudiante = $estudiantes[$alias];

            $vinculacion = $estudiante->vinculaciones()->firstOrCreate(['vacante_id' => $vacantes[$vacante]->id], [
                'empresa_id' => $empresas[$empresa]->id,
                'tutor_id' => $docente->id,
                'tipo' => Modalidad::Pasantia,
                'semestre_al_vincular' => $estudiante->semestre_actual,
                'fecha_inicio' => $inicio,
            ]);

            if (! $vinculacion->wasRecentlyCreated) {
                continue;
            }

            $documentos = [
                [TipoDocumento::InformeAvance, '2026-08-04', '2026-08-31', EstadoRevision::Aprobado, null],
                [TipoDocumento::ControlAsistencia, '2026-09-01', '2026-09-15', EstadoRevision::Aprobado, null],
                [TipoDocumento::DiarioCampo, '2026-09-14', '2026-09-20', EstadoRevision::Observado, 'Describe con más detalle las tareas del módulo.'],
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
