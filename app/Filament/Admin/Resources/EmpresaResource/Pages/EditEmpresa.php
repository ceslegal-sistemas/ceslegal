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
            $basename       = basename($raw);
            $dirDestino     = "rits/{$this->record->id}";
            $rutaPermanente = "{$dirDestino}/{$basename}";

            // Crear directorio si no existe y mover el archivo a ubicación permanente
            try {
                Storage::disk('local')->makeDirectory($dirDestino);
                Storage::disk('local')->move($raw, $rutaPermanente);
            } catch (\Throwable $e) {
                // Si el move falla, continuar con la ruta temporal
                \Illuminate\Support\Facades\Log::warning('EditEmpresa: no se pudo mover RIT a permanente', [
                    'from'  => $raw,
                    'to'    => $rutaPermanente,
                    'error' => $e->getMessage(),
                ]);
                $rutaPermanente = Storage::disk('local')->exists($raw) ? $raw : null;
            }

            $rutaAbsoluta = $rutaPermanente
                ? Storage::disk('local')->path($rutaPermanente)
                : null;

            if ($rutaAbsoluta && file_exists($rutaAbsoluta)) {
                app(ReglamentoInternoService::class)->procesarDocumento(
                    $rutaAbsoluta,
                    $this->record->id,
                    $basename,
                    $rutaPermanente,
                );
            }

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
