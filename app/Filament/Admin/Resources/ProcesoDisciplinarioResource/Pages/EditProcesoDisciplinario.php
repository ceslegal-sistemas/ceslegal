<?php

namespace App\Filament\Admin\Resources\ProcesoDisciplinarioResource\Pages;

use App\Filament\Admin\Resources\ProcesoDisciplinarioResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;

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
        // Al cargar el formulario para editar, poblar campos temporales según modalidad

        if (in_array($data['modalidad_descargos'], ['presencial', 'telefonico'])) {
            // Para presencial/telefónico: combinar fecha + hora en hora_temp_descargos
            if (!empty($data['fecha_descargos_programada']) && !empty($data['hora_descargos_programada'])) {
                $data['hora_temp_descargos'] = $data['fecha_descargos_programada'] . ' ' . $data['hora_descargos_programada'];
            }
        }
        // Para virtual: los campos ya están separados, no necesita transformación

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Para presencial/telefónico: extraer fecha y hora de hora_temp_descargos
        if (in_array($data['modalidad_descargos'] ?? '', ['presencial', 'telefonico'])) {
            if (!empty($data['hora_temp_descargos'])) {
                $datetime = \Carbon\Carbon::parse($data['hora_temp_descargos']);
                $data['fecha_descargos_programada'] = $datetime->format('Y-m-d');
                $data['hora_descargos_programada'] = $datetime->format('H:i:s');
            }
        }

        // Debug: Log para verificar qué se está guardando
        Log::info('EDIT - Datos antes de guardar proceso disciplinario (edición):', [
            'modalidad' => $data['modalidad_descargos'] ?? 'no definido',
            'fecha_descargos_programada' => $data['fecha_descargos_programada'] ?? 'no definido',
            'hora_descargos_programada' => $data['hora_descargos_programada'] ?? 'no definido',
        ]);

        // Remover campos temporales del array de datos
        unset($data['fecha_temp_descargos']);
        unset($data['hora_temp_descargos']);

        return $data;
    }
}
