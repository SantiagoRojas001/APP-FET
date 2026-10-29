<?php

return [

    /*
    | Dominio del correo institucional. Solo los estudiantes y docentes con
    | correo de este dominio pueden registrarse con esos roles.
    */
    'dominio_correo' => env('FET_DOMINIO_CORREO', 'fet.edu.co'),

    /*
    | Dirección pública del frontend. Se usa para armar los enlaces de los
    | correos (por ejemplo, el de restablecer la contraseña).
    */
    'url_frontend' => env('FRONTEND_URL', 'http://localhost:5173'),

    /*
    | Versión de la política de tratamiento de datos personales (Ley 1581 de
    | 2012) que el usuario acepta al registrarse.
    */
    'version_politica' => env('FET_VERSION_POLITICA', '2026-1'),

    /*
    | Documentos de seguimiento: tamaño máximo en kilobytes y extensiones.
    */
    'documentos' => [
        'max_kb' => 10240,
        'extensiones' => ['pdf'],
    ],

    /*
    | Soporte opcional de las evidencias semanales de horas (PDF o foto).
    */
    'evidencias' => [
        'max_kb' => 10240,
        'extensiones' => ['pdf', 'jpg', 'jpeg', 'png'],
    ],

    /*
    | Opciones de los campos de la hoja de vida (formato PA-GTH-F-10).
    */
    'hoja_vida' => [
        'estado_civil' => ['Soltero(a)', 'Casado(a)', 'Unión libre', 'Separado(a)', 'Viudo(a)'],
        'rh' => ['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-'],
        'dedicacion' => ['Tiempo completo', 'Medio tiempo', 'Por horas'],
        'modalidad_estudio' => ['Presencial', 'Virtual', 'A distancia', 'Híbrida'],
        'foto_max_kb' => 2048,
    ],

    /*
    | Reportes de problemas ligados a una práctica a partir de los cuales el
    | docente puede finalizarla anticipadamente.
    */
    'reportes_para_finalizar' => 3,

];
