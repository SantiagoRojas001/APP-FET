<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Vista previa de un repositorio público de GitHub: datos generales,
 * lenguajes y últimos commits. Las respuestas se guardan en caché para no
 * agotar el límite de la API.
 */
class GitHubService
{
    private const API = 'https://api.github.com';

    /**
     * Extrae "propietario/repositorio" de un enlace de GitHub, o null si el
     * enlace no es de un repositorio de github.com.
     *
     * @return array{0: string, 1: string}|null
     */
    public function parsearUrl(string $url): ?array
    {
        $partes = parse_url($url);
        $host = strtolower($partes['host'] ?? '');

        if (! in_array($host, ['github.com', 'www.github.com'], true)) {
            return null;
        }

        $segmentos = array_values(array_filter(explode('/', $partes['path'] ?? '')));
        if (count($segmentos) < 2) {
            return null;
        }

        [$propietario, $repo] = [$segmentos[0], preg_replace('/\.git$/', '', $segmentos[1])];
        $valido = '/^[A-Za-z0-9_.-]{1,100}$/';

        return preg_match($valido, $propietario) && preg_match($valido, $repo) ? [$propietario, $repo] : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function vistaPrevia(string $url): array
    {
        $ruta = $this->parsearUrl($url);

        if (! $ruta) {
            return ['disponible' => false, 'motivo' => 'El enlace no es un repositorio de GitHub.'];
        }

        [$propietario, $repo] = $ruta;
        $clave = 'github:'.strtolower("{$propietario}/{$repo}");

        if (($guardado = Cache::get($clave)) !== null) {
            return $guardado;
        }

        $vista = $this->consultar($propietario, $repo);

        // Los errores (límite de GitHub, caída) se reintentan pronto; los datos se guardan más tiempo.
        $minutos = $vista['disponible'] ? config('services.github.cache_minutos') : 2;
        Cache::put($clave, $vista, now()->addMinutes($minutos));

        return $vista;
    }

    /**
     * @return array<string, mixed>
     */
    private function consultar(string $propietario, string $repo): array
    {
        $base = self::API."/repos/{$propietario}/{$repo}";

        // En un pool, un fallo de conexión llega como valor (excepción), no se lanza.
        $respuestas = Http::pool(fn (Pool $pool) => [
            $this->cliente($pool->as('repo'))->get($base),
            $this->cliente($pool->as('lenguajes'))->get("{$base}/languages"),
            $this->cliente($pool->as('commits'))->get("{$base}/commits", ['per_page' => 5]),
        ]);
        $exitosa = fn (mixed $r) => $r instanceof Response && $r->successful();

        $datos = $respuestas['repo'];

        if (! $exitosa($datos)) {
            $estado = $datos instanceof Response ? $datos->status() : 0;

            return ['disponible' => false, 'motivo' => match ($estado) {
                404 => 'El repositorio no existe o es privado.',
                403, 429 => 'GitHub limitó las consultas por ahora. Inténtalo más tarde.',
                0 => 'No fue posible conectar con GitHub. Inténtalo más tarde.',
                default => 'No fue posible consultar GitHub. Inténtalo más tarde.',
            }];
        }

        $r = $datos->json();
        $lenguajes = $exitosa($respuestas['lenguajes']) ? $respuestas['lenguajes']->json() : [];
        $commits = $exitosa($respuestas['commits']) ? $respuestas['commits']->json() : [];
        $totalBytes = array_sum($lenguajes) ?: 1;

        return [
            'disponible' => true,
            'nombre' => $r['full_name'],
            'url' => $r['html_url'],
            'descripcion' => $r['description'],
            'propietario' => [
                'usuario' => $r['owner']['login'],
                'avatar' => $r['owner']['avatar_url'],
                'url' => $r['owner']['html_url'],
            ],
            'rama' => $r['default_branch'],
            'estrellas' => $r['stargazers_count'],
            'forks' => $r['forks_count'],
            'actualizado_en' => $r['pushed_at'],
            'lenguajes' => collect($lenguajes)
                ->map(fn (int $bytes, string $nombre) => ['nombre' => $nombre, 'porcentaje' => round($bytes / $totalBytes * 100, 1)])
                ->filter(fn (array $l) => $l['porcentaje'] > 0)
                ->values()
                ->take(6)
                ->all(),
            'commits' => collect($commits)->map(fn (array $c) => [
                'sha' => substr($c['sha'], 0, 7),
                'mensaje' => strtok($c['commit']['message'], "\n"),
                'autor' => $c['commit']['author']['name'] ?? null,
                'fecha' => $c['commit']['author']['date'] ?? null,
                'url' => $c['html_url'],
            ])->all(),
        ];
    }

    private function cliente(PendingRequest $request): PendingRequest
    {
        return $request
            ->withHeaders(['Accept' => 'application/vnd.github+json', 'User-Agent' => 'APP-FET'])
            ->when(config('services.github.token'), fn (PendingRequest $r, string $token) => $r->withToken($token))
            ->timeout(8);
    }
}
