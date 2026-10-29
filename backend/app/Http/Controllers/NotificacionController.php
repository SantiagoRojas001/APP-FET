<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificacionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Avisos dentro de la app del usuario autenticado.
 */
class NotificacionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $usuario = $request->user();

        return response()->json([
            'no_leidas' => $usuario->unreadNotifications()->count(),
            'data' => NotificacionResource::collection($usuario->notifications()->limit(50)->get()),
        ]);
    }

    public function noLeidas(Request $request): JsonResponse
    {
        return response()->json(['no_leidas' => $request->user()->unreadNotifications()->count()]);
    }

    public function marcarLeida(Request $request, string $id): Response
    {
        $request->user()->notifications()->findOrFail($id)->markAsRead();

        return response()->noContent();
    }

    public function marcarTodas(Request $request): Response
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->noContent();
    }
}
