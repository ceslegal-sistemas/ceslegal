<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AsistentePanelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint interno, servidor-a-servidor: lo consulta el AI Agent de n8n
 * (tool HTTP) que atiende la burbuja de Chatwoot embebida en el panel
 * 'empresa'. Protegido por un secreto compartido (X-Internal-Secret), no
 * por sesión - no hay usuario navegando aquí, es n8n preguntando "¿qué
 * puede ver este usuario?" antes de que el LLM responda.
 */
class AsistentePanelContextoController extends Controller
{
    public function contexto(Request $request, AsistentePanelService $servicio): JsonResponse
    {
        $secretoConfigurado = (string) config('services.asistente_panel.secret');

        // Falla cerrado: si no hay secreto configurado, nunca se compara
        // contra un header vacío (eso dejaría el endpoint abierto por
        // defecto en cualquier entorno sin ASISTENTE_PANEL_SECRET en el .env).
        if ($secretoConfigurado === '' || ! hash_equals($secretoConfigurado, (string) $request->header('X-Internal-Secret'))) {
            abort(403);
        }

        $usuario = User::find($request->query('user_id'));

        if (! $usuario) {
            abort(404);
        }

        if ($usuario->role !== 'cliente') {
            abort(403);
        }

        return response()->json($servicio->resolverContexto($usuario, $usuario->empresa));
    }
}
