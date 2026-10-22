<?php

namespace Database\Seeders;

use App\Models\Competencia;
use App\Models\Programa;
use Illuminate\Database\Seeder;

/**
 * Datos de referencia que la aplicación necesita en cualquier entorno:
 * programas, reglas de elegibilidad iniciales y catálogo de competencias.
 */
class CatalogoSeeder extends Seeder
{
    public function run(): void
    {
        $programas = [
            // nombre, total de semestres, semestre mínimo para práctica
            ['Ingeniería de Software', 10, 7],
            ['Tecnología en Desarrollo de Software', 6, 5],
            ['Técnica Profesional en Programación de Software', 4, 3],
        ];

        foreach ($programas as [$nombre, $total, $minimo]) {
            $programa = Programa::updateOrCreate(['nombre' => $nombre], ['total_semestres' => $total]);

            $programa->reglas()->firstOrCreate(
                ['vigente_desde' => '2026-01-01'],
                ['semestre_minimo' => $minimo],
            );
        }

        $competencias = [
            'PHP', 'Laravel', 'JavaScript', 'Vue.js', 'Python', 'Java', 'SQL y PostgreSQL', 'Git y GitHub',
            'Flutter', 'Redes y soporte', 'Análisis de datos', 'Pruebas de software', 'Inglés',
            'Trabajo en equipo', 'Comunicación asertiva',
        ];

        foreach ($competencias as $nombre) {
            Competencia::firstOrCreate(['nombre' => $nombre]);
        }
    }
}
