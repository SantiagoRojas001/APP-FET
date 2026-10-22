<?php

namespace App\Services;

use App\Models\Estudiante;

/**
 * Mide qué tan completa está la hoja de vida en el formato institucional.
 */
class HojaVidaService
{
    /**
     * @return list<array{seccion: string, completa: bool}>
     */
    public function secciones(Estudiante $estudiante): array
    {
        $estudiante->loadMissing('hojaVida', 'competencias', 'proyectos');
        $hoja = $estudiante->hojaVida;

        return [
            ['seccion' => 'Datos de contacto', 'completa' => filled($estudiante->telefono) && filled($estudiante->disponibilidad)],
            ['seccion' => 'Perfil profesional', 'completa' => filled($hoja?->perfil_profesional)],
            ['seccion' => 'Formación académica', 'completa' => filled($hoja?->formacion)],
            ['seccion' => 'Competencias', 'completa' => $estudiante->competencias->isNotEmpty()],
            ['seccion' => 'Repositorio de proyectos', 'completa' => $estudiante->proyectos->isNotEmpty()],
        ];
    }

    public function porcentaje(Estudiante $estudiante): int
    {
        $secciones = $this->secciones($estudiante);
        $completas = count(array_filter($secciones, fn (array $s) => $s['completa']));

        return (int) round($completas / count($secciones) * 100);
    }
}
