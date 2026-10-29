<?php

namespace App\Services;

use App\Enums\TipoReferencia;
use App\Models\Estudiante;

/**
 * Mide qué tan completa está la hoja de vida en el formato institucional.
 * Formación, cursos, experiencia e investigación son opcionales: en el
 * formato aparecen como «No registra».
 */
class HojaVidaService
{
    private const DATOS_PERSONALES = ['fecha_nacimiento', 'lugar_nacimiento', 'estado_civil', 'lugar_residencia', 'direccion', 'rh'];

    /**
     * @return list<array{seccion: string, completa: bool}>
     */
    public function secciones(Estudiante $estudiante): array
    {
        $estudiante->loadMissing('hojaVida.referencias', 'competencias', 'proyectos');
        $hoja = $estudiante->hojaVida;
        $referencias = $hoja?->referencias ?? collect();

        return [
            ['seccion' => 'Datos personales', 'completa' => filled($estudiante->telefono) && collect(self::DATOS_PERSONALES)->every(fn ($c) => filled($hoja?->{$c}))],
            ['seccion' => 'Foto', 'completa' => filled($hoja?->foto_url)],
            ['seccion' => 'Contacto de emergencia', 'completa' => filled($hoja?->contacto_emergencia_nombre) && filled($hoja?->contacto_emergencia_telefono)],
            ['seccion' => 'Referencias', 'completa' => $referencias->contains('tipo', TipoReferencia::Familiar) && $referencias->contains('tipo', TipoReferencia::Personal)],
            ['seccion' => 'Perfil profesional', 'completa' => filled($hoja?->perfil_profesional)],
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
