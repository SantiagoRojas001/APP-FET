<?php

namespace App\Models;

use App\Enums\Rol;
use App\Notifications\RestablecerContrasena;
use Database\Factories\UsuarioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Cuenta de acceso de una persona o empresa.
 */
#[Table('usuario', timestamps: false)]
#[Fillable(['correo', 'password_hash', 'rol', 'activo', 'tema'])]
#[Hidden(['password_hash'])]
class Usuario extends Authenticatable
{
    /** @use HasFactory<UsuarioFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $authPasswordName = 'password_hash';

    /** Las sesiones son por token: no hay columna remember_token. */
    protected $rememberTokenName = '';

    protected function casts(): array
    {
        return [
            'password_hash' => 'hashed',
            'rol' => Rol::class,
            'activo' => 'boolean',
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

    /** @return HasMany<ConsentimientoDatos, $this> */
    public function consentimientos(): HasMany
    {
        return $this->hasMany(ConsentimientoDatos::class);
    }
}
