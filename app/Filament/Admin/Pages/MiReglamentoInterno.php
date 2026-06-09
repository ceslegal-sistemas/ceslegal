<?php

namespace App\Filament\Admin\Pages;

use AlexSyvolap\FilamentConfetti\Confetti;
use App\Jobs\GenerarTextoRITJob;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Services\ReglamentoInternoService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class MiReglamentoInterno extends Page implements HasForms, HasActions
{
    use InteractsWithForms, InteractsWithActions;

    protected static ?string $navigationIcon  = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Mi Reglamento Interno';
    protected static ?string $navigationGroup = 'Empresa';
    protected static ?int    $navigationSort  = 10;
    protected static string  $view            = 'filament.pages.mi-reglamento-interno';

    public ?ReglamentoInterno $reglamento = null;
    public ?Empresa $empresa = null;

    public function mount(): void
    {
        $user = Auth::user();
        if (!$user) {
            $this->redirect(route('filament.admin.pages.dashboard'));
            return;
        }

        $this->empresa = ($user->hasRole('super_admin') || $user->hasRole('abogado'))
            ? Empresa::first()
            : ($user->empresa ?? null);

        if ($this->empresa) {
            // Prioridad: RIT activo (completado) ó el más reciente en estado generando/error
            $this->reglamento = ReglamentoInterno::where('empresa_id', $this->empresa->id)
                ->where(function ($q) {
                    $q->where('activo', true)
                      ->orWhereIn('estado_generacion', ['generando', 'error']);
                })
                ->orderByDesc('updated_at')
                ->first();
        }

        // Confeti de bienvenida: solo la primera vez tras registrarse (flag de un solo uso).
        if (session()->pull('celebrar_registro_rit')) {
            Confetti::fireworks()->shoot();
        }
    }

    /** Reintenta la generación del RIT cuando falló. */
    public function reintentarGeneracion(): void
    {
        if (!$this->reglamento || $this->reglamento->estado_generacion !== 'error') {
            return;
        }

        $this->reglamento->update([
            'estado_generacion' => 'generando',
            'mensaje_error_ia'  => null,
        ]);

        GenerarTextoRITJob::dispatch($this->reglamento, Auth::id());

        Notification::make()
            ->info()
            ->title('Reintentando generación...')
            ->body('La IA está procesando su RIT nuevamente. Le notificaremos cuando esté listo.')
            ->send();

        // Recargar estado para que la vista muestre el shimmer inmediatamente
        $this->reglamento = $this->reglamento->fresh();
    }

    /** Abre el modal para subir un RIT manualmente. */
    public function subirRITAction(): Action
    {
        return Action::make('subirRIT')
            ->label('Subir RIT')
            ->icon('heroicon-o-arrow-up-tray')
            ->modalHeading('Subir Reglamento Interno')
            ->modalDescription('Suba su propio Reglamento Interno en formato PDF o Word. El texto será extraído y guardado como versión vigente.')
            ->modalSubmitActionLabel('Guardar RIT')
            ->form([
                FileUpload::make('archivo')
                    ->label('Documento (PDF o Word)')
                    ->disk('local')
                    ->directory('reglamentos-temp')
                    ->acceptedFileTypes([
                        'application/pdf',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/msword',
                    ])
                    ->maxSize(10240)
                    ->required(),
            ])
            ->action(function (array $data): void {
                if (!$this->empresa) {
                    Notification::make()->danger()->title('Sin empresa asociada')->send();
                    return;
                }

                $path           = is_array($data['archivo']) ? ($data['archivo'][0] ?? null) : $data['archivo'];
                $nombreArchivo  = basename($path);
                $rutaPermanente = 'reglamentos/' . $this->empresa->id . '/' . $nombreArchivo;

                Storage::disk('local')->move($path, $rutaPermanente);

                $rutaAbsoluta = Storage::disk('local')->path($rutaPermanente);

                app(ReglamentoInternoService::class)->procesarDocumento(
                    $rutaAbsoluta,
                    $this->empresa->id,
                    $nombreArchivo,
                    $rutaPermanente,
                );

                // Recargar el reglamento activo
                $this->reglamento = ReglamentoInterno::where('empresa_id', $this->empresa->id)
                    ->where(function ($q) {
                        $q->where('activo', true)
                          ->orWhereIn('estado_generacion', ['generando', 'error']);
                    })
                    ->orderByDesc('updated_at')
                    ->first();

                Notification::make()
                    ->success()
                    ->title('RIT subido correctamente')
                    ->body('El documento ha sido procesado y guardado como versión vigente.')
                    ->send();
            });
    }

    public function getTitle(): string
    {
        return 'Reglamento Interno de Trabajo';
    }

    public static function canAccess(): bool
    {
        return Auth::check();
    }
}
