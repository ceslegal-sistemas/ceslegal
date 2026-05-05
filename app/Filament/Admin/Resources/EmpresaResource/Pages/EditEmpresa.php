<?php

namespace App\Filament\Admin\Resources\EmpresaResource\Pages;

use App\Filament\Admin\Resources\EmpresaResource;
use App\Services\ReglamentoInternoService;
use Filament\Actions;
use Filament\Notifications\Notification;
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
        $raw = $data['reglamento_docx_temp'] ?? null;

        // Filament FileUpload devuelve array incluso para archivos individuales
        if (is_array($raw)) {
            $raw = $raw[0] ?? null;
        }

        if ($raw) {
            $rutaAbsoluta = Storage::disk('local')->path($raw);

            app(ReglamentoInternoService::class)->procesarDocumento(
                $rutaAbsoluta,
                $this->record->id,
                basename($raw)
            );

            Notification::make()
                ->success()
                ->title('Reglamento Interno guardado')
                ->body('El RIT fue registrado correctamente en el sistema.')
                ->send();
        }

        unset($data['reglamento_docx_temp']);

        return $data;
    }
}
