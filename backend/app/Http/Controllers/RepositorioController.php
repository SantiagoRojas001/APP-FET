<?php

namespace App\Http\Controllers;

use App\Models\Proyecto;
use App\Services\GitHubService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Vista previa del repositorio de GitHub de un proyecto del estudiante.
 */
class RepositorioController extends Controller
{
    public function __invoke(Proyecto $proyecto, GitHubService $github): JsonResponse
    {
        Gate::authorize('view', $proyecto);

        return response()->json($github->vistaPrevia($proyecto->url_repositorio));
    }
}
