<?php

namespace App\Services;

use App\Enums\EstadoRevision;
use App\Enums\Rol;
use App\Enums\TipoFormato;
use App\Exceptions\ReglaNegocioException;
use App\Models\FormatoPractica;
use App\Models\Usuario;
use App\Models\Vinculacion;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Formatos institucionales de una práctica (config/formatos.php): quien le
 * corresponde los diligencia, las partes los firman con la firma que ya
 * registraron y se descargan en PDF. Cambiar lo diligenciado borra las firmas.
 */
class FormatoService
{
    private const ETIQUETAS = ['ESTUDIANTE' => 'Practicante', 'EMPRESA' => 'Representante de la empresa', 'DOCENTE' => 'Docente tutor (asesor FET)'];

    /** @return list<array<string, mixed>> */
    public function lista(Vinculacion $vinculacion, Usuario $usuario): array
    {
        $guardados = $vinculacion->formatos()->get()->keyBy(fn (FormatoPractica $f) => $f->tipo->value);
        $rol = $this->rolFirmante($vinculacion, $usuario);

        return array_map(function (TipoFormato $tipo) use ($vinculacion, $guardados, $rol) {
            $config = $tipo->config();
            $formato = $guardados->get($tipo->value);
            $disponible = $this->disponible($vinculacion, $tipo, $formato);

            return [
                'tipo' => $tipo->value,
                'titulo' => $config['titulo'],
                'codigo' => $config['codigo'],
                'campos' => $config['campos'],
                'datos' => (object) ($formato?->datos ?? []),
                'disponible' => $disponible,
                'puede_editar' => $rol !== null && $rol === $config['llena'] && $this->abierto($vinculacion, $tipo),
                'puede_firmar' => $disponible && $rol !== null && in_array($rol, $config['firmantes'], true)
                    && ! isset($formato?->firmas[$rol]) && $this->abierto($vinculacion, $tipo),
                'firmas' => array_map(fn (string $r) => [
                    'rol' => $r,
                    'etiqueta' => self::ETIQUETAS[$r],
                    'firmada_en' => $formato?->firmas[$r]['en'] ?? null,
                ], $config['firmantes']),
                'url_pdf' => "/api/vinculaciones/{$vinculacion->id}/formatos/{$tipo->value}/pdf",
            ];
        }, TipoFormato::cases());
    }

    /** @param  array<string, mixed>  $datos */
    public function guardar(Vinculacion $vinculacion, Usuario $usuario, TipoFormato $tipo, array $datos): void
    {
        $config = $tipo->config();
        if ($config['llena'] === null || $this->rolFirmante($vinculacion, $usuario) !== $config['llena']) {
            abort(403);
        }
        $this->exigirAbierto($vinculacion, $tipo);

        $validos = validator($datos, $this->reglas($config['campos']))->validate();

        $vinculacion->formatos()->updateOrCreate(['tipo' => $tipo], ['datos' => $validos, 'firmas' => [], 'actualizado_en' => now()]);
        Auditoria::registrar('formato.guardado', $vinculacion, ['tipo' => $tipo->value]);
    }

    public function firmar(Vinculacion $vinculacion, Usuario $usuario, TipoFormato $tipo): void
    {
        $rol = $this->rolFirmante($vinculacion, $usuario);
        if ($rol === null || ! in_array($rol, $tipo->config()['firmantes'], true)) {
            abort(403);
        }
        $this->exigirAbierto($vinculacion, $tipo);

        $formato = $vinculacion->formatos()->where('tipo', $tipo)->first();
        if (! $this->disponible($vinculacion, $tipo, $formato)) {
            throw new ReglaNegocioException('Este formato todavía no tiene información para firmar.');
        }
        if ($this->firmante($vinculacion, $rol, $usuario)['imagen'] === null) {
            throw new ReglaNegocioException(match ($rol) {
                'ESTUDIANTE' => 'Dibuja tu firma en la hoja de vida para poder firmar.',
                'EMPRESA' => 'La empresa no tiene registrada la firma de su representante.',
                default => 'Registra tu nombre, documento y firma en Mi firma.',
            });
        }

        $formato ??= $vinculacion->formatos()->create(['tipo' => $tipo, 'datos' => [], 'firmas' => []]);
        $formato->update(['firmas' => [...$formato->firmas, $rol => ['en' => now()->toIso8601String(), 'usuario_id' => $usuario->id]]]);
        Auditoria::registrar('formato.firmado', $vinculacion, ['tipo' => $tipo->value, 'rol' => $rol]);
    }

    public function pdf(Vinculacion $vinculacion, TipoFormato $tipo): string
    {
        $formato = $vinculacion->formatos()->where('tipo', $tipo)->first();
        abort_unless($this->disponible($vinculacion, $tipo, $formato), 404, 'Este formato todavía no tiene información.');

        $vinculacion->loadMissing('estudiante.programa', 'estudiante.hojaVida', 'empresa.usuario', 'tutor');
        $config = $tipo->config();

        return Pdf::loadView('pdf.formato', [
            'tipo' => $tipo,
            'config' => $config,
            'vinculacion' => $vinculacion,
            'datos' => $formato?->datos ?? [],
            'semanas' => $tipo === TipoFormato::RegistroAsistencia ? $vinculacion->evidencias()->reorder('semana_inicio')->get() : collect(),
            'calificaciones' => $tipo === TipoFormato::EvaluacionCuantitativa ? $vinculacion->calificaciones()->get() : collect(),
            'firmas' => array_map(function (string $rol) use ($vinculacion, $formato) {
                $firma = $formato?->firmas[$rol] ?? null;
                $usuario = $firma ? Usuario::query()->find($firma['usuario_id']) : null;

                return [
                    ...$this->firmante($vinculacion, $rol, $usuario),
                    'etiqueta' => self::ETIQUETAS[$rol],
                    'firmada' => $firma !== null,
                ];
            }, $config['firmantes']),
            'logo' => base64_encode(file_get_contents(resource_path('img/logo-fet.png'))),
        ])->setPaper('letter')->output();
    }

    private function rolFirmante(Vinculacion $vinculacion, Usuario $usuario): ?string
    {
        return match ($usuario->rol) {
            Rol::Estudiante => $usuario->can('evaluar', $vinculacion) ? 'ESTUDIANTE' : null,
            Rol::Empresa => $usuario->can('calificar', $vinculacion) ? 'EMPRESA' : null,
            Rol::Docente => $usuario->can('calificar', $vinculacion) ? 'DOCENTE' : null,
            default => null,
        };
    }

    /** @return array{nombre: ?string, documento: ?string, imagen: ?string} */
    private function firmante(Vinculacion $vinculacion, string $rol, ?Usuario $usuario): array
    {
        return match ($rol) {
            'ESTUDIANTE' => [
                'nombre' => $vinculacion->estudiante->nombre_completo,
                'documento' => $vinculacion->estudiante->documento,
                'imagen' => $this->imagen($vinculacion->estudiante->hojaVida?->firma_url),
            ],
            'EMPRESA' => [
                'nombre' => $vinculacion->empresa->representante_nombre,
                'documento' => $vinculacion->empresa->representante_documento,
                'imagen' => $this->imagen($vinculacion->empresa->firma_representante_url),
            ],
            default => [
                'nombre' => $usuario?->nombre ?? $vinculacion->tutor?->nombre,
                'documento' => $usuario?->documento ?? $vinculacion->tutor?->documento,
                'imagen' => $usuario?->puedeFirmarCartas() ? $this->imagen($usuario->firma_url) : null,
            ],
        };
    }

    private function imagen(?string $ruta): ?string
    {
        if (! $ruta || ! Storage::disk('local')->exists($ruta)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode(Storage::disk('local')->get($ruta));
    }

    private function disponible(Vinculacion $vinculacion, TipoFormato $tipo, ?FormatoPractica $formato): bool
    {
        return match ($tipo) {
            TipoFormato::RegistroAsistencia => $vinculacion->evidencias()->where('estado', '!=', EstadoRevision::Observado)->exists(),
            TipoFormato::EvaluacionCuantitativa => $vinculacion->calificaciones()->exists(),
            default => $formato !== null && $formato->datos !== [],
        };
    }

    /** La evaluación de convenio se llena al terminar; los demás, durante la práctica. */
    private function abierto(Vinculacion $vinculacion, TipoFormato $tipo): bool
    {
        return $tipo === TipoFormato::EvaluacionConvenio
            ? $vinculacion->cumplioHoras() || ! $vinculacion->estaActiva()
            : $vinculacion->estaActiva();
    }

    private function exigirAbierto(Vinculacion $vinculacion, TipoFormato $tipo): void
    {
        if (! $this->abierto($vinculacion, $tipo)) {
            throw new ReglaNegocioException($tipo === TipoFormato::EvaluacionConvenio
                ? 'La evaluación de convenio se llena cuando se completan las horas o la práctica se cierra.'
                : 'Esta práctica ya está cerrada.');
        }
    }

    /**
     * @param  list<array<string, mixed>>  $campos
     * @return array<string, mixed>
     */
    private function reglas(array $campos): array
    {
        $reglas = [];
        foreach ($campos as $campo) {
            $base = [$campo['requerido'] ? 'required' : 'nullable'];
            if ($campo['tipo'] === 'tabla') {
                $reglas[$campo['id']] = [...$base, 'array', 'min:1', 'max:60'];
                foreach ($campo['columnas'] as $columna) {
                    $reglas["{$campo['id']}.*.{$columna['id']}"] = ['required', ...$this->reglaDeTipo($columna)];
                }
            } else {
                $reglas[$campo['id']] = [...$base, ...$this->reglaDeTipo($campo)];
            }
        }

        return $reglas;
    }

    /**
     * @param  array<string, mixed>  $campo
     * @return list<mixed>
     */
    private function reglaDeTipo(array $campo): array
    {
        return match ($campo['tipo']) {
            'fecha' => ['date'],
            'hora' => ['date_format:H:i'],
            'numero' => ['integer', 'min:0', 'max:100000'],
            'opciones' => [Rule::in($campo['opciones'])],
            'area' => ['string', 'max:3000'],
            default => ['string', 'max:300'],
        };
    }
}
