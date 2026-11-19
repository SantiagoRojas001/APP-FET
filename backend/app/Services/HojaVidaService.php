<?php

namespace App\Services;

use App\Enums\EstadoHojaVida;
use App\Enums\Rol;
use App\Enums\TipoReferencia;
use App\Exceptions\ReglaNegocioException;
use App\Http\Resources\EstudianteResource;
use App\Models\Estudiante;
use App\Models\HojaVida;
use App\Models\Usuario;
use App\Notifications\HojaVidaPorRevisar;
use App\Notifications\HojaVidaRevisada;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

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
            ['seccion' => 'Cédula', 'completa' => filled($estudiante->documento) && filled($estudiante->lugar_expedicion) && filled($hoja?->cedula_frente_url) && filled($hoja?->cedula_reverso_url)],
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
     * Al guardar, la hoja de vida completa se envía sola a revisión docente.
     * Incompleta queda en borrador.
     */
    public function enviarSiCompleta(Estudiante $estudiante): bool
    {
        $hoja = $estudiante->hojaVida()->firstOrCreate();

        if (! app(ElegibilidadService::class)->habilitadoParaPractica($estudiante)
            || ! $estudiante->hojaVidaHabilitada()
            || in_array($hoja->estado, [EstadoHojaVida::EnRevision, EstadoHojaVida::Aprobada], true)
            || $this->porcentaje($estudiante) < 100) {
            return false;
        }

        $hoja->update([
            'estado' => EstadoHojaVida::EnRevision,
            'enviada_en' => now(),
        ]);
        Auditoria::registrar('hoja_vida.enviada', $estudiante);

        $estudiante->loadMissing('programa');
        Notification::send(
            $estudiante->tutor_id ? [$estudiante->tutor] : Usuario::delPrograma(Rol::Docente, $estudiante->programa_id),
            new HojaVidaPorRevisar($estudiante),
        );

        return true;
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
            // La versión nueva ya es la aprobada: la copia anterior sobra.
            'version_aprobada' => $aprobar ? null : $hoja->version_aprobada,
        ]);
        if ($aprobar) {
            Storage::disk('local')->deleteDirectory($this->carpetaAprobada($hoja));
        }
        Auditoria::registrar($aprobar ? 'hoja_vida.aprobada' : 'hoja_vida.observada', $estudiante, array_filter(['observaciones' => $observaciones]));

        $estudiante->loadMissing('usuario');
        $estudiante->usuario->notify(new HojaVidaRevisada($hoja));

        return $hoja;
    }

    /**
     * Antes de que el estudiante cambie su hoja de vida: en revisión no se
     * puede editar. Si estaba aprobada, se conserva una copia que las empresas
     * siguen viendo hasta que el docente apruebe la versión nueva.
     */
    public function antesDeEditar(Estudiante $estudiante): void
    {
        $hoja = $estudiante->hojaVida()->firstOrCreate();

        if ($hoja->estado === EstadoHojaVida::EnRevision) {
            throw new ReglaNegocioException('Tu hoja de vida está en revisión docente. Podrás editarla cuando el docente responda.');
        }

        if ($hoja->estado === EstadoHojaVida::Aprobada) {
            $hoja->update(['estado' => EstadoHojaVida::Borrador, 'version_aprobada' => $this->copiaAprobada($estudiante, $hoja)]);
        }
    }

    /** @return array<string, mixed> Datos, foto, firma y PDF de la hoja de vida tal como se aprobó. */
    private function copiaAprobada(Estudiante $estudiante, HojaVida $hoja): array
    {
        $disco = Storage::disk('local');
        $carpeta = $this->carpetaAprobada($hoja);
        $disco->deleteDirectory($carpeta);

        $copiar = function (?string $ruta, string $nombre) use ($disco, $carpeta): ?string {
            if (! $ruta || ! $disco->exists($ruta)) {
                return null;
            }
            $destino = "{$carpeta}/{$nombre}.".pathinfo($ruta, PATHINFO_EXTENSION);
            $disco->copy($ruta, $destino);

            return $destino;
        };

        $pdf = "{$carpeta}/hoja-de-vida.pdf";
        $disco->put($pdf, app(HojaVidaPdf::class)->contenido($estudiante, false));

        // Sin usuario en la petición, el recurso omite los datos que las empresas no ven.
        $datos = (new EstudianteResource($estudiante->cargarHojaVida()))->resolve(Request::create('/'));

        return [
            'perfil' => array_intersect_key($datos, array_flip(['telefono', 'disponibilidad', 'hoja_vida', 'competencias', 'proyectos'])),
            'foto' => $copiar($hoja->foto_url, 'foto'),
            'firma' => $copiar($hoja->firma_url, 'firma'),
            'pdf' => $pdf,
        ];
    }

    private function carpetaAprobada(HojaVida $hoja): string
    {
        return "hojas-aprobadas/{$hoja->id}";
    }

    public function porcentaje(Estudiante $estudiante): int
    {
        $secciones = $this->secciones($estudiante);
        $completas = count(array_filter($secciones, fn (array $s) => $s['completa']));

        return (int) round($completas / count($secciones) * 100);
    }
}
