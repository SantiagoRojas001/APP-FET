<?php

namespace App\Models;

use App\Enums\Ciclo;
use App\Enums\EstadoRevision;
use App\Enums\EstadoVinculacion;
use App\Enums\Modalidad;
use App\Policies\VinculacionPolicy;
use App\Support\Periodo;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pasantía o práctica en curso entre un estudiante y una empresa.
 */
#[Table('vinculacion', timestamps: false)]
#[Fillable([
    'estudiante_id', 'empresa_id', 'vacante_id', 'tutor_id', 'tipo', 'semestre_al_vincular',
    'fecha_inicio', 'fecha_fin', 'estado', 'reconocida_como_practica', 'observacion_final',
    'horas_cumplidas_en', 'finalizada_por',
])]
#[UsePolicy(VinculacionPolicy::class)]
class Vinculacion extends Model
{
    use HasFactory;

    protected $attributes = [
        'estado' => 'ACTIVA',
    ];

    private const SEGUIMIENTO = ['estudiante.programa', 'empresa', 'vacante', 'tutor', 'documentos', 'evaluaciones'];

    protected function casts(): array
    {
        return [
            'tipo' => Modalidad::class,
            'estado' => EstadoVinculacion::class,
            'semestre_al_vincular' => 'integer',
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'reconocida_como_practica' => 'boolean',
            'horas_cumplidas_en' => 'datetime',
        ];
    }

    public function estaActiva(): bool
    {
        return $this->estado === EstadoVinculacion::Activa;
    }

    /**
     * Carga lo que muestra el seguimiento: partes, documentos, evaluaciones,
     * horas aprobadas, evidencias por revisar y número de reportes.
     */
    public function cargarSeguimiento(): static
    {
        return $this->load(self::SEGUIMIENTO)
            ->loadSum(['evidencias as horas_aprobadas' => fn ($q) => $q->where('estado', EstadoRevision::Aprobado)], 'horas')
            ->loadCount(['reportes', 'evidencias as evidencias_por_revisar' => fn ($q) => $q->where('estado', EstadoRevision::Pendiente)]);
    }

    /** @param  Builder<self>  $query */
    #[Scope]
    protected function conSeguimiento(Builder $query): void
    {
        $query->with(self::SEGUIMIENTO)
            ->withSum(['evidencias as horas_aprobadas' => fn ($q) => $q->where('estado', EstadoRevision::Aprobado)], 'horas')
            ->withCount(['reportes', 'evidencias as evidencias_por_revisar' => fn ($q) => $q->where('estado', EstadoRevision::Pendiente)]);
    }

    /** Periodo académico de la fecha de inicio (2026-1, 2026-2). */
    public function periodo(): string
    {
        return Periodo::deFecha($this->fecha_inicio);
    }

    /** Ciclo que cursaba el estudiante al empezar: define las horas que exige la práctica. */
    public function ciclo(): Ciclo
    {
        return Ciclo::deSemestre($this->semestre_al_vincular);
    }

    public function horasRequeridas(): int
    {
        return Parametro::horasRequeridas($this->ciclo());
    }

    /** @param  Builder<self>  $query */
    #[Scope]
    protected function delPeriodo(Builder $query, string $periodo): void
    {
        [$inicio, $fin] = Periodo::rango($periodo);
        $query->whereBetween('vinculacion.fecha_inicio', [$inicio->toDateString(), $fin->toDateString()]);
    }

    public function cumplioHoras(): bool
    {
        return $this->horas_cumplidas_en !== null;
    }

    /** @return BelongsTo<Estudiante, $this> */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    /** @return BelongsTo<Empresa, $this> */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /** @return BelongsTo<Vacante, $this> */
    public function vacante(): BelongsTo
    {
        return $this->belongsTo(Vacante::class);
    }

    /** @return BelongsTo<Usuario, $this> */
    public function tutor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'tutor_id');
    }

    /** @return HasMany<DocumentoSeguimiento, $this> */
    public function documentos(): HasMany
    {
        return $this->hasMany(DocumentoSeguimiento::class)->orderByDesc('cargado_en');
    }

    /** @return BelongsTo<Usuario, $this> */
    public function finalizador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'finalizada_por');
    }

    /** @return HasMany<EvidenciaSemanal, $this> */
    public function evidencias(): HasMany
    {
        return $this->hasMany(EvidenciaSemanal::class)->orderByDesc('semana_inicio');
    }

    /** @return HasMany<Reporte, $this> */
    public function reportes(): HasMany
    {
        return $this->hasMany(Reporte::class)->orderByDesc('creado_en');
    }

    /** @return HasMany<Evaluacion, $this> */
    public function evaluaciones(): HasMany
    {
        return $this->hasMany(Evaluacion::class);
    }
}
