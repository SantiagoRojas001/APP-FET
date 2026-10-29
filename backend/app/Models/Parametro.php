<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Parámetro del aplicativo que define la administración (promedio mínimo y
 * semestres habilitados para pasantía o práctica).
 */
#[Table('parametro', timestamps: false)]
#[Fillable(['clave', 'valor'])]
class Parametro extends Model
{
    protected $primaryKey = 'clave';

    protected $keyType = 'string';

    public $incrementing = false;

    public static function promedioMinimo(): float
    {
        return (float) self::valor('promedio_minimo', '4.2');
    }

    /** @return list<int> */
    public static function semestresHabilitados(): array
    {
        return array_values(array_map('intval', array_filter(explode(',', self::valor('semestres_habilitados', '3,5,9')))));
    }

    public static function guardar(string $clave, string $valor): void
    {
        self::query()->updateOrCreate(['clave' => $clave], ['valor' => $valor]);
        Cache::forget("parametro.{$clave}");
    }

    private static function valor(string $clave, string $defecto): string
    {
        return Cache::remember("parametro.{$clave}", 300, fn () => self::query()->find($clave)?->valor ?? $defecto);
    }
}
