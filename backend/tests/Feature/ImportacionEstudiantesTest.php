<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Estudiante;
use App\Models\Programa;
use App\Models\Usuario;
use App\Notifications\CredencialesAcceso;
use App\Notifications\NoHabilitado;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

/**
 * El docente carga el listado de modalidades de grado (PDF, Excel o CSV), la
 * app filtra a quienes eligieron pasantía o práctica en su programa y, al
 * confirmar, crea las cuentas de los habilitados y envía el motivo a los demás.
 * Los seis programas de la FET los crea la migración.
 */
class ImportacionEstudiantesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    /** Docente de Ingeniería de Software. */
    private function comoDocente(): Usuario
    {
        $docente = $this->como(Rol::Docente);
        $docente->update(['programa_id' => Programa::where('nombre', 'Ingeniería de Software')->value('id')]);

        return $docente;
    }

    private function pdf(string $html): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('modalidades.pdf', Pdf::loadHTML($html)->output());
    }

    private function vistaPrevia(UploadedFile $archivo)
    {
        return $this->postJson('/api/docente/importaciones', ['archivo' => $archivo])->assertOk();
    }

    public function test_lee_la_tabla_del_pdf_y_filtra_por_modalidad_y_semestre(): void
    {
        $this->comoDocente();
        Usuario::factory()->rol(Rol::Estudiante)->create(['correo' => 'ya.existe@fet.edu.co']);

        $archivo = $this->pdf('<h3>Modalidades de grado 2026-2</h3><table border="1">
            <tr><th>Nombre</th><th>Correo</th><th>Programa</th><th>Ciclo</th><th>Modalidad de grado</th></tr>
            <tr><td>Ana María López Díaz</td><td>ana.lopez@fet.edu.co</td><td>Ingeniería de Software</td><td>Profesional</td><td>Pasantía</td></tr>
            <tr><td>Carlos Rojas</td><td>carlos.rojas@fet.edu.co</td><td>Tecnología en Desarrollo de Software</td><td>Tecnólogo</td><td>Práctica profesional</td></tr>
            <tr><td>Lucía Vargas Peña</td><td>lucia.vargas@fet.edu.co</td><td>Técnica Profesional en Programación de Software</td><td>Técnico</td><td>Proyecto de grado</td></tr>
            <tr><td>Pedro Ya Existe</td><td>ya.existe@fet.edu.co</td><td>Ingeniería de Software</td><td>Profesional</td><td>Pasantía</td></tr>
            <tr><td>Marta Cuéllar</td><td>marta.cuellar@fet.edu.co</td><td>Tecnología en Gestión Ambiental</td><td>Tecnólogo</td><td>Pasantía</td></tr>
        </table>');

        $filas = $this->vistaPrevia($archivo)
            ->assertJsonPath('resumen.nuevo', 2)
            ->assertJsonPath('resumen.omitido', 2)
            ->assertJsonPath('resumen.registrado', 1)
            ->json('filas');

        $this->assertSame(['Ana María', 'López Díaz', 9, 'PASANTIA'], [$filas[0]['nombres'], $filas[0]['apellidos'], $filas[0]['semestre_actual'], $filas[0]['modalidad_grado']]);
        $this->assertSame([5, 'PRACTICA', 'Tecnólogo'], [$filas[1]['semestre_actual'], $filas[1]['modalidad_grado'], $filas[1]['ciclo']]);
        $this->assertStringContainsString('Proyecto de grado', $filas[2]['motivo']);
        $this->assertSame('Es de otro programa (Ingeniería Ambiental).', $filas[4]['motivo']);
        $this->assertDatabaseCount('estudiante', 0);
        Notification::assertNothingSent();

        // Al confirmar se crean las cuentas, sin correo hasta que se registre el promedio.
        $nuevas = array_values(array_filter($filas, fn ($f) => $f['estado'] === 'nuevo'));
        $this->postJson('/api/docente/importaciones/confirmacion', ['filas' => $nuevas])
            ->assertOk()
            ->assertJsonPath('creados', 2)
            ->assertJsonPath('pendientes_de_promedio', 2);

        $this->assertDatabaseHas('usuario', ['correo' => 'ana.lopez@fet.edu.co', 'rol' => 'ESTUDIANTE']);
        $this->assertSame(9, Estudiante::where('nombres', 'Ana María')->value('semestre_actual'));
        Notification::assertNothingSent();

        // Una segunda carga del mismo listado no duplica cuentas.
        $this->postJson('/api/docente/importaciones/confirmacion', ['filas' => $nuevas])
            ->assertJsonPath('creados', 0)
            ->assertJsonCount(2, 'errores');
    }

    public function test_lee_un_pdf_sin_tabla_y_avisa_a_quien_cursa_un_semestre_no_habilitado(): void
    {
        $this->comoDocente();

        $filas = $this->vistaPrevia($this->pdf('<p>Listado de estudiantes</p>
            <p>Valentina Ruiz - valentina.ruiz@fet.edu.co - Ingeniería de Software - 9.º semestre - Pasantía</p>
            <p>Jorge Díaz - jorge.diaz@fet.edu.co - Ingeniería de Software - 7.º semestre - Pasantía</p>'))
            ->assertJsonPath('resumen.no_habilitado', 1)
            ->json('filas');

        $this->assertSame(['nuevo', 'valentina.ruiz@fet.edu.co', 9], [$filas[0]['estado'], $filas[0]['correo'], $filas[0]['semestre_actual']]);
        $this->assertSame(['no_habilitado', 'semestre'], [$filas[1]['estado'], $filas[1]['motivo_no_habilitado']]); // 7.º no está habilitado

        $this->postJson('/api/docente/importaciones/confirmacion', ['filas' => [$filas[0]], 'no_habilitados' => [$filas[1]]])
            ->assertJsonPath('creados', 1)
            ->assertJsonPath('no_habilitados', 1);

        $this->assertDatabaseMissing('usuario', ['correo' => 'jorge.diaz@fet.edu.co']);
        Notification::assertSentOnDemand(NoHabilitado::class, function (NoHabilitado $aviso, array $canales, object $destino) {
            return $destino->routes['mail'] === 'jorge.diaz@fet.edu.co'
                && str_contains(implode(' ', $aviso->toMail($destino)->introLines), 'cursas 7.º semestre');
        });

        // A quien sí está habilitado no se le envía el motivo.
        $this->postJson('/api/docente/importaciones/confirmacion', ['filas' => [], 'no_habilitados' => [[...$filas[1], 'semestre_actual' => 9]]])
            ->assertJsonPath('no_habilitados', 0)
            ->assertJsonPath('errores.0.error', fn ($m) => str_contains($m, 'sí está habilitado'));
    }

    public function test_con_excel_y_promedio_envia_el_resultado_al_confirmar(): void
    {
        $this->comoDocente();
        $ruta = tempnam(sys_get_temp_dir(), 'modalidades').'.xlsx';
        $escritor = new Writer;
        $escritor->openToFile($ruta);
        $escritor->addRows([
            Row::fromValues(['Nombres', 'Apellidos', 'Correo electrónico', 'Programa', 'Semestre', 'Modalidad', 'Promedio']),
            Row::fromValues(['Sara', 'Gómez', 'sara.gomez@fet.edu.co', 'Ingeniería de Software', 9, 'Práctica', '4,5']),
            Row::fromValues(['Luis', 'Pardo', 'luis.pardo@fet.edu.co', 'Ingeniería de Software', 9, 'Pasantía', '3,8']),
        ]);
        $escritor->close();

        $filas = $this->vistaPrevia(new UploadedFile($ruta, 'modalidades.xlsx', null, null, true))
            ->assertJsonPath('resumen.nuevo', 1)
            ->assertJsonPath('resumen.no_habilitado', 1)
            ->json('filas');
        $this->assertSame(4.5, $filas[0]['promedio']);
        $this->assertSame('promedio', $filas[1]['motivo_no_habilitado']);

        $this->postJson('/api/docente/importaciones/confirmacion', ['filas' => [$filas[0]], 'no_habilitados' => [$filas[1]]])
            ->assertJsonPath('creados', 1)
            ->assertJsonPath('con_correo', 1)
            ->assertJsonPath('no_habilitados', 1);

        Notification::assertSentTo(Usuario::where('correo', 'sara.gomez@fet.edu.co')->first(), CredencialesAcceso::class);
        $this->assertDatabaseMissing('usuario', ['correo' => 'luis.pardo@fet.edu.co']);
        Notification::assertSentOnDemand(NoHabilitado::class, fn ($aviso, $canales, $destino) => $destino->routes['mail'] === 'luis.pardo@fet.edu.co');
    }

    public function test_lee_csv_con_punto_y_coma_y_pide_revisar_lo_que_no_reconoce(): void
    {
        $this->comoDocente();
        $csv = "Nombre completo;Correo;Programa;Ciclo;Modalidad de grado\n"
            ."Elena Torres Ruiz;elena.torres@fet.edu.co;Ingeniería de Software;Profesional;Pasantía\n"
            ."Mario Silva;mario.silva@fet.edu.co;Contaduría Pública;Profesional;Práctica\n";

        $filas = $this->vistaPrevia(UploadedFile::fake()->createWithContent('modalidades.csv', $csv))->json('filas');

        $this->assertSame(['nuevo', 'Elena', 'Torres Ruiz'], [$filas[0]['estado'], $filas[0]['nombres'], $filas[0]['apellidos']]);
        $this->assertSame('revisar', $filas[1]['estado']);
        $this->assertStringContainsString('Contaduría Pública', $filas[1]['motivo']);
    }

    public function test_rechaza_archivos_sin_estudiantes_o_de_otro_tipo(): void
    {
        $this->comoDocente();

        $this->postJson('/api/docente/importaciones', ['archivo' => $this->pdf('<p>Sin datos</p>')])
            ->assertUnprocessable()
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'No encontramos estudiantes'));
        $this->postJson('/api/docente/importaciones', ['archivo' => UploadedFile::fake()->create('foto.png', 10, 'image/png')])
            ->assertJsonValidationErrors('archivo');
    }
}
