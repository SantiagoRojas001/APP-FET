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
        // Programas por ciclos propedéuticos: técnico, tecnólogo y profesional en 10 semestres.
        $programas = [
            'Ingeniería Ambiental',
            'Ingeniería de Alimentos',
            'Ingeniería de Software',
            'Ingeniería Eléctrica',
            'Administración de la Seguridad y Salud en el Trabajo',
            'Administración de Negocios',
        ];

        foreach ($programas as $nombre) {
            Programa::updateOrCreate(['nombre' => $nombre], ['total_semestres' => 10]);
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
