<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AsistentePanelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint interno, servidor-a-servidor: lo consulta la Tool del AI Agent de
 * n8n cuando detecta que el cliente pregunta por un artículo/norma concreta
 * (RIT propio, CST o Biblioteca Legal) - separado de
 * AsistentePanelContextoController porque este SOLO se invoca bajo demanda
 * (no en cada mensaje), ya que dispara una búsqueda semántica real contra
 * Gemini (mismo cupo compartido que usan Descargos y Auditoría RIT).
 */
class AsistentePanelConsultaLegalController extends Controller
{
    public function consultar(Request $request, AsistentePanelService $servicio): JsonResponse
    {
        $secretoConfigurado = (string) config('services.asistente_panel.secret');

        // Falla cerrado, mismo criterio que AsistentePanelContextoController.
        if ($secretoConfigurado === '' || ! hash_equals($secretoConfigurado, (string) $request->header('X-Internal-Secret'))) {
            abort(403);
        }

        $usuario = User::find($request->input('user_id'));

        if (! $usuario) {
            abort(404);
        }

        if ($usuario->role !== 'cliente') {
            abort(403);
        }

        $pregunta = trim((string) $request->input('pregunta'));

        if ($pregunta === '') {
            abort(422, 'El campo pregunta es obligatorio.');
        }

        // El empresa_id SIEMPRE se deriva del usuario autenticado por el
        // secreto interno, nunca de un valor que mande n8n en el payload -
        // así ninguna manipulación del request puede pedir artículos de otra
        // empresa (ver bug real ya corregido de scope multi-tenant en rutas
        // públicas por token, misma clase de riesgo).
        return response()->json($servicio->buscarArticuloLegal($usuario, $pregunta));
    }
}
