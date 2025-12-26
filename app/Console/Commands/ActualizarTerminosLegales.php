<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\TerminoLegalService;
use App\Services\NotificacionService;
use App\Models\TerminoLegal;
use Carbon\Carbon;

class ActualizarTerminosLegales extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'terminos:actualizar';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actualiza el estado de todos los términos legales activos y notifica los próximos a vencer';

    protected TerminoLegalService $terminoLegalService;
    protected NotificacionService $notificacionService;

    public function __construct(
        TerminoLegalService $terminoLegalService,
        NotificacionService $notificacionService
    ) {
        parent::__construct();
        $this->terminoLegalService = $terminoLegalService;
        $this->notificacionService = $notificacionService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando actualización de términos legales...');

        // Actualizar todos los términos activos
        $this->terminoLegalService->actualizarTerminos();

        // Obtener términos próximos a vencer (2 días hábiles o menos)
        $terminosProximos = $this->terminoLegalService->getTerminosProximosVencer(2);

        if ($terminosProximos->count() > 0) {
            $this->warn("Se encontraron {$terminosProximos->count()} términos próximos a vencer");

            foreach ($terminosProximos as $termino) {
                $this->notificarTerminoProximo($termino);
            }
        }

        // Obtener términos vencidos
        $terminosVencidos = $this->terminoLegalService->getTerminosVencidos();

        if ($terminosVencidos->count() > 0) {
            $this->error("Se encontraron {$terminosVencidos->count()} términos vencidos");

            foreach ($terminosVencidos as $termino) {
                $this->notificarTerminoVencido($termino);
            }
        }

        $this->info('✅ Actualización completada exitosamente');

        return Command::SUCCESS;
    }

    private function notificarTerminoProximo(TerminoLegal $termino)
    {
        $diasRestantes = $this->terminoLegalService->calcularDiasHabilesRestantes(
            Carbon::parse($termino->fecha_vencimiento)
        );

        // Obtener el proceso relacionado
        $proceso = $this->obtenerProceso($termino);

        if ($proceso && isset($proceso->abogado_id)) {
            $this->notificacionService->notificarDescargosProximos($proceso, $diasRestantes);
            $this->line("  → Notificación enviada para término #{$termino->id} ({$diasRestantes} días restantes)");
        }
    }

    private function notificarTerminoVencido(TerminoLegal $termino)
    {
        // Obtener el proceso relacionado
        $proceso = $this->obtenerProceso($termino);

        if ($proceso) {
            $userId = $proceso->abogado_id ?? null;

            if ($userId) {
                $this->notificacionService->notificarTerminoVencido(
                    userId: $userId,
                    procesoTipo: $termino->proceso_tipo,
                    procesoId: $termino->proceso_id,
                    codigoProceso: $proceso->codigo,
                    terminoTipo: $termino->termino_tipo
                );

                $this->line("  → Notificación de vencimiento enviada para término #{$termino->id}");
            }
        }
    }

    private function obtenerProceso(TerminoLegal $termino)
    {
        if ($termino->proceso_tipo === 'proceso_disciplinario') {
            return \App\Models\ProcesoDisciplinario::find($termino->proceso_id);
        } elseif ($termino->proceso_tipo === 'contrato') {
            return \App\Models\SolicitudContrato::find($termino->proceso_id);
        }

        return null;
    }
}
