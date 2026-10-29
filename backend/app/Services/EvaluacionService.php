<?php

namespace App\Services;

use App\Enums\TipoEvaluacion;
use App\Exceptions\ReglaNegocioException;
use App\Models\Evaluacion;
use App\Models\Usuario;
use App\Models\Vinculacion;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Cuestionarios de cierre (config/evaluaciones.php). Se habilitan cuando el
 * practicante completa las horas o la práctica se cierra, y cada parte
 * responde el suyo una sola vez. La evaluación de la empresa culmina la
 * práctica.
 */
class EvaluacionService
{
    public function __construct(private readonly PracticaService $practica) {}

    /**
     * @return list<array{id: string, texto: string, opciones?: list<string>, opcional?: bool}>
     */
    public function preguntas(TipoEvaluacion $tipo): array
    {
        return config("evaluaciones.{$tipo->value}");
    }

    public function habilitada(Vinculacion $vinculacion): bool
    {
        return $vinculacion->cumplioHoras() || ! $vinculacion->estaActiva();
    }

    /**
     * @param  array<string, mixed>  $respuestas  Respuesta por id de pregunta.
     */
    public function responder(Vinculacion $vinculacion, Usuario $evaluador, array $respuestas): Evaluacion
    {
        $tipo = TipoEvaluacion::delRol($evaluador->rol);

        if (! $this->habilitada($vinculacion)) {
            throw new ReglaNegocioException('La evaluación se habilita cuando el practicante completa sus horas o la práctica termina.');
        }

        if ($vinculacion->evaluaciones()->where('tipo', $tipo)->exists()) {
            throw new ReglaNegocioException('Ya respondiste esta evaluación.');
        }

        $preguntas = $this->preguntas($tipo);
        $this->validar($preguntas, $respuestas);

        $evaluacion = $vinculacion->evaluaciones()->create([
            'tipo' => $tipo,
            'evaluador_id' => $evaluador->id,
            'respuestas' => array_map(fn (array $p) => [
                'id' => $p['id'],
                'pregunta' => $p['texto'],
                'respuesta' => filled($respuestas[$p['id']] ?? null) ? trim($respuestas[$p['id']]) : null,
            ], $preguntas),
        ]);

        Auditoria::registrar('evaluacion.respondida', $vinculacion, ['tipo' => $tipo->value]);

        if ($tipo === TipoEvaluacion::EmpresaAPracticante) {
            $this->practica->culminarSiCorresponde($vinculacion);
        }

        return $evaluacion;
    }

    /**
     * Las de opción múltiple solo admiten sus opciones; las abiertas, texto.
     *
     * @param  list<array{id: string, texto: string, opciones?: list<string>, opcional?: bool}>  $preguntas
     * @param  array<string, mixed>  $respuestas
     */
    private function validar(array $preguntas, array $respuestas): void
    {
        $reglas = [];

        foreach ($preguntas as $p) {
            $reglas[$p['id']] = isset($p['opciones'])
                ? ['required', Rule::in($p['opciones'])]
                : [($p['opcional'] ?? false) ? 'nullable' : 'required', 'string', 'max:2000'];
        }

        $validador = validator($respuestas, $reglas, [
            'required' => 'Responde esta pregunta.',
            'in' => 'Elige una de las opciones.',
        ]);

        if ($validador->fails()) {
            throw ValidationException::withMessages(
                collect($validador->errors()->messages())->mapWithKeys(fn ($m, $k) => ["respuestas.{$k}" => $m])->all(),
            );
        }
    }
}
