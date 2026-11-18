<?php

namespace Tests\Feature;

use App\Enums\EstadoHojaVida;
use App\Enums\Modalidad;
use App\Enums\Rol;
use App\Models\Estudiante;
use App\Models\Programa;
use App\Models\Usuario;
use App\Models\Vinculacion;
use App\Notifications\NoHabilitado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * El director define los cupos de pasantía y asigna a cada estudiante su
 * docente tutor; cada docente atiende solo a su grupo.
 */
class CuposYTutoresTest extends TestCase
{
    use RefreshDatabase;

    private Programa $programa;

    private Usuario $docente;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->programa = Programa::factory()->create();
        $this->docente = $this->usuarioDelPrograma(Rol::Docente);
    }

    private function usuarioDelPrograma(Rol $rol): Usuario
    {
        return Usuario::factory()->rol($rol)->create(['programa_id' => $this->programa->id]);
    }

    /** @return array<string, mixed> */
    private function fila(int $numero, int $semestre, string $modalidad = 'PASANTIA'): array
    {
        return [
            'fila' => $numero,
            'correo' => "estudiante{$numero}@fet.edu.co",
            'nombres' => 'Ana',
            'apellidos' => "Pérez {$numero}",
            'programa_id' => $this->programa->id,
            'semestre_actual' => $semestre,
            'modalidad_grado' => $modalidad,
            'promedio' => 4.5,
        ];
    }

    private function confirmar(array $filas, array $avisos = [])
    {
        Sanctum::actingAs($this->docente);

        return $this->postJson('/api/docente/importaciones/confirmacion', ['filas' => $filas, 'no_habilitados' => $avisos])->assertOk();
    }

    public function test_el_docente_no_se_pasa_de_los_cupos_que_define_el_director(): void
    {
        Sanctum::actingAs($this->usuarioDelPrograma(Rol::Director));
        $this->putJson('/api/director/cupos', ['tecnico' => 1, 'tecnologo' => 5])
            ->assertOk()
            ->assertJsonPath('TECNICO.limite', 1)
            ->assertJsonPath('TECNICO.disponibles', 1);

        $this->confirmar([$this->fila(1, 3), $this->fila(2, 3)])
            ->assertJsonPath('creados', 1)
            ->assertJsonPath('errores.0.fila', 2)
            ->assertJsonPath('errores.0.error', 'No quedan cupos de pasantía para el ciclo Técnico en este periodo.');

        // Tecnólogo tiene sus propios cupos.
        $this->confirmar([$this->fila(3, 5)])->assertJsonPath('creados', 1);
    }

    public function test_la_practica_y_el_ciclo_profesional_no_tienen_limite(): void
    {
        Sanctum::actingAs($this->usuarioDelPrograma(Rol::Director));
        $this->putJson('/api/director/cupos', ['tecnico' => 0, 'tecnologo' => 0])->assertOk();

        $this->confirmar([$this->fila(1, 3, 'PRACTICA'), $this->fila(2, 5, 'PRACTICA'), $this->fila(3, 9)])
            ->assertJsonPath('creados', 3)
            ->assertJsonCount(0, 'errores');
    }

    public function test_quien_queda_sin_cupo_recibe_el_correo_con_el_motivo(): void
    {
        $this->confirmar([], [[...$this->fila(1, 3), 'motivo_no_habilitado' => NoHabilitado::POR_CUPO]])
            ->assertJsonPath('no_habilitados', 1);

        Notification::assertSentOnDemand(NoHabilitado::class);
        $this->assertDatabaseCount('estudiante', 0);
    }

    public function test_el_director_asigna_tutor_y_cada_docente_atiende_a_su_grupo(): void
    {
        $otro = $this->usuarioDelPrograma(Rol::Docente);
        $estudiante = Estudiante::factory()->for($this->programa, 'programa')->enSemestre(5)->create(['modalidad_grado' => Modalidad::Pasantia]);
        $estudiante->hojaVida()->update(['estado' => EstadoHojaVida::EnRevision]);
        $practica = Vinculacion::factory()->for($estudiante)->create(['tutor_id' => null]);

        Sanctum::actingAs($this->usuarioDelPrograma(Rol::Director));
        $this->getJson('/api/director/docentes')->assertOk()->assertJsonCount(2);
        $this->postJson('/api/director/estudiantes/tutor', ['estudiante_ids' => [$estudiante->id], 'tutor_id' => $this->docente->id])->assertOk();
        $this->getJson('/api/director/estudiantes')->assertJsonPath('data.0.tutor.id', $this->docente->id);
        $this->assertSame($this->docente->id, $practica->fresh()->tutor_id);

        Sanctum::actingAs($otro);
        $this->getJson('/api/docente/hojas-vida')->assertOk()->assertJsonCount(0);
        $this->patchJson("/api/docente/hojas-vida/{$estudiante->id}", ['aprobar' => true])->assertForbidden();
        $this->getJson("/api/vinculaciones/{$practica->id}")->assertForbidden();

        Sanctum::actingAs($this->docente);
        $this->getJson('/api/docente/hojas-vida')->assertJsonPath('0.id', $estudiante->id);
        $this->patchJson("/api/docente/hojas-vida/{$estudiante->id}", ['aprobar' => true])->assertOk();
    }

    public function test_el_director_no_asigna_docentes_ni_estudiantes_de_otro_programa(): void
    {
        $ajeno = Usuario::factory()->rol(Rol::Docente)->create(['programa_id' => Programa::factory()->create()->id]);
        $estudiante = Estudiante::factory()->for($this->programa, 'programa')->create();
        $otroEstudiante = Estudiante::factory()->create();

        Sanctum::actingAs($this->usuarioDelPrograma(Rol::Director));
        $this->postJson('/api/director/estudiantes/tutor', ['estudiante_ids' => [$estudiante->id], 'tutor_id' => $ajeno->id])
            ->assertJsonValidationErrors('tutor_id');
        $this->postJson('/api/director/estudiantes/tutor', ['estudiante_ids' => [$otroEstudiante->id], 'tutor_id' => $this->docente->id])
            ->assertJsonValidationErrors('estudiante_ids.0');
    }
}
