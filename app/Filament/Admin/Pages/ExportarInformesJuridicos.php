<?php

namespace App\Filament\Admin\Pages;

use App\Models\Empresa;
use App\Services\InformeJuridicoExportService;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ExportarInformesJuridicos extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-down';

    protected static string $view = 'filament.admin.pages.exportar-informes-juridicos';

    protected static ?string $title = 'Exportar Informes Jurídicos';

    protected static ?string $navigationLabel = 'Exportar Informes';

    protected static ?string $navigationGroup = 'Informes';

    protected static ?int $navigationSort = 10;

    public ?int $empresa_id = null;
    public ?int $anio = null;
    public ?string $mes = null;

    public function mount(): void
    {
        $user = Auth::user();

        // Si el usuario tiene empresa asignada, preseleccionar
        if ($user->empresa_id) {
            $this->empresa_id = $user->empresa_id;
        }

        $this->anio = now()->year;
        $this->mes = 'todos';
    }

    public function form(Form $form): Form
    {
        $user = Auth::user();
        $esAbogado = in_array($user->role, ['abogado', 'super_admin']);

        return $form
            ->schema([
                Section::make('Seleccione los parámetros del informe')
                    ->description('Configure los filtros para generar el informe de gestión jurídica')
                    ->icon('heroicon-o-funnel')
                    ->schema([
                        Select::make('empresa_id')
                            ->label('Empresa')
                            ->options(function () use ($user, $esAbogado) {
                                if ($esAbogado) {
                                    return Empresa::where('active', true)
                                        ->orderBy('razon_social')
                                        ->pluck('razon_social', 'id');
                                }

                                return Empresa::where('id', $user->empresa_id)
                                    ->pluck('razon_social', 'id');
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(!$esAbogado && $user->empresa_id)
                            ->native(false)
                            ->columnSpan(2),

                        Select::make('anio')
                            ->label('Año')
                            ->options(InformeJuridicoExportService::getAniosDisponibles())
                            ->required()
                            ->native(false),

                        Select::make('mes')
                            ->label('Mes')
                            ->options(array_merge(
                                ['todos' => 'Todos los meses (Anual)'],
                                InformeJuridicoExportService::getMeses()
                            ))
                            ->required()
                            ->native(false),
                    ])
                    ->columns(2),
            ]);
    }

    public function exportarPDF()
    {
        if (!$this->empresa_id || !$this->anio) {
            Notification::make()
                ->danger()
                ->title('Error')
                ->body('Debe seleccionar una empresa y un año.')
                ->send();
            return;
        }

        try {
            $service  = new InformeJuridicoExportService();
            $path     = $service->generarPDF($this->empresa_id, $this->anio, $this->mes);
            $filename = basename($path);
            $contenido = Storage::disk('public')->get($path);

            return response()->streamDownload(
                fn () => print($contenido),
                $filename,
                ['Content-Type' => 'application/pdf']
            );

        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Error al generar PDF')
                ->body('No se pudo generar el informe: ' . $e->getMessage())
                ->send();
        }
    }

    public function exportarExcel()
    {
        if (!$this->empresa_id || !$this->anio) {
            Notification::make()
                ->danger()
                ->title('Error')
                ->body('Debe seleccionar una empresa y un año.')
                ->send();
            return;
        }

        try {
            $service  = new InformeJuridicoExportService();
            $path     = $service->generarExcel($this->empresa_id, $this->anio, $this->mes);
            $filename = basename($path);
            $contenido = Storage::disk('public')->get($path);

            return response()->streamDownload(
                fn () => print($contenido),
                $filename,
                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
            );

        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Error al generar Excel')
                ->body('No se pudo generar el informe: ' . $e->getMessage())
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();
        return in_array($user->role, ['abogado', 'super_admin', 'admin_empresa']);
    }
}
