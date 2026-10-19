<?php

namespace App\Jobs;

use App\Models\Configuracion;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\NotificacionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Procesa, fuera de la request HTTP, el payload crudo del webhook de
 * WhatsApp (ver docs/superpowers/specs/2026-10-19-whatsapp-fase1-otp-descargos-design.md,
 * sección 2). Meta puede reentregar el mismo evento más de una vez - las 3
 * ramas de abajo son idempotentes a propósito: apagar un booleano que ya
 * estaba apagado, o comparar la calidad nueva contra la última guardada
 * antes de notificar, no causa ningún efecto doble relevante.
 *
 * Nota sobre `account_update`: el nombre exacto del campo y la forma del
 * payload para el cambio de calidad varían según la versión de la Graph
 * API - esta implementación revisa tanto `field === 'account_update'` como
 * la presencia directa de `quality_rating` en el value, como red de
 * seguridad. Conviene confirmar contra un evento real una vez el webhook
 * esté dado de alta en Meta, y ajustar si el nombre del campo es distinto.
 */
class ProcesarWebhookWhatsAppJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const PALABRAS_BAJA = ['stop', 'baja', 'no quiero mas mensajes', 'cancelar', 'detener'];

    public function __construct(private readonly array $payload)
    {
    }

    public function handle(NotificacionService $notificaciones): void
    {
        foreach ($this->payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $valor = $change['value'] ?? [];

                foreach ($valor['messages'] ?? [] as $mensaje) {
                    $this->procesarMensajeEntrante($mensaje);
                }

                foreach ($valor['statuses'] ?? [] as $status) {
                    $this->procesarStatus($status);
                }

                $esCambioDeCalidad = ($change['field'] ?? null) === 'account_update'
                    || array_key_exists('quality_rating', $valor);

                if ($esCambioDeCalidad) {
                    $this->procesarCalidad($valor, $notificaciones);
                }
            }
        }
    }

    private function procesarMensajeEntrante(array $mensaje): void
    {
        $texto = mb_strtolower(trim($mensaje['text']['body'] ?? ''));
        $texto = strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);

        $esBaja = false;
        foreach (self::PALABRAS_BAJA as $palabra) {
            if ($texto === $palabra || str_contains($texto, $palabra)) {
                $esBaja = true;
                break;
            }
        }
        if (!$esBaja) {
            return;
        }

        $remitente = (string) ($mensaje['from'] ?? '');
        $ultimos10 = substr(preg_replace('/\D/', '', $remitente), -10);
        if ($ultimos10 === '' || strlen($ultimos10) < 10) {
            return;
        }

        // Sin el scope multi-tenant: este Job corre sin usuario autenticado
        // (mismo criterio que ImpugnacionPortalService/DescargoPublicoController).
        $afectados = Trabajador::withoutGlobalScope('bufeteOrEmpresa')
            ->where('telefono', $ultimos10)
            ->get();

        // Si el número coincide con más de un trabajador (ej. datos de
        // prueba compartiendo número), se respeta el STOP en todos - mejor
        // dejar de mandarle a alguien que sí lo pidió, que seguir
        // mandándole a quien no.
        foreach ($afectados as $trabajador) {
            $trabajador->update(['notificar_por_whatsapp' => false]);
        }

        Log::info('WhatsApp: opt-out procesado', [
            'remitente' => $remitente,
            'trabajadores_afectados' => $afectados->pluck('id')->all(),
        ]);
    }

    private function procesarStatus(array $status): void
    {
        Log::info('WhatsApp: status recibido', [
            'message_id' => $status['id'] ?? null,
            'status' => $status['status'] ?? null,
            'destinatario' => $status['recipient_id'] ?? null,
            'error' => $status['errors'][0]['title'] ?? null,
        ]);
    }

    private function procesarCalidad(array $valor, NotificacionService $notificaciones): void
    {
        $calidadNueva = $valor['quality_rating'] ?? $valor['current_limit'] ?? null;
        if ($calidadNueva === null) {
            return;
        }

        $calidadAnterior = Configuracion::obtener('whatsapp_calidad_actual');
        if ($calidadAnterior === $calidadNueva) {
            // Sin cambio real - no duplicar la notificación en reentregas de Meta.
            return;
        }

        Configuracion::updateOrCreate(
            ['clave' => 'whatsapp_calidad_actual'],
            [
                'valor' => $calidadNueva,
                'tipo' => 'text',
                'categoria' => 'whatsapp',
                'editable' => false,
                'descripcion' => 'Última calidad del número reportada por Meta (automático, no editar).',
            ]
        );

        foreach (User::where('role', 'super_admin')->where('active', true)->get() as $admin) {
            $notificaciones->crear(
                userId: $admin->id,
                tipo: 'whatsapp_calidad',
                titulo: 'Cambió la calidad del número de WhatsApp',
                mensaje: "Meta reporta la calidad del número como \"{$calidadNueva}\".",
                prioridad: mb_strtoupper((string) $calidadNueva) === 'RED' ? 'urgente' : 'alta',
            );
        }

        if (mb_strtoupper((string) $calidadNueva) === 'RED') {
            Configuracion::updateOrCreate(
                ['clave' => 'whatsapp_habilitado'],
                [
                    'valor' => '0',
                    'tipo' => 'boolean',
                    'categoria' => 'whatsapp',
                    'editable' => true,
                    'descripcion' => 'Activa o desactiva el envío de notificaciones por WhatsApp',
                ]
            );
            Log::warning('WhatsApp: calidad RED reportada por Meta - envíos apagados automáticamente.');
        }
    }
}
