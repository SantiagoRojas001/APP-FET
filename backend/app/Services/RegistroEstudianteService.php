<?php

namespace App\Services;

use App\Enums\Modalidad;
use App\Enums\Rol;
use App\Exceptions\ReglaNegocioException;
use App\Models\Estudiante;
use App\Models\Parametro;
use App\Models\Usuario;
use App\Notifications\CredencialesAcceso;
use App\Notifications\NoHabilitado;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Registro de estudiantes por el docente, a partir del listado de
 * modalidades de grado. Solo se guarda a quien queda habilitado:
 *
 * 1. Se crea la cuenta de quien eligió pasantía o práctica y cursa un
 *    semestre habilitado. Nadie conoce aún su contraseña. A quien cursa otro
 *    semestre no se le registra: solo recibe el correo con el motivo.
 * 2. Con el promedio (que trae el listado o escribe el docente al cargarlo):
 *    si alcanza el mínimo, se genera una contraseña y se envía al
 *    estudiante, que debe cambiarla al primer ingreso. Si no, recibe el
 *    correo con el motivo y su registro se elimina.
 */
class RegistroEstudianteService
{
    public function __construct(private readonly ElegibilidadService $elegibilidad) {}

    /**
     * @param  array{correo: string, nombres: string, apellidos: string, programa_id: int, semestre_actual: int, modalidad_grado: string, codigo?: string|null, documento?: string|null}  $datos
     */
    public function registrar(array $datos, Usuario $docente): Estudiante
    {
        if (! $this->elegibilidad->semestreHabilitado((int) $datos['semestre_actual'])) {
            $semestres = implode(', ', array_map(fn ($s) => "{$s}.º", Parametro::semestresHabilitados()));
            throw new ReglaNegocioException("Solo se registran estudiantes de los semestres habilitados ({$semestres}).");
        }

        $programa = $docente->programaAsignado();
        if ($programa && $programa !== (int) $datos['programa_id']) {
            throw new ReglaNegocioException('El estudiante es de otro programa: lo registra un docente de su programa.');
        }

        $estudiante = DB::transaction(function () use ($datos, $docente) {
            $usuario = Usuario::create([
                'correo' => $datos['correo'],
                'password_hash' => Str::password(40),
                'rol' => Rol::Estudiante,
            ]);

            $estudiante = $usuario->estudiante()->create([
                ...collect($datos)->only(['programa_id', 'nombres', 'apellidos', 'semestre_actual', 'codigo', 'documento'])->all(),
                'modalidad_grado' => Modalidad::from($datos['modalidad_grado']),
                'registrado_por' => $docente->id,
            ]);
            $estudiante->hojaVida()->create();

            return $estudiante;
        });

        Auditoria::registrar('estudiante.registrado', $estudiante, ['modalidad' => $datos['modalidad_grado']]);

        return $estudiante;
    }

    /**
     * Registra el promedio y envía el resultado. Devuelve el estudiante
     * habilitado, o null si no alcanzó el mínimo (su registro se elimina).
     * Solo se hace una vez: el correo con las credenciales no se repite.
     */
    public function registrarPromedio(Estudiante $estudiante, float $promedio, Usuario $docente): ?Estudiante
    {
        $password = null;

        $estudiante = DB::transaction(function () use ($estudiante, $promedio, &$password) {
            $estudiante = Estudiante::query()->with('usuario')->lockForUpdate()->findOrFail($estudiante->id);

            if ($estudiante->resultado_enviado_en) {
                throw new ReglaNegocioException('El promedio de este estudiante ya se registró y se le envió el resultado.');
            }

            if (! $this->elegibilidad->promedioSuficiente($promedio)) {
                $estudiante->usuario->delete(); // con la cuenta se borran el estudiante y su hoja de vida

                return $estudiante;
            }

            $estudiante->update(['promedio' => $promedio, 'habilitado' => true, 'resultado_enviado_en' => now()]);
            $password = Str::password(12, symbols: false);
            $estudiante->usuario->update([
                'password_hash' => $password,
                'debe_cambiar_password' => true,
                'activo' => true,
            ]);

            return $estudiante;
        });

        $habilitado = $password !== null;
        // Si no quedó habilitado, su registro ya no existe: la auditoría queda a nombre del docente.
        Auditoria::registrar('estudiante.promedio_registrado', $habilitado ? $estudiante : $docente, [
            'estudiante' => $estudiante->nombre_completo,
            'correo' => $estudiante->usuario->correo,
            'promedio' => $promedio,
            'habilitado' => $habilitado,
            'docente_id' => $docente->id,
        ]);

        if (! $habilitado) {
            $this->notificarNoHabilitado($estudiante->usuario->correo, [...$estudiante->only('nombres', 'apellidos', 'codigo'), 'promedio' => $promedio], NoHabilitado::POR_PROMEDIO);

            return null;
        }

        $estudiante->usuario->notify(new CredencialesAcceso($estudiante->load('programa'), $password));

        return $estudiante;
    }

    /**
     * Correo a quien no puede formar parte del programa de pasantías, a la
     * dirección del listado (no tiene cuenta en la app).
     *
     * @param  array{nombres: string, apellidos: string, codigo?: string|null, semestre?: int|null, promedio?: float|null}  $estudiante
     */
    public function notificarNoHabilitado(string $correo, array $estudiante, string $motivo): void
    {
        Notification::route('mail', $correo)->notify(new NoHabilitado($estudiante, $motivo));
    }
}
