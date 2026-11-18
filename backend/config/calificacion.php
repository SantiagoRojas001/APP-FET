<?php

/*
| Evaluación cuantitativa de estudiantes en prácticas (PM-GFO-F-015).
| La empresa califica lo actitudinal y lo procedimental; el docente, lo
| cognitivo, y marca solo los ítems que evalúa en cada momento.
*/

return [

    'escala' => [1 => 'Bajo', 2 => 'Regular', 3 => 'Adecuado', 4 => 'Bueno', 5 => 'Excelente'],

    'nota_minima' => 3.0,

    // El primer momento se abre en la semana 8; el segundo, al completar las horas.
    'semana_primer_momento' => 8,

    'momentos' => [1 => 40, 2 => 60],

    'categorias' => [
        'ACTITUDINAL' => [
            'etiqueta' => 'Actitudinales',
            'peso' => 25,
            'parte' => 'EMPRESA',
            'items' => [
                'a1' => 'Capacidad de cambio y adaptación',
                'a2' => 'Manejo adecuado de la comunicación verbal',
                'a3' => 'La actitud, aptitud, valores y principios del estudiante sobresalieron y logró un gran proceso de aprendizaje',
                'a4' => 'Creatividad e innovación',
                'a5' => 'Apropiación de su cargo y funciones',
                'a6' => 'Trabajo en equipo',
                'a7' => 'Manejo de relaciones interpersonales',
                'a8' => 'Respeto a directivos y grupo de trabajo',
                'a9' => 'Puntualidad y cumplimiento en las tareas asignadas',
                'a10' => 'Respeto por la cultura organizacional de la empresa',
                'a11' => 'Asistencia puntual al campo de práctica en los días asignados',
                'a12' => 'Presentación personal según el cargo o funciones a desempeñar',
                'a13' => 'Actitud de servicio',
                'a14' => 'Manejo ético de la información',
                'a15' => 'Receptividad a sugerencias y observaciones',
            ],
        ],
        'PROCEDIMENTAL' => [
            'etiqueta' => 'Procedimentales',
            'peso' => 35,
            'parte' => 'EMPRESA',
            'items' => [
                'p1' => 'Cumple y desarrolla satisfactoriamente las actividades acordes a su programa',
                'p2' => 'El estudiante se destacó por su liderazgo y comunica ideas',
                'p3' => 'Redacción, orden, ortografía y presentación de documentos inherentes a la práctica',
                'p4' => 'Uso adecuado de comandos verbales dentro de su actividad',
                'p5' => 'Aplica conocimientos adquiridos en su lugar de trabajo',
                'p6' => 'Capacidad de comunicación escrita',
                'p7' => 'Habilidades para liderar procesos',
                'p8' => 'Coordina actividades propias de su campo de acción',
                'p9' => 'Organiza, maneja recursos y gestiona un proyecto',
                'p10' => 'Capacidad para desarrollar actividades en equipo',
                'p11' => 'Realiza de manera autónoma tareas relacionadas con actividades técnicas',
                'p12' => 'Conocimiento de su cargo y funciones a desempeñar',
                'p13' => 'Capacidad para detectar y solucionar problemas relacionados con su tarea, dirigirlas y gestionarlas',
                'p14' => 'Ejercicio autónomo de actividades profesionales, con argumentación y dominio de conocimientos',
            ],
        ],
        'COGNITIVO' => [
            'etiqueta' => 'Cognitivos',
            'peso' => 40,
            'parte' => 'DOCENTE',
            'items' => [
                'c1' => 'Informe de práctica: primera entrega',
                'c2' => 'Informe de práctica: entrega final',
                'c3' => 'Proyecto de investigación: primera entrega',
                'c4' => 'Proyecto de investigación: entrega final',
                'c5' => 'Dominio de conocimientos: revisiones de tema',
                'c6' => 'Dominio de conocimientos: estudios de caso',
                'c7' => 'Dominio de conocimientos: controles de lectura',
                'c8' => 'Dominio de conocimientos: otros',
            ],
        ],
    ],

];
