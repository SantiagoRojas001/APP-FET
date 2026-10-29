<?php

use App\Http\Controllers\AcuerdoController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\ActivacionController;
use App\Http\Controllers\Auth\RecuperacionController;
use App\Http\Controllers\Auth\RegistroEmpresaController;
use App\Http\Controllers\Auth\SesionController;
use App\Http\Controllers\CartaPresentacionController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\Director;
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
    // Docentes, directores y administradores entran por invitación; los estudiantes, con las credenciales que
    // reciben al registrar su promedio; las empresas también pueden registrarse y esperar validación.
    Route::post('auth/activar-cuenta', ActivacionController::class);
    Route::post('registro/empresa', RegistroEmpresaController::class);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('auth/yo', [SesionController::class, 'show']);
    Route::post('auth/logout', [SesionController::class, 'destroy']);
    Route::post('auth/primer-ingreso', [SesionController::class, 'primerIngreso']);

    // Lo demás exige haber cambiado la contraseña inicial.
    Route::middleware('primer-ingreso')->group(function () {
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

        // Carta de presentación y acuerdos (permisos en cada controlador)
        Route::get('citas/{cita}/carta-presentacion.pdf', CartaPresentacionController::class);
        Route::get('acuerdos/{acuerdo}/archivo', [AcuerdoController::class, 'archivo']);

        // Vista previa del repositorio de GitHub de un proyecto (permiso: ProyectoPolicy)
        Route::get('proyectos/{proyecto}/repositorio', RepositorioController::class)->middleware('throttle:60,1');

        // Perfil, hoja de vida en PDF y foto de un estudiante (permiso: EstudiantePolicy)
        Route::get('estudiantes/{estudiante}', [PerfilEstudianteController::class, 'show']);
        Route::get('estudiantes/{estudiante}/hoja-vida.pdf', [HojaVidaPdfController::class, 'deEstudiante']);
        Route::get('estudiantes/{estudiante}/foto', [FotoEstudianteController::class, 'show']);
        Route::get('estudiantes', [PerfilEstudianteController::class, 'index'])->middleware('rol:DOCENTE,ADMIN,DIRECTOR');

        Route::prefix('estudiante')->middleware('rol:ESTUDIANTE')->group(function () {
            Route::get('perfil', [Estudiante\PerfilController::class, 'show']);
            Route::put('perfil', [Estudiante\PerfilController::class, 'update']);
            Route::post('hoja-vida/envio', [Estudiante\PerfilController::class, 'envio']);
            Route::get('hoja-vida.pdf', [HojaVidaPdfController::class, 'propia']);
            Route::post('foto', [FotoEstudianteController::class, 'store'])->middleware('throttle:10,1');
            Route::get('citas', [Estudiante\CitaController::class, 'index']);
            Route::get('vinculaciones', [Estudiante\VinculacionController::class, 'index']);
        });

        Route::prefix('docente')->middleware('rol:DOCENTE')->group(function () {
            Route::get('estudiantes', [Docente\EstudianteController::class, 'index']);
            Route::post('estudiantes', [Docente\EstudianteController::class, 'store']);
            Route::patch('estudiantes/{estudiante}/promedio', [Docente\EstudianteController::class, 'promedio']);
            Route::get('hojas-vida', [Docente\HojaVidaController::class, 'index']);
            Route::patch('hojas-vida/{estudiante}', [Docente\HojaVidaController::class, 'update']);
            Route::get('vacantes', [Docente\VacanteController::class, 'index']);
            Route::post('vacantes/{vacante}/recomendaciones', [Docente\VacanteController::class, 'recomendar']);
            Route::delete('vacantes/{vacante}/recomendaciones/{recomendacion}', [Docente\VacanteController::class, 'quitar']);
            Route::get('vinculaciones', [Docente\VinculacionController::class, 'index']);
            Route::patch('vinculaciones/{vinculacion}', [Docente\VinculacionController::class, 'update']);
            Route::post('vinculaciones/{vinculacion}/finalizacion', [Docente\VinculacionController::class, 'finalizar']);
        });

        Route::prefix('empresa')->middleware('rol:EMPRESA')->group(function () {
            Route::get('hojas-vida', [Empresa\HojaVidaController::class, 'index']);
            Route::get('citas', [Empresa\CitaController::class, 'index']);
            Route::post('citas', [Empresa\CitaController::class, 'store']);
            Route::post('citas/{cita}/cancelacion', [Empresa\CitaController::class, 'cancelar'])->whereNumber('cita');
            Route::post('citas/{cita}/decision', [Empresa\CitaController::class, 'decidir'])->whereNumber('cita');
            Route::get('acuerdos', [Empresa\AcuerdoController::class, 'index']);
            Route::post('acuerdos', [Empresa\AcuerdoController::class, 'store']);
            Route::get('vacantes', [Empresa\VacanteController::class, 'index']);
            Route::post('vacantes', [Empresa\VacanteController::class, 'store']);
            Route::patch('vacantes/{vacante}', [Empresa\VacanteController::class, 'update'])->whereNumber('vacante');
            Route::get('vinculaciones', [Empresa\VinculacionController::class, 'index']);
        });

        Route::prefix('director')->middleware('rol:DIRECTOR')->group(function () {
            Route::get('estudiantes', [Director\EstudianteController::class, 'index']);
            Route::get('cartas', [Director\CitaController::class, 'index']);
            Route::post('cartas/{cita}', [Director\CitaController::class, 'presentar'])->whereNumber('cita');
            Route::get('acuerdos', [AcuerdoController::class, 'index']);
        });

        Route::prefix('admin')->middleware('rol:ADMIN')->group(function () {
            Route::get('resumen', [Admin\UsuarioController::class, 'resumen']);
            Route::get('usuarios', [Admin\UsuarioController::class, 'index']);
            Route::post('usuarios', [Admin\UsuarioController::class, 'store']);
            Route::patch('usuarios/{usuario}', [Admin\UsuarioController::class, 'update']);
            Route::post('usuarios/{usuario}/invitacion', [Admin\UsuarioController::class, 'invitar'])->middleware('throttle:10,1');
            Route::patch('estudiantes/{estudiante}', [Admin\EstudianteController::class, 'update']);
            Route::get('empresas', [Admin\EmpresaController::class, 'index']);
            Route::post('empresas', [Admin\EmpresaController::class, 'store']);
            Route::patch('empresas/{empresa}', [Admin\EmpresaController::class, 'update']);
            Route::patch('empresas/{empresa}/validacion', [Admin\EmpresaController::class, 'validar']);
            Route::get('acuerdos', [AcuerdoController::class, 'index']);
            Route::patch('acuerdos/{acuerdo}/revision', [AcuerdoController::class, 'revisar']);
            Route::get('parametros', [Admin\ParametroController::class, 'show']);
            Route::put('parametros', [Admin\ParametroController::class, 'update']);
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
});
