<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\RecuperacionController;
use App\Http\Controllers\Auth\RegistroController;
use App\Http\Controllers\Auth\SesionController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\Docente;
use App\Http\Controllers\Empresa;
use App\Http\Controllers\Estudiante;
use App\Http\Controllers\PerfilEstudianteController;
use App\Http\Controllers\RepositorioController;
use App\Http\Controllers\Seguimiento\DocumentoController;
use Illuminate\Support\Facades\Route;

/*
| API REST de APP FET. Todas las rutas quedan bajo /api.
| Autenticación con tokens de Laravel Sanctum (encabezado Authorization: Bearer).
*/

// Público
Route::get('catalogos', CatalogoController::class);
Route::post('auth/login', [SesionController::class, 'store'])->middleware('throttle:login');
Route::middleware('throttle:5,1')->group(function () {
    Route::post('auth/olvide-contrasena', [RecuperacionController::class, 'solicitar']);
    Route::post('auth/restablecer-contrasena', [RecuperacionController::class, 'restablecer']);
});
Route::middleware('throttle:10,1')->group(function () {
    Route::post('registro/estudiante', [RegistroController::class, 'estudiante']);
    Route::post('registro/empresa', [RegistroController::class, 'empresa']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('auth/yo', [SesionController::class, 'show']);
    Route::post('auth/logout', [SesionController::class, 'destroy']);
    Route::patch('auth/preferencias', [SesionController::class, 'preferencias']);

    // Seguimiento de una vinculación (cada acción valida permisos con VinculacionPolicy)
    Route::get('vinculaciones/{vinculacion}', [DocumentoController::class, 'vinculacion']);
    Route::get('vinculaciones/{vinculacion}/documentos', [DocumentoController::class, 'index']);
    Route::post('vinculaciones/{vinculacion}/documentos', [DocumentoController::class, 'store']);
    Route::get('documentos/{documento}/archivo', [DocumentoController::class, 'descargar']);
    Route::patch('documentos/{documento}/revision', [DocumentoController::class, 'revisar']);

    // Vista previa del repositorio de GitHub de un proyecto (permiso: ProyectoPolicy)
    Route::get('proyectos/{proyecto}/repositorio', RepositorioController::class)->middleware('throttle:60,1');

    // Perfiles de estudiantes para quien evalúa candidatos
    Route::middleware('rol:EMPRESA,DOCENTE,ADMIN')->group(function () {
        Route::get('estudiantes', [PerfilEstudianteController::class, 'index']);
        Route::get('estudiantes/{estudiante}', [PerfilEstudianteController::class, 'show']);
    });

    Route::prefix('estudiante')->middleware('rol:ESTUDIANTE')->group(function () {
        Route::get('perfil', [Estudiante\PerfilController::class, 'show']);
        Route::put('perfil', [Estudiante\PerfilController::class, 'update']);
        Route::get('elegibilidad', Estudiante\ElegibilidadController::class);
        Route::get('vacantes', [Estudiante\VacanteController::class, 'index']);
        Route::post('vacantes/{vacante}/postulaciones', [Estudiante\PostulacionController::class, 'store']);
        Route::get('postulaciones', [Estudiante\PostulacionController::class, 'index']);
        Route::get('vinculaciones', [Estudiante\VinculacionController::class, 'index']);
    });

    Route::prefix('docente')->middleware('rol:DOCENTE')->group(function () {
        Route::get('postulaciones', [Docente\PostulacionController::class, 'index']);
        Route::patch('postulaciones/{postulacion}', [Docente\PostulacionController::class, 'update']);
        Route::get('vinculaciones', [Docente\VinculacionController::class, 'index']);
        Route::patch('vinculaciones/{vinculacion}', [Docente\VinculacionController::class, 'update']);
    });

    Route::prefix('empresa')->middleware('rol:EMPRESA')->group(function () {
        Route::get('vacantes', [Empresa\VacanteController::class, 'index']);
        Route::post('vacantes', [Empresa\VacanteController::class, 'store']);
        Route::patch('vacantes/{vacante}', [Empresa\VacanteController::class, 'update'])->whereNumber('vacante');
        Route::get('postulaciones', [Empresa\PostulacionController::class, 'index']);
        Route::patch('postulaciones/{postulacion}', [Empresa\PostulacionController::class, 'update'])->whereNumber('postulacion');
        Route::get('vinculaciones', [Empresa\VinculacionController::class, 'index']);
    });

    Route::prefix('admin')->middleware('rol:ADMIN')->group(function () {
        Route::get('resumen', [Admin\UsuarioController::class, 'resumen']);
        Route::get('usuarios', [Admin\UsuarioController::class, 'index']);
        Route::post('usuarios', [Admin\UsuarioController::class, 'store']);
        Route::patch('usuarios/{usuario}', [Admin\UsuarioController::class, 'update']);
        Route::get('empresas', [Admin\EmpresaController::class, 'index']);
        Route::patch('empresas/{empresa}/validacion', [Admin\EmpresaController::class, 'validar']);
        Route::get('reglas', [Admin\ReglaElegibilidadController::class, 'index']);
        Route::post('reglas', [Admin\ReglaElegibilidadController::class, 'store']);
    });
});
