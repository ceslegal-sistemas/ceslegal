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

        if ($user->esAbogadoDeBufete()) {
            // Bufete: opera sobre la empresa elegida en el selector del topbar.
            $activaId = \App\Support\EmpresaActiva::id();
            $this->empresa = $activaId ? Empresa::find($activaId) : null;
            if (! $this->empresa) {
                \Filament\Notifications\Notification::make()
                    ->warning()
                    ->title('Seleccione una empresa')
                    ->body('Elija la empresa en el selector de la barra superior para ver o construir su Reglamento Interno.')
                    ->send();
            }
        } else {
            $this->empresa = ($user->hasRole('super_admin') || $user->hasRole('abogado'))
                ? Empresa::first()
                : ($user->empresa ?? null);
        }

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
        // class_exists evita fatal si el paquete aún no está instalado en vendor/.
        if (session()->pull('celebrar_registro_rit') && class_exists(Confetti::class)) {
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

    /**
     * Re-extrae las faltas y su sanción EXACTA por gravedad (leve/grave/muy grave)
     * del RIT, releyendo el texto con IA. Útil para RIT subidos antes de esta mejora,
     * cuya extracción guardada no separaba la sanción por gravedad.
     */
    public function reextraerSancionesAction(): Action
    {
        return Action::make('reextraerSanciones')
            ->label('Re-extraer sanciones')
            ->icon('heroicon-o-arrow-path')
            ->color('gray')
            ->visible(fn() => $this->reglamento && !empty($this->reglamento->texto_completo))
            ->requiresConfirmation()
            ->modalHeading('Re-extraer la tabla de sanciones')
            ->modalDescription('Vuelve a leer el Reglamento con IA para extraer las faltas y su sanción exacta por gravedad (leve, grave, muy grave). Úselo si la tabla de sanciones de los documentos no coincide con su RIT.')
            ->modalSubmitActionLabel('Re-extraer')
            ->action(function (): void {
                if (!$this->reglamento) {
                    Notification::make()->danger()->title('No hay Reglamento activo')->send();
                    return;
                }
                try {
                    $datos = app(ReglamentoInternoService::class)->extraerYPersistirSanciones($this->reglamento);
                    if (empty($datos)) {
                        Notification::make()->warning()
                            ->title('No se pudieron extraer sanciones')
                            ->body('La IA no encontró un cuadro de faltas claro en el texto del Reglamento.')
                            ->send();
                        return;
                    }
                    Notification::make()->success()
                        ->title('Sanciones re-extraídas')
                        ->body('La tabla de sanciones de los documentos ahora usará los datos actualizados del RIT.')
                        ->send();
                    $this->reglamento = $this->reglamento->fresh();
                } catch (\Throwable $e) {
                    Notification::make()->danger()
                        ->title('Error al re-extraer')
                        ->body($e->getMessage())
                        ->send();
                }
            });
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

                $ritCreado = app(ReglamentoInternoService::class)->procesarDocumento(
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

                // Si no se pudo extraer texto, el RIT queda guardado pero sin sanciones
                // detectables. Se identifica el motivo real y se da una acción concreta.
                if (empty($ritCreado->texto_completo)) {
                    $motivo = app(ReglamentoInternoService::class)->motivoTextoVacio($rutaAbsoluta);

                    $cuerpo = match ($motivo) {
                        'protegido' => 'El PDF está protegido o cifrado (tiene restricciones), por eso no se puede leer su contenido para detectar las faltas y sanciones. Vuelva a subirlo en Word (.docx) o exporte el PDF sin protección.',
                        'corrupto'  => 'No se pudo leer el archivo: puede estar dañado o en un formato no compatible. Verifíquelo y vuelva a subirlo en Word (.docx) o en un PDF con texto seleccionable.',
                        default     => 'El PDF no tiene texto seleccionable (parece escaneado como imagen), por eso no se puede leer su contenido para detectar las faltas y sanciones. Vuelva a subirlo en Word (.docx) o en un PDF con texto seleccionable.',
                    };

                    Notification::make()
                        ->warning()
                        ->title('No se pudo leer el contenido del reglamento')
                        ->body($cuerpo)
                        ->persistent()
                        ->send();

                    return;
                }

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
