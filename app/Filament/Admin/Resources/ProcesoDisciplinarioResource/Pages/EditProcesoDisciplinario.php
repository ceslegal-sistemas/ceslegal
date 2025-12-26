<?php

namespace App\Filament\Admin\Resources\ProcesoDisciplinarioResource\Pages;

use App\Filament\Admin\Resources\ProcesoDisciplinarioResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProcesoDisciplinario extends EditRecord
{
    protected static string $resource = ProcesoDisciplinarioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Al cargar el formulario para editar, separar fecha y hora según modalidad

        if (!empty($data['fecha_descargos_programada'])) {
            $datetime = \Carbon\Carbon::parse($data['fecha_descargos_programada']);

            if ($data['modalidad_descargos'] === 'virtual') {
                // Para virtual: separar fecha y hora
                // La fecha ya está en fecha_descargos_programada
                // Extraer solo la hora para el campo hora_descargos_programada
                $data['hora_descargos_programada'] = $datetime->format('H:i');
            } else if (in_array($data['modalidad_descargos'], ['presencial', 'telefonico'])) {
                // Para presencial/telefónico: establecer campos temporales
                $data['fecha_temp_descargos'] = $datetime->format('Y-m-d');
                $data['hora_temp_descargos'] = $datetime->format('Y-m-d H:i:s');
            }
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Para presencial/telefónico: usar el valor de hora_temp_descargos
        if (in_array($data['modalidad_descargos'] ?? '', ['presencial', 'telefonico'])) {
            if (!empty($data['hora_temp_descargos'])) {
                $data['fecha_descargos_programada'] = $data['hora_temp_descargos'];
            }
        }

        // Debug: Log para verificar qué se está guardando
        \Log::info('EDIT - Datos antes de guardar proceso disciplinario (edición):', [
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
}
