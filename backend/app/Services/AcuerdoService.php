<?php

namespace App\Services;

use App\Enums\EstadoAcuerdo;
use App\Enums\Rol;
use App\Exceptions\ReglaNegocioException;
use App\Models\Acuerdo;
use App\Models\Empresa;
use App\Models\Usuario;
use App\Notifications\AcuerdoPorRevisar;
use App\Notifications\AcuerdoRevisado;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;

/**
 * Acuerdos (convenios) de las empresas: la empresa sube el PDF con su
 * vigencia, la administración lo aprueba o lo rechaza con un motivo y el
 * director de programa los consulta. Sin acuerdo aprobado y vigente la
 * empresa no asigna citas.
 */
class AcuerdoService
{
    /**
     * @param  array{vigencia_inicio: string, vigencia_fin: string}  $datos
     */
    public function subir(Empresa $empresa, array $datos, UploadedFile $archivo): Acuerdo
    {
        if ($empresa->acuerdos()->where('estado', EstadoAcuerdo::Pendiente)->exists()) {
            throw new ReglaNegocioException('Ya tienes un acuerdo pendiente de aprobación.');
        }

        $acuerdo = $empresa->acuerdos()->create([
            ...$datos,
            'archivo_url' => $archivo->store("acuerdos/empresa-{$empresa->id}", 'local'),
        ]);
        Auditoria::registrar('acuerdo.cargado', $acuerdo);

        $acuerdo->load('empresa');
        Notification::send(Usuario::query()->where('rol', Rol::Admin)->where('activo', true)->get(), new AcuerdoPorRevisar($acuerdo));

        return $acuerdo;
    }

    public function revisar(Acuerdo $acuerdo, Usuario $admin, bool $aprobar, ?string $observacion): Acuerdo
    {
        if ($acuerdo->estado !== EstadoAcuerdo::Pendiente) {
            throw new ReglaNegocioException("Este acuerdo ya fue revisado ({$acuerdo->estado->label()}).");
        }

        if (! $aprobar && blank($observacion)) {
            throw new ReglaNegocioException('Escribe el motivo del rechazo para que la empresa lo conozca.');
        }

        $acuerdo->update([
            'estado' => $aprobar ? EstadoAcuerdo::Aprobado : EstadoAcuerdo::Rechazado,
            'observacion' => $observacion,
            'revisado_por' => $admin->id,
            'revisado_en' => now(),
        ]);
        Auditoria::registrar($aprobar ? 'acuerdo.aprobado' : 'acuerdo.rechazado', $acuerdo, array_filter(['observacion' => $observacion]));

        $acuerdo->load('empresa.usuario');
        $acuerdo->empresa->usuario->notify(new AcuerdoRevisado($acuerdo));

        return $acuerdo;
    }
}
