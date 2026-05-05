<?php

namespace App\Filament\Admin\Resources\EmpresaResource\Pages;

use App\Filament\Admin\Resources\EmpresaResource;
use App\Services\ReglamentoInternoService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditEmpresa extends EditRecord
{
    protected static string $resource = EmpresaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Interceptar los datos antes de guardar en el modelo Empresa:
     * - Si se subió un RIT, procesarlo y guardarlo en reglamentos_internos.
     * - Eliminar el campo temporal para que no intente guardarse en la tabla empresas.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $rutaRelativa = $data['reglamento_docx_temp'] ?? null;

        if ($rutaRelativa) {
            $rutaAbsoluta = Storage::disk('local')->path($rutaRelativa);

            app(ReglamentoInternoService::class)->procesarDocumento(
                $rutaAbsoluta,
                $this->record->id,
                basename($rutaRelativa)
            );
        }

        unset($data['reglamento_docx_temp']);

        return $data;
    }
}
