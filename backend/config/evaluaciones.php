<?php

/*
| Cuestionario opcional del practicante sobre la empresa
| (App\Enums\TipoEvaluacion). Cada pregunta es de opción múltiple
| (`opciones`) o abierta. Las abiertas pueden ser opcionales.
|
| Al cambiar un texto, las evaluaciones ya enviadas conservan el original:
| cada respuesta guarda la pregunta tal como se hizo.
*/

$valoracion = ['Excelente', 'Bueno', 'Aceptable', 'Deficiente'];

return [

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
