<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Empresa;
use App\Models\Proyecto;
use App\Services\GitHubService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RepositorioGitHubTest extends TestCase
{
    use RefreshDatabase;

    private function proyecto(string $url = 'https://github.com/ana/citas-api'): Proyecto
    {
        return $this->estudiante(5)->proyectos()->create(['titulo' => 'Citas', 'url_repositorio' => $url]);
    }

    private function fakeGitHub(): void
    {
        Http::fake([
            'api.github.com/repos/ana/citas-api/languages' => Http::response(['PHP' => 750, 'Blade' => 250]),
            'api.github.com/repos/ana/citas-api/commits*' => Http::response([[
                'sha' => 'abc1234def5678',
                'html_url' => 'https://github.com/ana/citas-api/commit/abc1234',
                'commit' => ['message' => "Agrega reservas\n\nDetalle", 'author' => ['name' => 'Ana', 'date' => '2026-10-01T10:00:00Z']],
            ]]),
            'api.github.com/repos/ana/citas-api' => Http::response([
                'full_name' => 'ana/citas-api', 'html_url' => 'https://github.com/ana/citas-api', 'description' => 'API de citas',
                'owner' => ['login' => 'ana', 'avatar_url' => 'https://avatars.githubusercontent.com/u/1', 'html_url' => 'https://github.com/ana'],
                'default_branch' => 'main', 'stargazers_count' => 3, 'forks_count' => 1, 'pushed_at' => '2026-10-01T10:00:00Z',
            ]),
        ]);
    }

    public function test_reconoce_solo_enlaces_de_repositorios_de_github(): void
    {
        $github = new GitHubService;

        $this->assertSame(['ana', 'citas-api'], $github->parsearUrl('https://github.com/ana/citas-api.git'));
        $this->assertSame(['ana', 'citas-api'], $github->parsearUrl('https://www.github.com/ana/citas-api/tree/main'));
        $this->assertNull($github->parsearUrl('https://gitlab.com/ana/citas-api'));
        $this->assertNull($github->parsearUrl('https://github.com/ana'));
    }

    public function test_el_estudiante_ve_la_vista_previa_de_su_repositorio(): void
    {
        $this->fakeGitHub();
        $proyecto = $this->proyecto();

        $this->comoEstudiante($proyecto->estudiante)
            ->getJson("/api/proyectos/{$proyecto->id}/repositorio")
            ->assertOk()
            ->assertJsonPath('disponible', true)
            ->assertJsonPath('nombre', 'ana/citas-api')
            ->assertJsonPath('lenguajes.0', ['nombre' => 'PHP', 'porcentaje' => 75])
            ->assertJsonPath('commits.0.sha', 'abc1234')
            ->assertJsonPath('commits.0.mensaje', 'Agrega reservas');
    }

    public function test_guarda_la_respuesta_en_cache(): void
    {
        $this->fakeGitHub();
        $proyecto = $this->proyecto();
        $this->comoEmpresa(Empresa::factory()->create());

        $this->getJson("/api/proyectos/{$proyecto->id}/repositorio")->assertOk();
        $this->getJson("/api/proyectos/{$proyecto->id}/repositorio")->assertOk();

        Http::assertSentCount(3); // repo, lenguajes y commits: una sola vez
    }

    public function test_informa_cuando_el_repositorio_no_existe_o_no_es_de_github(): void
    {
        Http::fake(['api.github.com/*' => Http::response(['message' => 'Not Found'], 404)]);
        $this->como(Rol::Docente);

        $this->getJson("/api/proyectos/{$this->proyecto()->id}/repositorio")
            ->assertOk()
            ->assertJsonPath('disponible', false)
            ->assertJsonPath('motivo', 'El repositorio no existe o es privado.');

        $this->getJson("/api/proyectos/{$this->proyecto('https://gitlab.com/ana/x')->id}/repositorio")
            ->assertJsonPath('motivo', 'El enlace no es un repositorio de GitHub.');
    }

    public function test_un_estudiante_no_ve_los_repositorios_de_otro(): void
    {
        Http::fake();
        $ajeno = $this->proyecto();

        $this->comoEstudiante($this->estudiante(5))
            ->getJson("/api/proyectos/{$ajeno->id}/repositorio")
            ->assertForbidden();
    }
}
