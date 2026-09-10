<?php

namespace App\Services;

use App\Models\DocumentoLegal;
use App\Models\Empresa;
use App\Models\ProcesoDisciplinario;
use App\Models\SolicitudContrato;
use App\Models\SugerenciaActualizacionRit;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AsistentePanelService
{
    public function responder(User $usuario, string $conversationId, string $mensaje): string
    {
        $mensajeError = 'No pude responder en este momento. Intenta de nuevo en unos minutos.';

        $payload = [
            'conversation_id' => $conversationId,
            'message' => $mensaje,
            'contexto' => $this->resolverContexto($usuario, $usuario->empresa),
        ];

        try {
            $response = Http::timeout(20)
                ->withHeaders(['X-Internal-Secret' => config('services.asistente_panel.secret')])
                ->post(config('services.asistente_panel.webhook_url'), $payload);

            if (! $response->successful()) {
                Log::warning('AsistentePanelService: respuesta no exitosa de n8n', ['status' => $response->status()]);
                return $mensajeError;
            }

            return $response->json('reply') ?? $mensajeError;
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning('AsistentePanelService: no se pudo conectar con n8n', ['error' => $e->getMessage()]);
            return $mensajeError;
        }
    }
    public function resolverContexto(User $usuario, ?Empresa $empresa): array
    {
        if (! $empresa) {
            return [];
        }

        return [
            'empresa_nombre' => $empresa->nombre_completo,
            'rit' => $this->resolverContextoRit($empresa),
            'procesos_disciplinarios_abiertos' => ProcesoDisciplinario::where('empresa_id', $empresa->id)
                ->whereNotIn('estado', ['cerrado', 'archivado'])
                ->count(),
            'numero_empleados' => $empresa->numero_empleados,
            // Mismo chequeo de permiso que dashboard.blade.php:37-39 - sin esto,
            // un cliente al que se le quitó "Gestión de Contratos" seguiría
            // recibiendo este número aunque el Dashboard ya no se lo muestre.
            'contratos_por_vencer' => $usuario->can('view_any_solicitud::contrato')
                ? SolicitudContrato::where('empresa_id', $empresa->id)
                    ->where('tipo_contrato', 'Contrato a Término Fijo')
                    ->whereNull('decision_no_renovacion_en')
                    ->where('requiere_revision_manual_renovacion', false)
                    ->whereBetween('fecha_fin_contrato', [now()->startOfDay(), now()->addDays(45)->endOfDay()])
                    ->count()
                : null,
        ];
    }

    private function resolverContextoRit(Empresa $empresa): array
    {
        $rit = $empresa->reglamentoInterno;

        if (! $rit) {
            return ['tiene_rit' => false];
        }

        return [
            'tiene_rit' => true,
            'nombre' => $rit->nombre,
            'actualizado_en' => $rit->updated_at->toDateString(),
            'sugerencias_pendientes' => SugerenciaActualizacionRit::where('reglamento_interno_id', $rit->id)
                ->where('estado', 'pendiente')
                ->whereHas('documentoLegal', fn($q) => $q->where('activo', true))
                ->count(),
        ];
    }
}
