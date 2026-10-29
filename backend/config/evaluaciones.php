<?php

/*
| Cuestionarios de cierre de la práctica, por tipo de evaluación
| (App\Enums\TipoEvaluacion). Cada pregunta es de opción múltiple
| (`opciones`) o abierta. Las abiertas pueden ser opcionales.
|
| Al cambiar un texto, las evaluaciones ya enviadas conservan el original:
| cada respuesta guarda la pregunta tal como se hizo.
*/

$valoracion = ['Excelente', 'Bueno', 'Aceptable', 'Deficiente'];

return [

    'DOCENTE_A_PRACTICANTE' => [
        ['id' => 'entregas', 'texto' => '¿El practicante entregó a tiempo los documentos de seguimiento?', 'opciones' => ['Siempre', 'Casi siempre', 'A veces', 'Nunca']],
        ['id' => 'conocimientos', 'texto' => 'Aplicación de los conocimientos del programa en la práctica', 'opciones' => $valoracion],
        ['id' => 'comunicacion', 'texto' => 'Comunicación con el docente tutor', 'opciones' => $valoracion],
        ['id' => 'informes', 'texto' => 'Calidad de los informes y del diario de campo', 'opciones' => $valoracion],
        ['id' => 'reconocimiento', 'texto' => '¿Recomienda reconocer la práctica como requisito académico?', 'opciones' => ['Sí', 'Sí, con observaciones', 'No']],
        ['id' => 'fortalezas', 'texto' => 'Principales fortalezas del practicante'],
        ['id' => 'mejoras', 'texto' => 'Aspectos que debe mejorar'],
        ['id' => 'observaciones', 'texto' => 'Observaciones adicionales', 'opcional' => true],
    ],

    'EMPRESA_A_PRACTICANTE' => [
        ['id' => 'desempeno', 'texto' => 'Desempeño técnico en las tareas asignadas', 'opciones' => $valoracion],
        ['id' => 'responsabilidad', 'texto' => 'Puntualidad y responsabilidad', 'opciones' => $valoracion],
        ['id' => 'equipo', 'texto' => 'Trabajo en equipo y comunicación', 'opciones' => $valoracion],
        ['id' => 'aprendizaje', 'texto' => 'Capacidad de aprendizaje y adaptación', 'opciones' => $valoracion],
        ['id' => 'objetivos', 'texto' => '¿El practicante cumplió los objetivos del plan de práctica?', 'opciones' => ['Totalmente', 'En su mayoría', 'Parcialmente', 'No los cumplió']],
        ['id' => 'contratacion', 'texto' => '¿Contrataría o recomendaría al practicante?', 'opciones' => ['Sí', 'Tal vez', 'No']],
        ['id' => 'logros', 'texto' => 'Logros o aportes más importantes del practicante'],
        ['id' => 'recomendaciones', 'texto' => 'Recomendaciones para la formación de la FET', 'opcional' => true],
    ],

    'PRACTICANTE_A_EMPRESA' => [
        ['id' => 'acompanamiento', 'texto' => 'Acompañamiento de tu jefe o tutor en la empresa', 'opciones' => $valoracion],
        ['id' => 'relacion', 'texto' => '¿Las actividades se relacionaron con tu programa?', 'opciones' => ['Totalmente', 'En buena parte', 'Poco', 'Nada']],
        ['id' => 'condiciones', 'texto' => 'Condiciones de trabajo (espacio, herramientas y horario)', 'opciones' => $valoracion],
        ['id' => 'aprendizaje', 'texto' => '¿Cuánto aprendiste durante la práctica?', 'opciones' => ['Mucho', 'Bastante', 'Poco', 'Nada']],
        ['id' => 'recomendaria', 'texto' => '¿Recomendarías esta empresa a otros estudiantes?', 'opciones' => ['Sí', 'Tal vez', 'No']],
        ['id' => 'destacado', 'texto' => '¿Qué fue lo mejor de tu práctica?'],
        ['id' => 'mejorar', 'texto' => '¿Qué debería mejorar la empresa?', 'opcional' => true],
    ],

];
