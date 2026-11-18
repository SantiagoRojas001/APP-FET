<?php

namespace App\Services;

use App\Enums\ParteCalificacion;
use App\Exceptions\ReglaNegocioException;
use App\Models\Calificacion;
use App\Models\Usuario;
use App\Models\Vinculacion;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * Evaluación cuantitativa en dos momentos. La empresa califica todos sus
 * ítems; el docente, los ítems cognitivos que decida evaluar. Una vez enviada
 * no se cambia. La nota final se definirá cuando la FET confirme su fórmula.
 */
class CalificacionService
{
    /** @return list<array{id: string, etiqueta: string, peso: int, items: list<array{id: string, texto: string}>}> */
    public function categorias(ParteCalificacion $parte): array
    {
        $categorias = [];
        foreach (config('calificacion.categorias') as $id => $categoria) {
            if ($categoria['parte'] === $parte->value) {
                $categorias[] = [
                    'id' => $id,
                    'etiqueta' => $categoria['etiqueta'],
                    'peso' => $categoria['peso'],
                    'items' => array_map(
                        fn (string $item, string $texto) => ['id' => $item, 'texto' => $texto],
                        array_keys($categoria['items']),
                        $categoria['items'],
                    ),
                ];
            }
        }

        return $categorias;
    }

    public function inicioPrimerMomento(Vinculacion $vinculacion): CarbonInterface
    {
        return $vinculacion->fecha_inicio->copy()->addWeeks(config('calificacion.semana_primer_momento') - 1);
    }

    public function habilitado(Vinculacion $vinculacion, int $momento): bool
    {
        return $vinculacion->estaActiva() && ($momento === 1
            ? ! today()->lt($this->inicioPrimerMomento($vinculacion))
            : $vinculacion->cumplioHoras());
    }

    /** Las dos partes calificaron los dos momentos. */
    public function completas(Vinculacion $vinculacion): bool
    {
        return $vinculacion->calificaciones()->count() === 2 * count(ParteCalificacion::cases());
    }

    /** @param  array<string, mixed>  $puntajes  Puntaje por id de ítem. */
    public function registrar(Vinculacion $vinculacion, Usuario $evaluador, int $momento, array $puntajes, ?string $observaciones): Calificacion
    {
        $parte = ParteCalificacion::delRol($evaluador->rol);

        if (! $this->habilitado($vinculacion, $momento)) {
            throw new ReglaNegocioException($momento === 1
                ? 'El primer momento se califica desde la semana '.config('calificacion.semana_primer_momento').' de la práctica.'
                : 'El segundo momento se califica cuando el practicante completa sus horas.');
        }

        if ($vinculacion->calificaciones()->where('momento', $momento)->where('parte', $parte)->exists()) {
            throw new ReglaNegocioException('Ya registraste esta calificación.');
        }

        $items = collect($this->categorias($parte))->flatMap(fn (array $c) => array_map(fn (array $i) => [...$i, 'categoria' => $c['id']], $c['items']));
        $puntajes = array_filter($puntajes, fn ($valor) => filled($valor));
        $this->validar($parte, $items->pluck('id')->all(), $puntajes);

        $calificacion = $vinculacion->calificaciones()->create([
            'momento' => $momento,
            'parte' => $parte,
            'evaluador_id' => $evaluador->id,
            'observaciones' => $observaciones,
            'puntajes' => $items
                ->filter(fn (array $i) => isset($puntajes[$i['id']]))
                ->map(fn (array $i) => [...$i, 'puntaje' => (int) $puntajes[$i['id']]])
                ->values()
                ->all(),
        ]);
        Auditoria::registrar('calificacion.registrada', $vinculacion, ['momento' => $momento, 'parte' => $parte->value]);

        return $calificacion->refresh();
    }

    /**
     * @param  list<string>  $ids
     * @param  array<string, mixed>  $puntajes
     */
    private function validar(ParteCalificacion $parte, array $ids, array $puntajes): void
    {
        $errores = [];
        foreach ($ids as $id) {
            $valor = $puntajes[$id] ?? null;
            if ($valor === null) {
                if ($parte === ParteCalificacion::Empresa) {
                    $errores["puntajes.{$id}"] = ['Califica este ítem.'];
                }
            } elseif (! in_array((int) $valor, [1, 2, 3, 4, 5], true) || ! is_numeric($valor)) {
                $errores["puntajes.{$id}"] = ['Usa una nota de 1 a 5.'];
            }
        }

        if ($parte === ParteCalificacion::Docente && array_intersect($ids, array_keys($puntajes)) === []) {
            $errores['puntajes'] = ['Califica al menos un ítem.'];
        }

        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }
    }
}
