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
