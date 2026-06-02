<?php

namespace App\Filament\Admin\Resources\EmpresaResource\Pages;

use App\Filament\Admin\Resources\EmpresaResource;
use App\Services\ReglamentoInternoService;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;

class CreateEmpresa extends CreateRecord
{
    protected static string $resource = EmpresaResource::class;

    protected function afterCreate(): void
    {
        $path = $this->data['reglamento_docx_temp'] ?? null;
        if ($path) {
            try {
                $nombreArchivo  = basename($path);
                $rutaPermanente = 'reglamentos/' . $this->record->id . '/' . $nombreArchivo;

                \Illuminate\Support\Facades\Storage::disk('local')->move($path, $rutaPermanente);

                app(ReglamentoInternoService::class)->procesarDocumento(
                    storage_path("app/{$rutaPermanente}"),
                    $this->record->id,
                    $nombreArchivo,
                    $rutaPermanente,
                );
            } catch (\Exception $e) {
                Log::error('Error al procesar reglamento interno en CreateEmpresa', [
                    'empresa_id' => $this->record->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }
    }
}
