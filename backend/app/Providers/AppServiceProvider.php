<?php

namespace App\Providers;

use App\Services\ElegibilidadService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(ElegibilidadService::class);
    }

    public function boot(): void
    {
        // En desarrollo, avisa de consultas N+1 y atributos inexistentes.
        Model::shouldBeStrict(! $this->app->isProduction());

        JsonResource::withoutWrapping();

        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(10)->letters()->mixedCase()->numbers()->uncompromised()
            : Password::min(8)->letters()->numbers());

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(mb_strtolower((string) $request->input('correo')).'|'.$request->ip())
            ->response(fn () => response()->json(['message' => 'Demasiados intentos. Espera un minuto e inténtalo de nuevo.'], 429)));
    }
}
