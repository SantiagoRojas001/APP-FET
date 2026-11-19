<?php

/*
| Formatos institucionales de la práctica. `llena` es el rol que los
| diligencia (null: salen de los datos de la app) y `firmantes`, quiénes firman.
| Tipos de campo: texto, area, fecha, hora, numero, opciones y tabla.
*/

return [

    'ACTA_INICIO' => [
        'titulo' => 'Acta de inicio',
        'codigo' => 'PE-PCA-F-005',
        'version' => '1.0',
        'llena' => 'DOCENTE',
        'firmantes' => ['ESTUDIANTE', 'EMPRESA', 'DOCENTE'],
        'campos' => [
            ['id' => 'fecha', 'etiqueta' => 'Fecha de la reunión', 'tipo' => 'fecha', 'requerido' => true],
            ['id' => 'hora', 'etiqueta' => 'Hora', 'tipo' => 'hora', 'requerido' => true],
            ['id' => 'lugar', 'etiqueta' => 'Lugar', 'tipo' => 'texto', 'requerido' => true],
            ['id' => 'objetivo', 'etiqueta' => 'Objetivo de la práctica', 'tipo' => 'area', 'requerido' => true],
            ['id' => 'producto', 'etiqueta' => 'Producto a entregar', 'tipo' => 'texto', 'requerido' => true],
            ['id' => 'modo_trabajo', 'etiqueta' => 'Modo de trabajo', 'tipo' => 'area', 'requerido' => true],
            ['id' => 'recomendaciones', 'etiqueta' => 'Recomendaciones y normas de la empresa', 'tipo' => 'area', 'requerido' => false],
            ['id' => 'fecha_plan', 'etiqueta' => 'Fecha de entrega del plan de trabajo', 'tipo' => 'fecha', 'requerido' => true],
            ['id' => 'proposiciones', 'etiqueta' => 'Proposiciones y varios', 'tipo' => 'area', 'requerido' => false],
        ],
    ],

    'PLAN_TRABAJO' => [
        'titulo' => 'Plan de trabajo de convenio de práctica',
        'codigo' => 'PM-PSO-F-002',
        'version' => '01',
        'llena' => 'ESTUDIANTE',
        'firmantes' => ['ESTUDIANTE', 'EMPRESA', 'DOCENTE'],
        'campos' => [
            ['id' => 'fecha_inicio', 'etiqueta' => 'Fecha de inicio', 'tipo' => 'fecha', 'requerido' => true],
            ['id' => 'fecha_fin', 'etiqueta' => 'Fecha final', 'tipo' => 'fecha', 'requerido' => true],
            ['id' => 'actividades', 'etiqueta' => 'Cronograma', 'tipo' => 'tabla', 'requerido' => true, 'columnas' => [
                ['id' => 'competencia', 'etiqueta' => 'Competencia', 'tipo' => 'texto'],
                ['id' => 'actividad', 'etiqueta' => 'Actividad', 'tipo' => 'texto'],
                ['id' => 'fecha', 'etiqueta' => 'Fecha', 'tipo' => 'fecha'],
            ]],
        ],
    ],

    'REGISTRO_ASISTENCIA' => [
        'titulo' => 'Registro de asistencia prácticas profesionales',
        'codigo' => null,
        'version' => null,
        'llena' => null,
        'firmantes' => ['ESTUDIANTE', 'DOCENTE'],
        'campos' => [],
    ],

    'EVALUACION_CUANTITATIVA' => [
        'titulo' => 'Evaluación cuantitativa de estudiantes en prácticas profesionales',
        'codigo' => 'PM-GFO-F-015',
        'version' => '02',
        'llena' => null,
        'firmantes' => ['EMPRESA', 'DOCENTE', 'ESTUDIANTE'],
        'campos' => [],
    ],

    'EVALUACION_CONVENIO' => [
        'titulo' => 'Evaluación de convenio',
        'codigo' => 'PM-PSO-F-006',
        'version' => '01',
        'llena' => 'DOCENTE',
        'firmantes' => ['EMPRESA', 'DOCENTE'],
        'campos' => [
            ['id' => 'nombre', 'etiqueta' => 'Nombre del convenio', 'tipo' => 'texto', 'requerido' => true],
            ['id' => 'numero', 'etiqueta' => 'Número de convenio', 'tipo' => 'texto', 'requerido' => false],
            ['id' => 'fecha_firma', 'etiqueta' => 'Fecha de firma del convenio', 'tipo' => 'fecha', 'requerido' => false],
            ['id' => 'duracion', 'etiqueta' => 'Duración', 'tipo' => 'texto', 'requerido' => false],
            ['id' => 'tipo', 'etiqueta' => 'Tipo de convenio', 'tipo' => 'opciones', 'requerido' => true, 'opciones' => ['Práctica', 'Marco de Cooperación', 'Otro']],
            ['id' => 'objetivos', 'etiqueta' => 'Los objetivos de la práctica se lograron en un nivel', 'tipo' => 'opciones', 'requerido' => true, 'opciones' => ['Excelente (4)', 'Bueno (3)', 'Aceptable (2)', 'Deficiente (1)', 'No aplica']],
            ['id' => 'impacto', 'etiqueta' => 'Personas impactadas', 'tipo' => 'numero', 'requerido' => false],
            ['id' => 'producto', 'etiqueta' => 'Producto demostrable del convenio', 'tipo' => 'area', 'requerido' => false],
            ['id' => 'producto_futuro', 'etiqueta' => 'Producto que puede generar a futuro', 'tipo' => 'area', 'requerido' => false],
            ['id' => 'continuar', 'etiqueta' => '¿Debe continuar el convenio?', 'tipo' => 'opciones', 'requerido' => true, 'opciones' => ['Sí', 'No']],
            ['id' => 'observaciones', 'etiqueta' => 'Observaciones', 'tipo' => 'area', 'requerido' => false],
        ],
    ],

];
