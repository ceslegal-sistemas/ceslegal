<?php

namespace App\Filament\Admin\Resources\DiligenciaDescargoResource\Pages;

use App\Filament\Admin\Resources\DiligenciaDescargoResource;
use App\Services\IADescargoService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;

class CreateDiligenciaDescargo extends CreateRecord
{
    protected static string $resource = DiligenciaDescargoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Para diligencias virtuales, fecha_diligencia = fecha_acceso_permitida
        if (($data['lugar_diligencia'] ?? '') === 'virtual' && empty($data['fecha_diligencia'])) {
            $data['fecha_diligencia'] = $data['fecha_acceso_permitida'] ?? now();
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $diligencia = $this->record;

        // Generar token de acceso
        $diligencia->generarTokenAcceso();

        // Generar preguntas (estándar + IA + cierre) si no existen
        if ($diligencia->preguntas()->count() === 0) {
            try {
                $iaService = new IADescargoService();
                $iaService->generarPreguntasCompletas($diligencia, 2);
            } catch (\Exception $e) {
                Log::warning('No se pudieron generar preguntas IA al crear diligencia', [
                    'diligencia_id' => $diligencia->id,
                    'error'         => $e->getMessage(),
                ]);

                Notification::make()
                    ->warning()
                    ->title('Preguntas generadas sin IA')
                    ->body('Se crearon las preguntas estándar. Las preguntas con IA no pudieron generarse.')
                    ->send();
            }
        }
    }
}
