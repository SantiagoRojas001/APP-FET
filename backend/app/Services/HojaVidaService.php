<?php

namespace App\Services;

use App\Enums\TipoReferencia;
use App\Exceptions\ReglaNegocioException;
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

    /**
     * Publica u oculta la hoja de vida. Solo se publica completa, si el
     * estudiante está habilitado y no cursa una práctica.
     */
    public function publicar(Estudiante $estudiante, bool $publicar): void
    {
        $hoja = $estudiante->hojaVida()->firstOrCreate();

        if ($publicar) {
            if (! app(ElegibilidadService::class)->habilitadoParaPractica($estudiante)) {
                throw new ReglaNegocioException('Aún no estás habilitado para pasantías o prácticas.');
            }
            if (! $estudiante->hojaVidaHabilitada()) {
                throw new ReglaNegocioException('Tu hoja de vida está deshabilitada mientras cursas tu práctica.');
            }
            if ($this->porcentaje($estudiante) < 100) {
                throw new ReglaNegocioException('Completa todas las secciones de tu hoja de vida antes de publicarla.');
            }
        }

        $hoja->update(['publicada_en' => $publicar ? ($hoja->publicada_en ?? now()) : null]);
        Auditoria::registrar($publicar ? 'hoja_vida.publicada' : 'hoja_vida.oculta', $estudiante);
    }

    public function porcentaje(Estudiante $estudiante): int
    {
        $secciones = $this->secciones($estudiante);
        $completas = count(array_filter($secciones, fn (array $s) => $s['completa']));

        return (int) round($completas / count($secciones) * 100);
    }
}
