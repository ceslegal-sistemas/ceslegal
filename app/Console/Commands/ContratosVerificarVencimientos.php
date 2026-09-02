<?php

namespace App\Console\Commands;

use App\Models\SolicitudContrato;
use App\Services\NotificacionService;
use App\Services\PlazoContratoService;
use Illuminate\Console\Command;

/**
 * Alerta 45 días antes del vencimiento de un contrato a término fijo sin
 * decisión de renovación, y aplica la renovación automática del Art. 46 CST
 * cuando se cumple el plazo legal (30 días antes) sin que nadie haya
 * decidido. Programado diario a las 8:00 a.m. junto a terminos:actualizar
 * (ver bootstrap/app.php).
 */
class ContratosVerificarVencimientos extends Command
{
    protected $signature = 'contratos:verificar-vencimientos';

    protected $description = 'Alerta contratos a término fijo por vencer y aplica renovación automática cuando aplique';

    public function handle(PlazoContratoService $plazoService, NotificacionService $notificacionService): int
    {
        $solicitudes = SolicitudContrato::where('tipo_contrato', 'Contrato a Término Fijo')
            ->whereNotNull('fecha_fin_contrato')
            ->whereNull('decision_no_renovacion_en')
            ->where('requiere_revision_manual_renovacion', false)
            ->get();

        $notificadas = 0;
        $renovadas = 0;

        foreach ($solicitudes as $solicitud) {
            if ($plazoService->yaVencioPlazoLegalSinDecision($solicitud)) {
                $requeriaRevisionAntes = $solicitud->requiere_revision_manual_renovacion;

                $plazoService->aplicarRenovacionAutomatica($solicitud);
                $solicitud->refresh();

                if ($solicitud->requiere_revision_manual_renovacion && !$requeriaRevisionAntes) {
                    $notificacionService->notificarRevisionManualRenovacion($solicitud);
                } else {
                    $notificacionService->notificarRenovacionAutomatica($solicitud);
                }

                $renovadas++;
                continue;
            }

            if ($plazoService->estaEnVentanaDeAlerta($solicitud) && empty($solicitud->notificado_vencimiento_en)) {
                $notificacionService->notificarContratoPorVencer($solicitud);
                $solicitud->forceFill(['notificado_vencimiento_en' => now()])->save();
                $notificadas++;
            }
        }

        $this->info("Contratos notificados: {$notificadas}. Renovados/revisados automáticamente: {$renovadas}.");

        return self::SUCCESS;
    }
}
