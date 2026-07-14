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

    /**
     * Multi-tenant: si un abogado de bufete crea la empresa, queda vinculada a su
     * bufete automáticamente (no elige bufete a mano).
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (auth()->user()?->esAbogadoDeBufete()) {
            $data['bufete_id'] = auth()->user()->bufete_id;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        // La empresa recién creada queda seleccionada como activa (bufete y admin),
        // para que las herramientas de RIT (construir/auditar/mi reglamento) operen
        // sobre ella tras redirigir.
        \App\Support\EmpresaActiva::set($this->record->id);

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

    /**
     * Redirección según la rama de RIT elegida al crear:
     *  - 'tiene'     → Auditar RIT (el reglamento subido se audita).
     *  - 'construir' → Mi Reglamento Interno (desde donde se construye con IA).
     *  - otro/CST    → listado de empresas.
     */
    protected function getRedirectUrl(): string
    {
        $opcion = $this->data['rit_opcion'] ?? null;

        // Empresa OBLIGADA que no cargó ni eligió construir → llevar al constructor.
        if ($this->record->requiereRit() === true && ! in_array($opcion, ['tiene', 'construir'], true)) {
            return route('filament.admin.pages.mi-reglamento-interno');
        }

        return match ($opcion) {
            'tiene'     => route('filament.admin.pages.auditar-r-i-t'),
            'construir' => route('filament.admin.pages.mi-reglamento-interno'),
            default     => $this->getResource()::getUrl('index'),
        };
    }
}
