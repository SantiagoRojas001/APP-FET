<?php

namespace App\Services;

use App\Enums\EstadoHojaVida;
use App\Enums\Rol;
use App\Enums\TipoReferencia;
use App\Exceptions\ReglaNegocioException;
use App\Models\Estudiante;
use App\Models\HojaVida;
use App\Models\Usuario;
use App\Notifications\HojaVidaPorRevisar;
use App\Notifications\HojaVidaRevisada;
use Illuminate\Support\Facades\Notification;

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
            // La firma es obligatoria: sin ella la hoja de vida no se puede enviar a revisión.
            ['seccion' => 'Firma', 'completa' => filled($hoja?->firma_url)],
            ['seccion' => 'Contacto de emergencia', 'completa' => filled($hoja?->contacto_emergencia_nombre) && filled($hoja?->contacto_emergencia_telefono)],
            ['seccion' => 'Referencias (una familiar y una personal)', 'completa' => $referencias->contains('tipo', TipoReferencia::Familiar) && $referencias->contains('tipo', TipoReferencia::Personal)],
            ['seccion' => 'Perfil profesional', 'completa' => filled($hoja?->perfil_profesional)],
            ['seccion' => 'Competencias', 'completa' => $estudiante->competencias->isNotEmpty()],
            ['seccion' => 'Repositorio de proyectos', 'completa' => $estudiante->proyectos->isNotEmpty()],
        ];
    }

    /**
     * El estudiante envía su hoja de vida a revisión docente. Debe estar
     * completa, el estudiante habilitado y sin práctica en curso. Si tenía
     * recomendaciones, se envía de nuevo ya corregida.
     */
    public function enviar(Estudiante $estudiante): HojaVida
    {
        $hoja = $estudiante->hojaVida()->firstOrCreate();

        if (! app(ElegibilidadService::class)->habilitadoParaPractica($estudiante)) {
            throw new ReglaNegocioException('Aún no estás habilitado para pasantías o prácticas.');
        }
        if (! $estudiante->hojaVidaHabilitada()) {
            throw new ReglaNegocioException('Tu hoja de vida está deshabilitada mientras cursas tu práctica.');
        }
        if (in_array($hoja->estado, [EstadoHojaVida::EnRevision, EstadoHojaVida::Aprobada], true)) {
            throw new ReglaNegocioException("Tu hoja de vida ya está {$hoja->estado->label()}.");
        }
        if ($this->porcentaje($estudiante) < 100) {
            throw new ReglaNegocioException('Completa todas las secciones de tu hoja de vida antes de enviarla a revisión.');
        }

        $hoja->update(['estado' => EstadoHojaVida::EnRevision, 'enviada_en' => now()]);
        Auditoria::registrar('hoja_vida.enviada', $estudiante);

        $estudiante->loadMissing('programa');
        Notification::send(
            Usuario::delPrograma(Rol::Docente, $estudiante->programa_id),
            new HojaVidaPorRevisar($estudiante),
        );

        return $hoja;
    }

    /**
     * El docente aprueba la hoja de vida (desde ahí la ven las empresas) o la
     * devuelve con recomendaciones para que el estudiante la corrija.
     */
    public function revisar(Estudiante $estudiante, Usuario $docente, bool $aprobar, ?string $observaciones): HojaVida
    {
        if (! $aprobar && blank($observaciones)) {
            throw new ReglaNegocioException('Escribe las recomendaciones para que el estudiante sepa qué corregir.');
        }

        $hoja = $estudiante->hojaVida()->firstOrCreate();

        if ($hoja->estado !== EstadoHojaVida::EnRevision) {
            throw new ReglaNegocioException("Esta hoja de vida no está en revisión ({$hoja->estado->label()}).");
        }

        $hoja->update([
            'estado' => $aprobar ? EstadoHojaVida::Aprobada : EstadoHojaVida::ConObservaciones,
            'observaciones' => $aprobar ? null : $observaciones,
            'revisada_por' => $docente->id,
            'revisada_en' => now(),
        ]);
        Auditoria::registrar($aprobar ? 'hoja_vida.aprobada' : 'hoja_vida.observada', $estudiante, array_filter(['observaciones' => $observaciones]));

        $estudiante->loadMissing('usuario');
        $estudiante->usuario->notify(new HojaVidaRevisada($hoja));

        return $hoja;
    }

    /**
     * Antes de que el estudiante cambie su hoja de vida: en revisión no se
     * puede editar y, si ya estaba aprobada, vuelve a borrador y necesita una
     * nueva aprobación (deja de verse en las empresas hasta entonces).
     */
    public function antesDeEditar(Estudiante $estudiante): void
    {
        $hoja = $estudiante->hojaVida()->firstOrCreate();

        if ($hoja->estado === EstadoHojaVida::EnRevision) {
            throw new ReglaNegocioException('Tu hoja de vida está en revisión docente. Podrás editarla cuando el docente responda.');
        }

        if ($hoja->estado === EstadoHojaVida::Aprobada) {
            $hoja->update(['estado' => EstadoHojaVida::Borrador]);
        }
    }

    public function porcentaje(Estudiante $estudiante): int
    {
        $secciones = $this->secciones($estudiante);
        $completas = count(array_filter($secciones, fn (array $s) => $s['completa']));

        return (int) round($completas / count($secciones) * 100);
    }
}
