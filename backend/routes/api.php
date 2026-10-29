<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\ActivacionController;
use App\Http\Controllers\Auth\RecuperacionController;
use App\Http\Controllers\Auth\RegistroEmpresaController;
use App\Http\Controllers\Auth\SesionController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\Docente;
use App\Http\Controllers\Empresa;
use App\Http\Controllers\Estudiante;
use App\Http\Controllers\FotoEstudianteController;
use App\Http\Controllers\HojaVidaPdfController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\PerfilEstudianteController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\RepositorioController;
use App\Http\Controllers\Seguimiento\DocumentoController;
use App\Http\Controllers\Seguimiento\EvaluacionController;
use App\Http\Controllers\Seguimiento\EvidenciaController;
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
    // Estudiantes y docentes entran por invitación; las empresas también pueden registrarse y esperar validación.
    Route::post('auth/activar-cuenta', ActivacionController::class);
    Route::post('registro/empresa', RegistroEmpresaController::class);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('auth/yo', [SesionController::class, 'show']);
    Route::post('auth/logout', [SesionController::class, 'destroy']);
    Route::patch('auth/preferencias', [SesionController::class, 'preferencias']);

    // Notificaciones dentro de la app
    Route::get('notificaciones', [NotificacionController::class, 'index']);
    Route::get('notificaciones/no-leidas', [NotificacionController::class, 'noLeidas']);
    Route::patch('notificaciones/leidas', [NotificacionController::class, 'marcarTodas']);
    Route::patch('notificaciones/{id}/leida', [NotificacionController::class, 'marcarLeida'])->whereUuid('id');

    // Reportes de problemas: cualquier usuario reporta y sigue los suyos
    Route::get('reportes', [ReporteController::class, 'index']);
    Route::post('reportes', [ReporteController::class, 'store'])->middleware('throttle:10,1');

    // Seguimiento de una vinculación (cada acción valida permisos con VinculacionPolicy)
    Route::get('vinculaciones/{vinculacion}', [DocumentoController::class, 'vinculacion']);
    Route::get('vinculaciones/{vinculacion}/documentos', [DocumentoController::class, 'index']);
    Route::post('vinculaciones/{vinculacion}/documentos', [DocumentoController::class, 'store']);
    Route::get('documentos/{documento}/archivo', [DocumentoController::class, 'descargar']);
    Route::patch('documentos/{documento}/revision', [DocumentoController::class, 'revisar']);
    Route::get('vinculaciones/{vinculacion}/carta-bienvenida.pdf', [DocumentoController::class, 'cartaBienvenida']);
    Route::get('vinculaciones/{vinculacion}/evidencias', [EvidenciaController::class, 'index']);
    Route::post('vinculaciones/{vinculacion}/evidencias', [EvidenciaController::class, 'store']);
    Route::post('vinculaciones/{vinculacion}/evidencias/aprobacion', [EvidenciaController::class, 'aprobarPendientes']);
    Route::post('evidencias/{evidencia}/correccion', [EvidenciaController::class, 'corregir']);
    Route::patch('evidencias/{evidencia}/revision', [EvidenciaController::class, 'revisar']);
    Route::get('evidencias/{evidencia}/archivo', [EvidenciaController::class, 'descargar']);
    Route::get('vinculaciones/{vinculacion}/evaluaciones', [EvaluacionController::class, 'index']);
    Route::post('vinculaciones/{vinculacion}/evaluaciones', [EvaluacionController::class, 'store']);
    Route::get('vinculaciones/{vinculacion}/reportes', [ReporteController::class, 'deVinculacion']);

    // Vista previa del repositorio de GitHub de un proyecto (permiso: ProyectoPolicy)
    Route::get('proyectos/{proyecto}/repositorio', RepositorioController::class)->middleware('throttle:60,1');

    // Foto de la hoja de vida (el propio estudiante y quien consulta perfiles)
    Route::get('estudiantes/{estudiante}/foto', [FotoEstudianteController::class, 'show']);

    // Perfiles de estudiantes para quien evalúa candidatos
    Route::middleware('rol:EMPRESA,DOCENTE,ADMIN')->group(function () {
        Route::get('estudiantes', [PerfilEstudianteController::class, 'index']);
        Route::get('estudiantes/{estudiante}', [PerfilEstudianteController::class, 'show']);
        Route::get('estudiantes/{estudiante}/hoja-vida.pdf', [HojaVidaPdfController::class, 'deEstudiante']);
    });

    Route::prefix('estudiante')->middleware('rol:ESTUDIANTE')->group(function () {
        Route::get('perfil', [Estudiante\PerfilController::class, 'show']);
        Route::put('perfil', [Estudiante\PerfilController::class, 'update']);
        Route::get('hoja-vida.pdf', [HojaVidaPdfController::class, 'propia']);
        Route::post('foto', [FotoEstudianteController::class, 'store'])->middleware('throttle:10,1');
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
        Route::post('vinculaciones/{vinculacion}/finalizacion', [Docente\VinculacionController::class, 'finalizar']);
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
        Route::post('usuarios/{usuario}/invitacion', [Admin\UsuarioController::class, 'invitar'])->middleware('throttle:10,1');
        Route::post('estudiantes', [Admin\EstudianteController::class, 'store']);
        Route::patch('estudiantes/{estudiante}', [Admin\EstudianteController::class, 'update']);
        Route::get('empresas', [Admin\EmpresaController::class, 'index']);
        Route::post('empresas', [Admin\EmpresaController::class, 'store']);
        Route::patch('empresas/{empresa}', [Admin\EmpresaController::class, 'update']);
        Route::patch('empresas/{empresa}/validacion', [Admin\EmpresaController::class, 'validar']);
        Route::get('reglas', [Admin\ReglaElegibilidadController::class, 'index']);
        Route::post('reglas', [Admin\ReglaElegibilidadController::class, 'store']);
        Route::patch('programas/{programa}', [Admin\ProgramaController::class, 'update']);
        Route::get('periodos', [Admin\PeriodoController::class, 'index']);
        Route::post('periodos', [Admin\PeriodoController::class, 'store']);
        Route::post('periodos/{periodo}/cierre', [Admin\PeriodoController::class, 'cerrar']);
        Route::get('reportes', [Admin\ReporteController::class, 'index']);
        Route::patch('reportes/{reporte}', [Admin\ReporteController::class, 'update']);
        Route::get('informes', [Admin\InformeController::class, 'show']);
        Route::get('informes/exportar', [Admin\InformeController::class, 'exportar']);
        Route::get('auditoria', [Admin\AuditoriaController::class, 'index']);
    });
});
