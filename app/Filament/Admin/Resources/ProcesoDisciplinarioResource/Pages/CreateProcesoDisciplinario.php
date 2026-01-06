<?php

namespace App\Filament\Admin\Resources\ProcesoDisciplinarioResource\Pages;

use App\Filament\Admin\Resources\ProcesoDisciplinarioResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Services\DocumentGeneratorService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class CreateProcesoDisciplinario extends CreateRecord
{
    protected static string $resource = ProcesoDisciplinarioResource::class;

    protected function getCreateFormAction(): Actions\Action
    {
        return parent::getCreateFormAction()
            ->requiresConfirmation()
            ->modalHeading('Confirmar Creación de Proceso Disciplinario')
            ->modalDescription(function (array $data): string {
                // Verificar si se enviará automáticamente
                $trabajador = \App\Models\Trabajador::find($data['trabajador_id'] ?? null);
                $modalidad = $data['modalidad_descargos'] ?? null;
                $fechaProgramada = $data['fecha_descargos_programada'] ?? $data['hora_temp_descargos'] ?? null;

                if (
                    $trabajador &&
                    !empty($trabajador->email) &&
                    !empty($fechaProgramada) &&
                    in_array($modalidad, ['presencial', 'telefonico', 'virtual'])
                ) {
                    $mensajeModalidad = match($modalidad) {
                        'virtual' => 'Se enviará la citación con link de acceso web para descargos virtuales.',
                        'presencial' => 'Se enviará la citación para asistir presencialmente a la diligencia.',
                        'telefonico' => 'Se enviará la citación para la audiencia telefónica.',
                        default => 'Se enviará la citación al trabajador.'
                    };

                    return "⚠️ La citación será enviada AUTOMÁTICAMENTE al correo: {$trabajador->email}\n\n" .
                           "{$mensajeModalidad}\n\n" .
                           "Se generarán automáticamente las preguntas con IA para los descargos.\n\n" .
                           "¿Desea continuar?";
                }

                return '¿Está seguro que desea crear este proceso disciplinario?';
            })
            ->modalSubmitActionLabel('Sí, crear y enviar citación')
            ->modalIcon('heroicon-o-paper-airplane')
            ->color('success');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Para presencial/telefónico: usar el valor de hora_temp_descargos
        if (in_array($data['modalidad_descargos'] ?? '', ['presencial', 'telefonico'])) {
            if (!empty($data['hora_temp_descargos'])) {
                $data['fecha_descargos_programada'] = $data['hora_temp_descargos'];
            }
        }

        // Debug: Log para verificar qué se está guardando
        Log::info('CREATE - Datos antes de crear proceso disciplinario:', [
            'modalidad' => $data['modalidad_descargos'] ?? 'no definido',
            'fecha_descargos_programada' => $data['fecha_descargos_programada'] ?? 'no definido',
            'hora_temp_descargos' => $data['hora_temp_descargos'] ?? 'no definido',
        ]);

        // Remover campos temporales del array de datos
        unset($data['fecha_temp_descargos']);
        unset($data['hora_temp_descargos']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $proceso = $this->record;

        // Solo enviar citación automáticamente si:
        // 1. Tiene fecha programada
        // 2. El trabajador tiene email
        // 3. La modalidad es presencial, telefónico o virtual
        if (
            !empty($proceso->fecha_descargos_programada) &&
            !empty($proceso->trabajador->email) &&
            in_array($proceso->modalidad_descargos, ['presencial', 'telefonico', 'virtual'])
        ) {
            try {
                $documentService = new DocumentGeneratorService();
                $resultado = $documentService->generarYEnviarCitacion($proceso);

                if ($resultado['success']) {
                    // Verificar si se generaron preguntas con IA
                    $preguntasConIA = $resultado['preguntas_ia_generadas'] ?? false;

                    if ($preguntasConIA) {
                        $mensajeModalidad = $proceso->modalidad_descargos === 'virtual'
                            ? 'La citación fue generada y enviada automáticamente con link de acceso web y preguntas generadas por IA.'
                            : 'La citación fue generada y enviada automáticamente al trabajador con preguntas generadas por IA.';

                        Notification::make()
                            ->success()
                            ->title('Proceso creado y citación enviada')
                            ->body($mensajeModalidad)
                            ->duration(5000)
                            ->send();
                    } else {
                        // Correo enviado pero sin preguntas de IA
                        Notification::make()
                            ->warning()
                            ->title('Citación enviada con advertencia')
                            ->body('La citación fue enviada exitosamente, pero no se pudieron generar preguntas con IA. Deberá generarlas manualmente desde el módulo de Descargos.')
                            ->duration(8000)
                            ->send();
                    }

                    Log::info('Citación enviada automáticamente después de crear proceso', [
                        'proceso_id' => $proceso->id,
                        'codigo' => $proceso->codigo,
                        'modalidad' => $proceso->modalidad_descargos,
                        'preguntas_ia' => $preguntasConIA,
                    ]);
                } else {
                    // Verificar si el error es por falta de preguntas con IA
                    if (str_contains($resultado['message'], 'IA no pudo generar preguntas')) {
                        Notification::make()
                            ->danger()
                            ->title('¡ERROR! No se generaron preguntas con IA')
                            ->body($resultado['message'] . ' El proceso fue creado pero debe generar las preguntas manualmente desde el módulo de Descargos.')
                            ->persistent()
                            ->send();
                    } else {
                        Notification::make()
                            ->warning()
                            ->title('Proceso creado con advertencia')
                            ->body('El proceso fue creado pero hubo un error: ' . $resultado['message'])
                            ->duration(8000)
                            ->send();
                    }

                    Log::warning('Error al enviar citación automática', [
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

                Log::error('Excepción al enviar citación automática', [
                    'proceso_id' => $proceso->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
