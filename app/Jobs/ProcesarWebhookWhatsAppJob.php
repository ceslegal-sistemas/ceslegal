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
use Illuminate\Support\Facades\Http;
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
        $this->reenviarAChatwoot();

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

    /**
     * Acuse de entrega/lectura de WhatsApp (pedido del usuario, 2026-10-27:
     * "necesito que haya un acuse de que el trabajador ya leyó el mensaje
     * enviado por whatsapp") - correlaciona el wamid que Meta reporta aquí
     * con el whatsapp_message_id guardado al enviar (ver
     * DocumentGeneratorService::enviarAvisoWhatsappTrabajador()). Mismo
     * mecanismo que el "doble check azul" nativo de WhatsApp: si el
     * trabajador tiene desactivados los acuses de lectura en su privacidad,
     * Meta nunca reporta "read" y este acuse se queda en "Entregado" - no
     * hay forma de forzarlo, igual que con los checks azules normales.
     */
    private function procesarStatus(array $status): void
    {
        $messageId = $status['id'] ?? null;
        $estadoMeta = $status['status'] ?? null;

        Log::info('WhatsApp: status recibido', [
            'message_id' => $messageId,
            'status' => $estadoMeta,
            'destinatario' => $status['recipient_id'] ?? null,
            'error' => $status['errors'][0]['title'] ?? null,
        ]);

        if (!$messageId || !$estadoMeta) {
            return;
        }

        $tracking = \App\Models\EmailTracking::where('whatsapp_message_id', $messageId)->first();
        if (!$tracking) {
            // No todos los envíos de WhatsApp quedan ligados a un
            // EmailTracking (ej. recordatorio automático) - nada que
            // actualizar, no es un error.
            return;
        }

        // Idempotente a propósito (igual que el resto de este Job): Meta
        // puede reentregar eventos desordenados, pero "leído" es el estado
        // terminal que interesa - nunca se pisa por un "delivered" tardío
        // que llegue después.
        if ($tracking->whatsapp_estado === 'leido') {
            return;
        }

        $timestamp = isset($status['timestamp'])
            ? \Carbon\Carbon::createFromTimestamp((int) $status['timestamp'])
            : now();

        match ($estadoMeta) {
            'delivered' => $tracking->update([
                'whatsapp_estado' => 'entregado',
                'whatsapp_entregado_en' => $tracking->whatsapp_entregado_en ?? $timestamp,
            ]),
            'read' => $tracking->update([
                'whatsapp_estado' => 'leido',
                'whatsapp_entregado_en' => $tracking->whatsapp_entregado_en ?? $timestamp,
                'whatsapp_leido_en' => $timestamp,
            ]),
            'failed' => $tracking->update(['whatsapp_estado' => 'fallido']),
            'sent' => $tracking->whatsapp_estado
                ? null
                : $tracking->update(['whatsapp_estado' => 'enviado']),
            default => null,
        };
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

    /**
     * Relay a Chatwoot (pedido del usuario, 2026-10-26): Meta solo permite
     * UNA url de webhook activa por app - al conectar Chatwoot, su propio
     * asistente de configuración sobrescribió la URL del webhook dentro de
     * esta misma app de Meta con la de Chatwoot, así que este proyecto
     * dejó de recibir los eventos (no se enteraba de mensajes entrantes,
     * "STOP" ni confirmaciones de entrega). La solución: Meta vuelve a
     * apuntar a ESTE webhook (único registrado), y este Job reenvía el
     * payload crudo, tal cual llegó, a la URL de Chatwoot configurada -
     * así ambos sistemas siguen recibiendo todo, sin pisarse.
     *
     * Fail-open a propósito: si el reenvío falla (Chatwoot caído, URL mal
     * configurada), el procesamiento normal de este Job sigue de todas
     * formas - nunca se pierde el procesamiento propio por un problema de
     * Chatwoot.
     */
    private function reenviarAChatwoot(): void
    {
        $url = Configuracion::obtener('whatsapp_chatwoot_webhook_url');
        if (empty($url)) {
            return;
        }

        try {
            $respuesta = Http::timeout(10)->post($url, $this->payload);

            if (!$respuesta->successful()) {
                Log::warning('WhatsApp: Chatwoot respondió con error al reenviar el webhook', [
                    'status' => $respuesta->status(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('WhatsApp: no se pudo reenviar el webhook a Chatwoot', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
