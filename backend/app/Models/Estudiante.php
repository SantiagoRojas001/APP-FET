<?php

namespace App\Models;

use App\Enums\Ciclo;
use App\Enums\EstadoHojaVida;
use App\Enums\EstadoVinculacion;
use App\Enums\Modalidad;
use App\Policies\EstudiantePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Datos personales y académicos del estudiante o egresado.
 */
#[Table('estudiante', timestamps: false)]
#[UsePolicy(EstudiantePolicy::class)]
#[Fillable([
    'usuario_id', 'programa_id', 'codigo', 'documento', 'nombres', 'apellidos', 'telefono', 'semestre_actual',
    'es_egresado', 'disponibilidad', 'modalidad_grado', 'promedio', 'habilitado', 'resultado_enviado_en', 'registrado_por',
])]
class Estudiante extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'semestre_actual' => 'integer',
            'es_egresado' => 'boolean',
            'modalidad_grado' => Modalidad::class,
            'promedio' => 'float',
            'habilitado' => 'boolean',
            'resultado_enviado_en' => 'datetime',
        ];
    }

    /** @return Attribute<string, never> */
    protected function nombreCompleto(): Attribute
    {
        return Attribute::get(fn () => "{$this->nombres} {$this->apellidos}");
    }

    /**
     * La hoja de vida circula mientras el estudiante busca práctica. Cuando el
     * docente autoriza su vinculación, se deshabilita: no se edita ni aparece
     * en la búsqueda de talento.
     */
    public function hojaVidaHabilitada(): bool
    {
        return $this->relationLoaded('vinculacionActiva')
            ? $this->vinculacionActiva === null
            : ! $this->vinculaciones()->where('estado', EstadoVinculacion::Activa)->exists();
    }

    /** Carga la hoja de vida completa con lo que muestran el perfil y el PDF. */
    public function cargarHojaVida(): static
    {
        return $this->load([
            'usuario', 'programa', 'competencias', 'proyectos', 'vinculacionActiva',
            ...array_map(fn (string $r) => "hojaVida.{$r}", HojaVida::COMPLETA),
        ]);
    }

    /**
     * Estudiantes que las empresas pueden encontrar: habilitados, con hoja de
     * vida aprobada por el docente y sin práctica en curso.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function disponibles(Builder $query): void
    {
        $query->where('habilitado', true)
            ->where('es_egresado', false)
            ->whereHas('hojaVida', fn (Builder $h) => $h->where('estado', EstadoHojaVida::Aprobada))
            ->whereDoesntHave('vinculaciones', fn (Builder $v) => $v->where('estado', EstadoVinculacion::Activa));
    }

    /** @return BelongsTo<Usuario, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }

    /** @return BelongsTo<Programa, $this> */
    public function programa(): BelongsTo
    {
        return $this->belongsTo(Programa::class);
    }

    /** @return HasOne<HojaVida, $this> */
    public function hojaVida(): HasOne
    {
        return $this->hasOne(HojaVida::class);
    }

    /** @return BelongsToMany<Competencia, $this> */
    public function competencias(): BelongsToMany
    {
        return $this->belongsToMany(Competencia::class, 'estudiante_competencia')->withPivot('nivel');
    }

    /** @return HasMany<Proyecto, $this> */
    public function proyectos(): HasMany
    {
        return $this->hasMany(Proyecto::class);
    }

    /** Ciclo académico según el semestre que cursa. */
    public function ciclo(): Ciclo
    {
        return Ciclo::deSemestre($this->semestre_actual);
    }

    /**
     * Estudiantes de un programa; sin programa (administración o docente sin
     * programa asignado), todos.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function delPrograma(Builder $query, ?int $programaId): void
    {
        $query->when($programaId, fn (Builder $q, int $id) => $q->where('estudiante.programa_id', $id));
    }

    /** @param  Builder<self>  $query */
    #[Scope]
    protected function delCiclo(Builder $query, Ciclo $ciclo): void
    {
        $query->whereBetween('semestre_actual', $ciclo->semestres());
    }

    /** @return HasMany<Cita, $this> */
    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class)->orderByDesc('fecha_hora');
    }

    /** @return HasMany<Recomendacion, $this> */
    public function recomendaciones(): HasMany
    {
        return $this->hasMany(Recomendacion::class);
    }

    /** @return HasMany<Vinculacion, $this> */
    public function vinculaciones(): HasMany
    {
        return $this->hasMany(Vinculacion::class);
    }

    /** @return HasOne<Vinculacion, $this> */
    public function vinculacionActiva(): HasOne
    {
        return $this->hasOne(Vinculacion::class)
            ->where('estado', EstadoVinculacion::Activa)
            ->latestOfMany('fecha_inicio');
    }
}
