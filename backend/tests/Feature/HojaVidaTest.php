<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Competencia;
use App\Models\Empresa;
use App\Models\Postulacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HojaVidaTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function hojaCompleta(Competencia $competencia): array
    {
        return [
            'telefono' => '3001234567',
            'disponibilidad' => 'Tiempo completo',
            'perfil_profesional' => 'Desarrollador en formación.',
            'fecha_nacimiento' => '2004-04-21',
            'lugar_nacimiento' => 'Neiva, Huila',
            'estado_civil' => 'Soltero(a)',
            'lugar_residencia' => 'Neiva',
            'direccion' => 'Calle 14 # 43-20',
            'rh' => 'A+',
            'contacto_emergencia_nombre' => 'Juan Ipuz',
            'contacto_emergencia_telefono' => '3224785961',
            'formaciones' => [['nivel' => 'TECNICO', 'titulo' => 'Técnico en Sistemas', 'institucion' => 'SENA', 'pais_ciudad' => 'Colombia, Neiva', 'fecha_grado' => '2022-11-20', 'modalidad' => 'Presencial']],
            'cursos' => [['tema' => 'IA en la programación', 'duracion_horas' => 80, 'fecha' => '2024-11', 'institucion' => 'Scrum.org', 'modalidad' => 'Virtual']],
            'experiencias' => [['cargo' => 'Monitor', 'organizacion' => 'FET', 'jefe_inmediato' => 'Alfredo Reyes', 'dedicacion' => 'Medio tiempo', 'fecha_inicio' => '2025-02-01']],
            'investigaciones' => [['tipo' => 'GRUPO', 'descripcion' => 'Semillero FetWork', 'periodo' => '2023-1 a 2024-2']],
            'referencias' => [
                ['tipo' => 'FAMILIAR', 'nombre' => 'Leopoldina Rojas', 'ocupacion' => 'Ama de casa', 'parentesco' => 'Madre', 'telefono' => '3224587643'],
                ['tipo' => 'PERSONAL', 'nombre' => 'Alonso Vergara', 'ocupacion' => 'Comerciante', 'telefono' => '3105656427'],
            ],
            'competencias' => [['id' => $competencia->id, 'nivel' => 'INTERMEDIO']],
            'proyectos' => [['titulo' => 'API', 'url_repositorio' => 'https://github.com/ana/api']],
        ];
    }

    public function test_el_estudiante_completa_su_hoja_de_vida_en_el_formato_institucional(): void
    {
        Storage::fake('local');
        $estudiante = $this->estudiante(5);
        $this->comoEstudiante($estudiante);

        $this->putJson('/api/estudiante/perfil', $this->hojaCompleta(Competencia::factory()->create(['nombre' => 'PHP'])))
            ->assertOk()
            ->assertJsonPath('hoja_vida.porcentaje', 86)
            ->assertJsonPath('hoja_vida.datos_personales.rh', 'A+')
            ->assertJsonPath('hoja_vida.formaciones.0.nivel.etiqueta', 'Técnico')
            ->assertJsonPath('hoja_vida.cursos.0.fecha', '2024-11')
            ->assertJsonPath('hoja_vida.experiencias.0.jefe_inmediato', 'Alfredo Reyes')
            ->assertJsonPath('hoja_vida.investigaciones.0.tipo.valor', 'GRUPO')
            ->assertJsonCount(2, 'hoja_vida.referencias')
            ->assertJsonPath('competencias.0.nivel', 'INTERMEDIO');

        // Con la foto queda completa y aparece en el PDF institucional.
        $this->post('/api/estudiante/foto', ['foto' => UploadedFile::fake()->image('foto.png', 300, 400)])
            ->assertOk();
        $this->getJson('/api/estudiante/perfil')->assertJsonPath('hoja_vida.porcentaje', 100);
        $this->get("/api/estudiantes/{$estudiante->id}/foto")->assertOk();
        $this->get('/api/estudiante/hoja-vida.pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_valida_las_opciones_del_formato(): void
    {
        $estudiante = $this->estudiante(5);
        $datos = $this->hojaCompleta(Competencia::factory()->create());

        $this->comoEstudiante($estudiante)->putJson('/api/estudiante/perfil', [
            ...$datos,
            'rh' => 'Z+',
            'referencias' => [['tipo' => 'FAMILIAR', 'nombre' => 'X', 'ocupacion' => 'Y', 'telefono' => '1']],
            'cursos' => [['tema' => 'X', 'duracion_horas' => 10, 'fecha' => '2024-11-05', 'institucion' => 'Y', 'modalidad' => 'Presencial']],
        ])->assertJsonValidationErrors(['rh', 'referencias.0.parentesco', 'cursos.0.fecha']);

        $this->postJson('/api/estudiante/foto', ['foto' => UploadedFile::fake()->create('foto.pdf', 10, 'application/pdf')])
            ->assertJsonValidationErrors('foto');
    }

    public function test_la_foto_solo_la_ven_el_estudiante_y_quien_consulta_perfiles(): void
    {
        Storage::fake('local');
        $estudiante = $this->estudiante(5);
        $this->comoEstudiante($estudiante)->post('/api/estudiante/foto', ['foto' => UploadedFile::fake()->image('foto.png', 300, 400)])->assertOk();

        $this->comoEmpresa(Empresa::factory()->create())->get("/api/estudiantes/{$estudiante->id}/foto")->assertOk();
        $this->comoEstudiante($this->estudiante(5))->get("/api/estudiantes/{$estudiante->id}/foto")->assertForbidden();
    }

    public function test_rechaza_repositorios_que_no_son_enlaces_https(): void
    {
        $estudiante = $this->estudiante(5);

        $this->comoEstudiante($estudiante)->putJson('/api/estudiante/perfil', [
            'experiencias' => [],
            'formaciones' => [],
            'cursos' => [],
            'investigaciones' => [],
            'referencias' => [],
            'competencias' => [],
            'proyectos' => [['titulo' => 'X', 'url_repositorio' => 'javascript:alert(1)']],
        ])->assertJsonValidationErrors('proyectos.0.url_repositorio');
    }

    public function test_las_empresas_ven_el_perfil_sin_el_documento_de_identidad(): void
    {
        $estudiante = $this->estudiante(5);
        $this->comoEmpresa(Empresa::factory()->create());

        $this->getJson("/api/estudiantes/{$estudiante->id}")
            ->assertOk()
            ->assertJsonPath('nombre_completo', $estudiante->nombre_completo)
            ->assertJsonMissingPath('documento');
    }

    public function test_los_listados_de_estudiantes_y_postulaciones_cargan_sin_consultas_n_mas_1(): void
    {
        Postulacion::factory()->count(3)->create();
        $this->como(Rol::Docente);

        // Con Model::shouldBeStrict activo, una carga perezosa haría fallar la petición.
        $this->getJson('/api/estudiantes')->assertOk()->assertJsonCount(3, 'data');
        $this->getJson('/api/docente/postulaciones')->assertOk()->assertJsonCount(3);
    }
}
