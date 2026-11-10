<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegistroEmpresaRequest;
use App\Services\RegistroEmpresaService;
use Illuminate\Http\JsonResponse;

/**
 * Registro público de empresas (/empresasRegistro). Con convenio, la empresa
 * define su contraseña y espera la validación de la FET; sin convenio, envía
 * una solicitud de convenio y se registra cuando lo reciba (ver
 * RegistroEmpresaService).
 */
class RegistroEmpresaController extends Controller
{
    public function __invoke(RegistroEmpresaRequest $request, RegistroEmpresaService $registro): JsonResponse
    {
        $datos = $request->safe()->except(['firma', 'convenio', 'con_convenio', 'acepta_politica']);

        if (! $request->boolean('con_convenio')) {
            $registro->solicitarConvenio($datos, $request->file('firma'));

            return response()->json([
                'message' => 'Recibimos tu solicitud de convenio. La FET te enviará el convenio por correo: cuando lo tengas firmado, vuelve a esta página y registra tu empresa con él.',
            ], 201);
        }

        $registro->registrar($datos, $request->file('firma'), $request->file('convenio'));

        return response()->json([
            'message' => 'Registro recibido. La FET validará tu empresa y tu convenio, y te avisará por correo para que puedas ingresar.',
        ], 201);
    }
}
