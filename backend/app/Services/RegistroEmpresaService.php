<?php

namespace App\Services;

use App\Enums\EstadoSolicitudConvenio;
use App\Enums\Rol;
use App\Exceptions\ReglaNegocioException;
use App\Models\Empresa;
use App\Models\SolicitudConvenio;
use App\Models\Usuario;
use App\Notifications\ConvenioParaRegistro;
use App\Notifications\EmpresaPorValidar;
use App\Notifications\SolicitudConvenioRechazada;
use App\Notifications\SolicitudConvenioRecibida;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Registro público de empresas (/empresasRegistro):
 *
 * - Con convenio: la empresa se registra con la firma de su representante
 *   legal y el PDF del convenio vigente. Queda pendiente: la administración
 *   la valida y aprueba el convenio.
 * - Sin convenio: envía una solicitud de convenio, también firmada, sin crear
 *   una cuenta. La administración le envía el convenio por correo y la
 *   empresa vuelve a registrarse con él.
 */
class RegistroEmpresaService
{
    private const DATOS_EMPRESA = [
        'nit', 'razon_social', 'sector', 'contacto_nombre', 'contacto_telefono', 'modalidad_interes',
        'representante_nombre', 'representante_documento',
    ];

    public function __construct(private readonly FirmaService $firmas) {}

    /**
     * @param  array<string, mixed>  $datos  datos de la empresa, correo, contraseña y vigencia del convenio
     */
    public function registrar(array $datos, UploadedFile $firma, UploadedFile $convenio): Empresa
    {
        $rutaFirma = $this->firmas->guardar($firma, 'firmas-empresas');

        $empresa = DB::transaction(function () use ($datos, $rutaFirma, $convenio) {
            $usuario = Usuario::create([
                'correo' => $datos['correo'],
                'password_hash' => $datos['password'],
                'rol' => Rol::Empresa,
            ]);
            $usuario->consentimientos()->create(['version_politica' => config('fet.version_politica')]);

            $empresa = $usuario->empresa()->create([
                ...collect($datos)->only(self::DATOS_EMPRESA)->all(),
                'firma_representante_url' => $rutaFirma,
            ]);
            $empresa->acuerdos()->create([
                'vigencia_inicio' => $datos['vigencia_inicio'],
                'vigencia_fin' => $datos['vigencia_fin'],
                'archivo_url' => $convenio->store("acuerdos/empresa-{$empresa->id}", 'local'),
            ]);

            // Si había pedido el convenio, la solicitud queda atendida con este registro.
            SolicitudConvenio::query()
                ->where('nit', $empresa->nit)
                ->whereIn('estado', [EstadoSolicitudConvenio::Pendiente, EstadoSolicitudConvenio::Enviado])
                ->update(['estado' => EstadoSolicitudConvenio::Registrada]);

            return $empresa;
        });

        Auditoria::registrar('empresa.registrada', $empresa, ['con_convenio' => true]);
        Notification::send($this->administradores(), new EmpresaPorValidar($empresa));

        return $empresa;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function solicitarConvenio(array $datos, UploadedFile $firma): SolicitudConvenio
    {
        if (Empresa::query()->where('nit', $datos['nit'])->exists()) {
            throw new ReglaNegocioException('Esta empresa ya está registrada en APP FET. Ingresa con su cuenta.');
        }
        if (SolicitudConvenio::query()->where('nit', $datos['nit'])->where('estado', EstadoSolicitudConvenio::Pendiente)->exists()) {
            throw new ReglaNegocioException('Ya recibimos una solicitud de convenio de esta empresa. Te responderemos por correo.');
        }

        $solicitud = SolicitudConvenio::create([
            ...collect($datos)->only([...self::DATOS_EMPRESA, 'correo'])->all(),
            'firma_url' => $this->firmas->guardar($firma, 'firmas-empresas'),
        ]);

        Auditoria::registrar('convenio.solicitado', $solicitud, ['nit' => $solicitud->nit]);
        Notification::send($this->administradores(), new SolicitudConvenioRecibida($solicitud));

        return $solicitud;
    }

    /** La administración envía el convenio a la empresa para que se registre con él. */
    public function enviarConvenio(SolicitudConvenio $solicitud, Usuario $admin, UploadedFile $convenio): SolicitudConvenio
    {
        $this->exigirPendiente($solicitud);

        $solicitud->update([
            'estado' => EstadoSolicitudConvenio::Enviado,
            'convenio_url' => $convenio->store('convenios-enviados', 'local'),
            'atendida_por' => $admin->id,
            'atendida_en' => now(),
        ]);
        Auditoria::registrar('convenio.enviado', $solicitud);

        Notification::route('mail', $solicitud->correo)->notify(new ConvenioParaRegistro($solicitud));

        return $solicitud;
    }

    public function rechazarSolicitud(SolicitudConvenio $solicitud, Usuario $admin, string $motivo): SolicitudConvenio
    {
        $this->exigirPendiente($solicitud);

        $solicitud->update([
            'estado' => EstadoSolicitudConvenio::Rechazada,
            'observacion' => $motivo,
            'atendida_por' => $admin->id,
            'atendida_en' => now(),
        ]);
        Auditoria::registrar('convenio.solicitud_rechazada', $solicitud, ['motivo' => $motivo]);

        Notification::route('mail', $solicitud->correo)->notify(new SolicitudConvenioRechazada($solicitud));

        return $solicitud;
    }

    private function exigirPendiente(SolicitudConvenio $solicitud): void
    {
        if ($solicitud->estado !== EstadoSolicitudConvenio::Pendiente) {
            throw new ReglaNegocioException("Esta solicitud ya fue atendida ({$solicitud->estado->label()}).");
        }
    }

    private function administradores()
    {
        return Usuario::query()->where('rol', Rol::Admin)->where('activo', true)->get();
    }
}
