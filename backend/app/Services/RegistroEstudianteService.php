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
use Illuminate\Support\Str;

/**
 * Registro de estudiantes por el docente, a partir del listado de
 * modalidades de grado:
 *
 * 1. Se crea la cuenta de quien eligió pasantía o práctica y cursa un
 *    semestre habilitado. Nadie conoce aún su contraseña.
 * 2. El docente registra el promedio. Si alcanza el mínimo, se genera una
 *    contraseña y se envía al estudiante, que debe cambiarla al primer
 *    ingreso. Si no, la cuenta queda inactiva y recibe un mensaje de ánimo.
 *    Hasta que no hay promedio no se envía ningún correo.
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
     * Registra el promedio y envía el resultado. Solo se hace una vez: el
     * correo con las credenciales no se repite.
     */
    public function registrarPromedio(Estudiante $estudiante, float $promedio, Usuario $docente): Estudiante
    {
        $password = null;

        $estudiante = DB::transaction(function () use ($estudiante, $promedio, &$password) {
            $estudiante = Estudiante::query()->lockForUpdate()->findOrFail($estudiante->id);

            if ($estudiante->resultado_enviado_en) {
                throw new ReglaNegocioException('El promedio de este estudiante ya se registró y se le envió el resultado.');
            }

            $habilitado = $this->elegibilidad->promedioSuficiente($promedio);
            $estudiante->update(['promedio' => $promedio, 'habilitado' => $habilitado, 'resultado_enviado_en' => now()]);

            if ($habilitado) {
                $password = Str::password(12, symbols: false);
                $estudiante->usuario()->update([
                    'password_hash' => bcrypt($password),
                    'debe_cambiar_password' => true,
                    'activo' => true,
                ]);
            } else {
                $estudiante->usuario()->update(['activo' => false]);
            }

            return $estudiante;
        });

        $estudiante->load('usuario', 'programa');
        Auditoria::registrar('estudiante.promedio_registrado', $estudiante, [
            'promedio' => $promedio,
            'habilitado' => $estudiante->habilitado,
            'docente_id' => $docente->id,
        ]);

        $estudiante->usuario->notify($password !== null
            ? new CredencialesAcceso($estudiante, $password)
            : new NoHabilitado($estudiante));

        return $estudiante;
    }
}
