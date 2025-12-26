<?php

namespace App\Filament\Admin\Resources\ProcesoDisciplinarioResource\Pages;

use App\Filament\Admin\Resources\ProcesoDisciplinarioResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Services\DocumentGeneratorService;
use Filament\Notifications\Notification;

class CreateProcesoDisciplinario extends CreateRecord
{
    protected static string $resource = ProcesoDisciplinarioResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Para presencial/telefónico: usar el valor de hora_temp_descargos
        if (in_array($data['modalidad_descargos'] ?? '', ['presencial', 'telefonico'])) {
            if (!empty($data['hora_temp_descargos'])) {
                $data['fecha_descargos_programada'] = $data['hora_temp_descargos'];
            }
        }

        // Debug: Log para verificar qué se está guardando
        \Log::info('CREATE - Datos antes de crear proceso disciplinario:', [
            'modalidad' => $data['modalidad_descargos'] ?? 'no definido',
            'fecha_descargos_programada' => $data['fecha_descargos_programada'] ?? 'no definido',
            'hora_temp_descargos' => $data['hora_temp_descargos'] ?? 'no definido',
        ]);

        // Remover campos temporales del array de datos
        unset($data['fecha_temp_descargos']);
        unset($data['hora_temp_descargos']);
        unset($data['hora_descargos_programada']); // Este tampoco debe guardarse directamente

        return $data;
    }

    protected function afterCreate(): void
    {
        $proceso = $this->record;

        // Solo enviar citación automáticamente si:
        // 1. Tiene fecha programada
        // 2. El trabajador tiene email
        // 3. La modalidad es presencial o telefónico
        if (
            !empty($proceso->fecha_descargos_programada) &&
            !empty($proceso->trabajador->email) &&
            in_array($proceso->modalidad_descargos, ['presencial', 'telefonico'])
        ) {
            try {
                $documentService = new DocumentGeneratorService();
                $resultado = $documentService->generarYEnviarCitacion($proceso);

                if ($resultado['success']) {
                    Notification::make()
                        ->success()
                        ->title('Proceso creado y citación enviada')
                        ->body('La citación fue generada y enviada automáticamente al trabajador.')
                        ->duration(5000)
                        ->send();

                    \Log::info('Citación enviada automáticamente después de crear proceso', [
                        'proceso_id' => $proceso->id,
                        'codigo' => $proceso->codigo,
                        'modalidad' => $proceso->modalidad_descargos,
                    ]);
                } else {
                    Notification::make()
                        ->warning()
                        ->title('Proceso creado con advertencia')
                        ->body('El proceso fue creado pero hubo un error al enviar la citación: ' . $resultado['message'])
                        ->duration(8000)
                        ->send();

                    \Log::warning('Error al enviar citación automática', [
                        'proceso_id' => $proceso->id,
                        'error' => $resultado['message'],
                    ]);
                }
            } catch (\Exception $e) {
                Notification::make()
                    ->warning()
                    ->title('Proceso creado con advertencia')
                    ->body('El proceso fue creado pero hubo un error al enviar la citación automáticamente.')
                    ->duration(8000)
                    ->send();

                \Log::error('Excepción al enviar citación automática', [
                    'proceso_id' => $proceso->id,
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            // Si es virtual, mostrar notificación informativa
            if ($proceso->modalidad_descargos === 'virtual') {
                Notification::make()
                    ->info()
                    ->title('Proceso creado')
                    ->body('El proceso fue creado. Recuerde enviar la citación manualmente desde la tabla.')
                    ->duration(5000)
                    ->send();
            }
        }
    }
}
