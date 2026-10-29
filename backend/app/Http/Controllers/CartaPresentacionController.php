<?php

namespace App\Http\Controllers;

use App\Enums\EstadoCita;
use App\Enums\Rol;
use App\Models\Cita;
use App\Services\CartaPresentacion;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Carta de presentación en PDF de una cita autorizada. La ven la FET (docentes,
 * administración y el director del programa), la empresa y el estudiante.
 */
class CartaPresentacionController extends Controller
{
    public function __invoke(Request $request, Cita $cita, CartaPresentacion $carta): Response
    {
        $usuario = $request->user();
        $cita->loadMissing('estudiante');

        abort_unless(match ($usuario->rol) {
            Rol::Admin, Rol::Docente => true,
            Rol::Director => $usuario->programa_id === $cita->estudiante->programa_id,
            Rol::Empresa => $usuario->loadMissing('empresa')->empresa?->id === $cita->empresa_id,
            Rol::Estudiante => $usuario->id === $cita->estudiante->usuario_id,
        }, 403);
        // La FET la ve desde que el docente aprueba; la empresa y el estudiante, cuando se envía.
        $disponible = $usuario->tieneRol(Rol::Admin, Rol::Docente, Rol::Director)
            ? in_array($cita->estado, [EstadoCita::Autorizado, EstadoCita::Presentado], true)
            : $cita->estado === EstadoCita::Presentado;
        abort_unless($disponible, 404, 'La carta de presentación aún no está disponible.');

        return response($carta->pdf($cita), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="carta-de-presentacion.pdf"',
        ]);
    }
}
