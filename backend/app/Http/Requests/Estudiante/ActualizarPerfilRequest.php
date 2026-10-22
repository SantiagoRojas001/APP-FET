<?php

namespace App\Http\Requests\Estudiante;

use App\Enums\NivelCompetencia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Hoja de vida completa: datos de contacto, perfil, formación, experiencias,
 * competencias y proyectos. Las listas reemplazan a las anteriores.
 */
class ActualizarPerfilRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'telefono' => ['nullable', 'string', 'max:20'],
            'disponibilidad' => ['nullable', 'string', 'max:100'],
            'perfil_profesional' => ['nullable', 'string', 'max:3000'],
            'formacion' => ['nullable', 'string', 'max:3000'],

            'experiencias' => ['present', 'array', 'max:20'],
            'experiencias.*.cargo' => ['required', 'string', 'max:120'],
            'experiencias.*.organizacion' => ['required', 'string', 'max:120'],
            'experiencias.*.fecha_inicio' => ['required', 'date', 'before_or_equal:today'],
            'experiencias.*.fecha_fin' => ['nullable', 'date', 'after_or_equal:experiencias.*.fecha_inicio'],
            'experiencias.*.descripcion' => ['nullable', 'string', 'max:1000'],

            'competencias' => ['present', 'array', 'max:30'],
            'competencias.*.id' => ['required', 'integer', 'distinct', 'exists:competencia,id'],
            'competencias.*.nivel' => ['required', Rule::enum(NivelCompetencia::class)],

            'proyectos' => ['present', 'array', 'max:20'],
            'proyectos.*.titulo' => ['required', 'string', 'max:150'],
            'proyectos.*.descripcion' => ['nullable', 'string', 'max:1000'],
            'proyectos.*.url_repositorio' => ['required', 'url:https', 'max:255'],
            'proyectos.*.semestre' => ['nullable', 'integer', 'min:1', 'max:12'],
        ];
    }

    public function attributes(): array
    {
        return [
            'experiencias.*.cargo' => 'cargo',
            'experiencias.*.organizacion' => 'organización',
            'experiencias.*.fecha_inicio' => 'fecha de inicio',
            'experiencias.*.fecha_fin' => 'fecha de fin',
            'competencias.*.id' => 'competencia',
            'competencias.*.nivel' => 'nivel',
            'proyectos.*.titulo' => 'título del proyecto',
            'proyectos.*.url_repositorio' => 'enlace del repositorio',
        ];
    }
}
