<?php

namespace App\Models;

use App\Enums\Rol;
use App\Notifications\RestablecerContrasena;
use Database\Factories\UsuarioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Cuenta de acceso de una persona o empresa.
 */
#[Table('usuario', timestamps: false)]
#[Fillable(['correo', 'password_hash', 'rol', 'activo', 'tema', 'debe_cambiar_password', 'programa_id', 'tutorial_visto', 'nombre', 'documento', 'firma_url'])]
#[Hidden(['password_hash'])]
class Usuario extends Authenticatable
{
    /** @use HasFactory<UsuarioFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $attributes = [
        'debe_cambiar_password' => false,
        'tutorial_visto' => false,
        'programa_id' => null,
        'nombre' => null,
        'documento' => null,
        'firma_url' => null,
    ];

    protected $authPasswordName = 'password_hash';

    /** Las sesiones son por token: no hay columna remember_token. */
    protected $rememberTokenName = '';

    protected function casts(): array
    {
        return [
            'password_hash' => 'hashed',
            'rol' => Rol::class,
            'activo' => 'boolean',
            'debe_cambiar_password' => 'boolean',
            'tutorial_visto' => 'boolean',
            'creado_en' => 'datetime',
        ];
    }

    /** La cuenta se identifica por `correo`, no por la columna `email` de Laravel. */
    public function getEmailForPasswordReset(): string
    {
        return $this->correo;
    }

    public function routeNotificationForMail(): string
    {
        return $this->correo;
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new RestablecerContrasena($token));
    }

    public function tieneRol(Rol ...$roles): bool
    {
        return in_array($this->rol, $roles, true);
    }

    /**
     * Programa al que pertenece un docente o director: solo trabaja con los
     * estudiantes de ese programa. Sin programa asignado, con todos.
     */
    public function programaAsignado(): ?int
    {
        return $this->tieneRol(Rol::Docente, Rol::Director) ? $this->programa_id : null;
    }

    /**
     * Docentes o directores activos de un programa. Si el programa no tiene,
     * los que no tienen programa asignado (atienden a todos).
     *
     * @return Collection<int, self>
     */
    public static function delPrograma(Rol $rol, int $programaId): Collection
    {
        $activos = self::query()->where('rol', $rol)->where('activo', true);
        $delPrograma = (clone $activos)->where('programa_id', $programaId)->get();

        return $delPrograma->isNotEmpty() ? $delPrograma : $activos->whereNull('programa_id')->get();
    }

    /** El director firma las cartas de presentación con su nombre, documento y firma. */
    public function puedeFirmarCartas(): bool
    {
        return filled($this->nombre) && filled($this->documento) && filled($this->firma_url);
    }

    /** @return HasOne<Estudiante, $this> */
    public function estudiante(): HasOne
    {
        return $this->hasOne(Estudiante::class);
    }

    /** @return HasOne<Empresa, $this> */
    public function empresa(): HasOne
    {
        return $this->hasOne(Empresa::class);
    }

    /** @return HasMany<Reporte, $this> */
    public function reportes(): HasMany
    {
        return $this->hasMany(Reporte::class, 'autor_id');
    }

    /** Programa del docente o director de programa. @return BelongsTo<Programa, $this> */
    public function programa(): BelongsTo
    {
        return $this->belongsTo(Programa::class);
    }

    /** @return HasMany<ConsentimientoDatos, $this> */
    public function consentimientos(): HasMany
    {
        return $this->hasMany(ConsentimientoDatos::class);
    }
}
