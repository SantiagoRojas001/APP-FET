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

];
