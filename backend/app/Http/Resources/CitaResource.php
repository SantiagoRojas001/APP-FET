<?php

namespace App\Http\Resources;

use App\Enums\EstadoCita;
use App\Models\Cita;
use Illuminate\Http\Request;

/** @mixin Cita */
class CitaResource extends Recurso
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'estado' => $this->enum($this->estado),
            'fecha_hora' => $this->fecha_hora->toIso8601String(),
            'realizada' => $this->fecha_hora->isPast(),
            'direccion' => $this->direccion,
            'indicaciones' => $this->indicaciones,
            'observacion' => $this->observacion,
            'fecha_inicio_practica' => $this->fecha_inicio_practica?->toDateString(),
            'creada_en' => $this->creada_en?->toIso8601String(),
            'decidida_en' => $this->decidida_en?->toIso8601String(),
            'presentada_en' => $this->presentada_en?->toIso8601String(),
            'vinculacion_id' => $this->vinculacion_id,
            'url_carta' => in_array($this->estado, [EstadoCita::Aceptado, EstadoCita::Presentado], true) ? "/api/citas/{$this->id}/carta-presentacion.pdf" : null,
            'empresa' => new EmpresaResource($this->whenLoaded('empresa')),
            'estudiante' => new EstudianteResource($this->whenLoaded('estudiante')),
            'vacante' => $this->whenLoaded('vacante', fn () => $this->vacante ? ['id' => $this->vacante->id, 'titulo' => $this->vacante->titulo, 'modalidad' => $this->enum($this->vacante->modalidad)] : null),
            // Docente que aprobó la hoja de vida: será el tutor de la práctica.
            'tutor' => $this->when(
                $this->relationLoaded('estudiante') && $this->estudiante->relationLoaded('hojaVida') && $this->estudiante->hojaVida?->relationLoaded('revisor'),
                fn () => $this->estudiante->hojaVida?->revisor?->correo,
            ),
            'director' => $this->whenLoaded('director', fn () => $this->director?->correo),
        ];
    }
}
