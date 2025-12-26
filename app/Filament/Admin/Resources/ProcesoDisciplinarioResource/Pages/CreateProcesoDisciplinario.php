<?php

namespace App\Filament\Admin\Resources\ProcesoDisciplinarioResource\Pages;

use App\Filament\Admin\Resources\ProcesoDisciplinarioResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

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
}
