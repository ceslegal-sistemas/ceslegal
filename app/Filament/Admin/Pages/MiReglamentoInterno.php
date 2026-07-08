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
                // detectables. Se avisa con un mensaje según el motivo real: PDF protegido
                // (p. ej. un RIT generado por el propio sistema, que sale con protección) o
                // PDF sin texto seleccionable (escaneado como imagen).
                if (empty($ritCreado->texto_completo)) {
                    $protegido = app(ReglamentoInternoService::class)
                        ->motivoTextoVacio($rutaAbsoluta) === 'protegido';

                    Notification::make()
                        ->warning()
                        ->title('RIT guardado, pero no se pudo leer el texto')
                        ->body($protegido
                            ? 'El PDF está protegido (con restricciones de edición), por eso el sistema no pudo leer su contenido para detectar faltas y sanciones. Si este reglamento lo generó con nuestro constructor, ya está guardado y no necesita volver a subirlo. Si desea reemplazarlo, suba el archivo en Word o un PDF sin protección.'
                            : 'El archivo parece ser un PDF escaneado (imagen), sin texto seleccionable. Se guardó como versión vigente, pero el sistema no podrá detectar las faltas y sanciones automáticamente. Le recomendamos subir el reglamento en Word o en un PDF con texto seleccionable.')
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
