<?php

namespace App\Http\Requests\Estudiante;

use App\Enums\NivelCompetencia;
use App\Enums\NivelFormacion;
use App\Enums\TipoInvestigacion;
use App\Enums\TipoReferencia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Hoja de vida completa en el formato PA-GTH-F-10, más el perfil,
 * competencias y proyectos que ven las empresas. Las listas reemplazan a las
 * anteriores.
 */
class ActualizarPerfilRequest extends FormRequest
{
    public function rules(): array
    {
        $opciones = config('fet.hoja_vida');

        return [
            'telefono' => ['nullable', 'string', 'max:20'],
            'disponibilidad' => ['nullable', 'string', 'max:100'],
            'perfil_profesional' => ['nullable', 'string', 'max:3000'],

            'fecha_nacimiento' => ['nullable', 'date', 'before:-14 years'],
            'lugar_nacimiento' => ['nullable', 'string', 'max:100'],
            'estado_civil' => ['nullable', Rule::in($opciones['estado_civil'])],
            'lugar_residencia' => ['nullable', 'string', 'max:100'],
            'direccion' => ['nullable', 'string', 'max:150'],
            'rh' => ['nullable', Rule::in($opciones['rh'])],
            'contacto_emergencia_nombre' => ['nullable', 'string', 'max:120'],
            'contacto_emergencia_telefono' => ['nullable', 'string', 'max:20'],

            'formaciones' => ['present', 'array', 'max:15'],
            'formaciones.*.nivel' => ['required', Rule::enum(NivelFormacion::class)],
            'formaciones.*.titulo' => ['required', 'string', 'max:150'],
            'formaciones.*.institucion' => ['required', 'string', 'max:150'],
            'formaciones.*.pais_ciudad' => ['required', 'string', 'max:100'],
            'formaciones.*.fecha_grado' => ['nullable', 'date', 'before_or_equal:today'],
            'formaciones.*.modalidad' => ['nullable', Rule::in($opciones['modalidad_estudio'])],

            'cursos' => ['present', 'array', 'max:30'],
            'cursos.*.tema' => ['required', 'string', 'max:150'],
            'cursos.*.duracion_horas' => ['required', 'integer', 'min:1', 'max:5000'],
            'cursos.*.fecha' => ['required', 'date_format:Y-m', 'before_or_equal:today'],
            'cursos.*.institucion' => ['required', 'string', 'max:150'],
            'cursos.*.modalidad' => ['required', Rule::in($opciones['modalidad_estudio'])],

            'experiencias' => ['present', 'array', 'max:20'],
            'experiencias.*.cargo' => ['required', 'string', 'max:120'],
            'experiencias.*.organizacion' => ['required', 'string', 'max:120'],
            'experiencias.*.direccion' => ['nullable', 'string', 'max:150'],
            'experiencias.*.telefono' => ['nullable', 'string', 'max:20'],
            'experiencias.*.jefe_inmediato' => ['nullable', 'string', 'max:120'],
            'experiencias.*.dedicacion' => ['nullable', Rule::in($opciones['dedicacion'])],
            'experiencias.*.fecha_inicio' => ['required', 'date', 'before_or_equal:today'],
            'experiencias.*.fecha_fin' => ['nullable', 'date', 'after_or_equal:experiencias.*.fecha_inicio'],
            'experiencias.*.descripcion' => ['nullable', 'string', 'max:1000'],

            'investigaciones' => ['present', 'array', 'max:40'],
            'investigaciones.*.tipo' => ['required', Rule::enum(TipoInvestigacion::class)],
            'investigaciones.*.descripcion' => ['required', 'string', 'max:1000'],
            'investigaciones.*.periodo' => ['nullable', 'string', 'max:60'],

            'referencias' => ['present', 'array', 'max:6'],
            'referencias.*.tipo' => ['required', Rule::enum(TipoReferencia::class)],
            'referencias.*.nombre' => ['required', 'string', 'max:120'],
            'referencias.*.ocupacion' => ['required', 'string', 'max:100'],
            'referencias.*.parentesco' => ['nullable', 'string', 'max:50', 'required_if:referencias.*.tipo,'.TipoReferencia::Familiar->value],
            'referencias.*.telefono' => ['required', 'string', 'max:20'],

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
            'fecha_nacimiento' => 'fecha de nacimiento',
            'formaciones.*.titulo' => 'título obtenido',
            'formaciones.*.institucion' => 'institución',
            'formaciones.*.pais_ciudad' => 'país y ciudad',
            'formaciones.*.fecha_grado' => 'fecha de grado',
            'cursos.*.tema' => 'tema',
            'cursos.*.duracion_horas' => 'duración',
            'cursos.*.fecha' => 'fecha',
            'cursos.*.institucion' => 'institución',
            'experiencias.*.cargo' => 'cargo',
            'experiencias.*.organizacion' => 'institución o empresa',
            'experiencias.*.fecha_inicio' => 'fecha de inicio',
            'experiencias.*.fecha_fin' => 'fecha de terminación',
            'investigaciones.*.descripcion' => 'descripción',
            'referencias.*.nombre' => 'nombre completo',
            'referencias.*.ocupacion' => 'profesión u ocupación',
            'referencias.*.parentesco' => 'parentesco',
            'referencias.*.telefono' => 'teléfono',
            'competencias.*.id' => 'competencia',
            'competencias.*.nivel' => 'nivel',
            'proyectos.*.titulo' => 'título del proyecto',
            'proyectos.*.url_repositorio' => 'enlace del repositorio',
        ];
    }

    public function messages(): array
    {
        return ['fecha_nacimiento.before' => 'Revisa la fecha de nacimiento.'];
    }
}
