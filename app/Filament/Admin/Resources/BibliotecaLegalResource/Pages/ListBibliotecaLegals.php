<?php

namespace App\Filament\Admin\Resources\BibliotecaLegalResource\Pages;

use App\Filament\Admin\Resources\BibliotecaLegalResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListBibliotecaLegals extends ListRecords
{
    protected static string $resource = BibliotecaLegalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Subir documento')
                ->icon('heroicon-o-arrow-up-tray'),

            Actions\Action::make('importar_masivo')
                ->label('Importar varios documentos')
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->modalHeading('Importar varios documentos')
                ->modalDescription('Suba varios archivos a la vez. No hace falta llenar nada más: la IA analiza cada documento por separado para identificar título, tipo, referencia, fecha de expedición y si debe incorporarse íntegro al RIT. Revise los campos sugeridos después, desde la lista o editando cada documento.')
                ->modalSubmitActionLabel('Importar')
                ->modalWidth(\Filament\Support\Enums\MaxWidth::Large)
                ->form([
                    Forms\Components\FileUpload::make('archivos')
                        ->label('Documentos (PDF, DOCX o TXT)')
                        ->multiple()
                        // Bug real encontrado en producción (2026-10-22): sin
                        // esto, Filament guarda cada archivo en disco y lo
                        // convierte a una ruta de texto ANTES de que corra la
                        // acción de abajo (ver FileUpload::saveUploadedFiles(),
                        // llamado en beforeStateDehydrated()) - por eso en la
                        // interfaz se veían "subida completada", pero
                        // crearDocumentosDesdeArchivosMasivo() esperaba objetos
                        // TemporaryUploadedFile (para guardarlos él mismo con
                        // ->store() y leer ->getClientOriginalName()), así que
                        // el `instanceof` fallaba para TODOS y no se creaba
                        // ningún documento ("No se importó ningún documento").
                        // storeFiles(false) deja los archivos sin guardar hasta
                        // que el propio loop los procese.
                        ->storeFiles(false)
                        ->acceptedFileTypes([
                            'application/pdf',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'text/plain',
                        ])
                        ->maxSize(20480) // 20 MB por archivo
                        ->maxFiles(30)
                        ->required()
                        ->helperText('Máximo 30 archivos por tanda, 20 MB cada uno. PDF, DOCX o TXT.'),
                ])
                ->action(function (array $data) {
                    $creados = BibliotecaLegalResource::crearDocumentosDesdeArchivosMasivo($data['archivos'] ?? []);

                    if ($creados === 0) {
                        Notification::make()
                            ->warning()
                            ->title('No se importó ningún documento')
                            ->send();
                        return;
                    }

                    Notification::make()
                        ->success()
                        ->title("{$creados} documento(s) en proceso")
                        ->body('La IA está identificando título, tipo, referencia, fecha y si debe incorporarse íntegro al RIT de cada uno. Esta lista se actualiza sola - revise los campos sugeridos cuando terminen.')
                        ->send();
                }),
        ];
    }
}
