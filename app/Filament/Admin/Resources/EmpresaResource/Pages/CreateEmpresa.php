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
                app(ReglamentoInternoService::class)->procesarDocumento(
                    storage_path("app/{$path}"),
                    $this->record->id,
                    basename($path)
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
