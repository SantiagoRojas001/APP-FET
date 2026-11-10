<?php

namespace App\Models;

use App\Enums\EstadoSolicitudConvenio;
use App\Enums\Modalidad;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Solicitud de convenio de una empresa que aún no lo tiene. No crea una
 * cuenta: la FET le envía el convenio y la empresa vuelve a registrarse con él.
 */
#[Table('solicitud_convenio', timestamps: false)]
#[Fillable([
    'nit', 'razon_social', 'sector', 'correo', 'contacto_nombre', 'contacto_telefono', 'modalidad_interes',
    'representante_nombre', 'representante_documento', 'firma_url', 'estado', 'observacion', 'convenio_url',
    'atendida_por', 'atendida_en',
])]
class SolicitudConvenio extends Model
{
    protected $attributes = [
        'estado' => 'PENDIENTE',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoSolicitudConvenio::class,
            'modalidad_interes' => Modalidad::class,
            'atendida_en' => 'datetime',
            'creado_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Usuario, $this> */
    public function atendidaPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'atendida_por');
    }
}
