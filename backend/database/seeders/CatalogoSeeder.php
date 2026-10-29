<?php

namespace Database\Seeders;

use App\Models\Competencia;
use App\Models\Programa;
use Illuminate\Database\Seeder;

/**
 * Datos de referencia que la aplicación necesita en cualquier entorno:
 * programas y catálogo de competencias. Los parámetros de habilitación
 * (promedio mínimo y semestres) los crea su migración.
 */
class CatalogoSeeder extends Seeder
{
    public function run(): void
    {
        $programas = [
            // nombre, total de semestres
            ['Ingeniería de Software', 10],
            ['Tecnología en Desarrollo de Software', 6],
            ['Técnica Profesional en Programación de Software', 4],
        ];

        foreach ($programas as [$nombre, $total]) {
            Programa::updateOrCreate(['nombre' => $nombre], ['total_semestres' => $total]);
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
