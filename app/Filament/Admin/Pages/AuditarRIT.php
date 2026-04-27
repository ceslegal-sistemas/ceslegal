<?php

namespace App\Filament\Admin\Pages;

use App\Jobs\ProcesarAuditoriaRIT;
use App\Models\AuditoriaRIT;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Services\AuditoriaRITService;
use App\Services\BibliotecaLegalService;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Poll;

class AuditarRIT extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-magnifying-glass-circle';
    protected static ?string $navigationLabel = 'Auditoría de RIT';
    protected static ?string $navigationGroup = 'Empresa';
    protected static ?int    $navigationSort  = 11;
    protected static string  $view            = 'filament.pages.auditar-rit';

    public ?Empresa           $empresa    = null;
    public ?AuditoriaRIT      $auditoria  = null;
    public ?ReglamentoInterno $rit        = null;
    public bool               $procesando = false;
    public array              $data       = [];

    public function mount(): void
    {
        $user = Auth::user();

        $this->empresa = ($user->hasRole('super_admin') || $user->hasRole('abogado'))
            ? Empresa::first()
            : $user->empresa ?? null;

        if ($this->empresa) {
            $this->rit = ReglamentoInterno::where('empresa_id', $this->empresa->id)
                ->orderByDesc('updated_at')
                ->first();

            // Cargar auditoría más reciente
            $this->auditoria = AuditoriaRIT::where('empresa_id', $this->empresa->id)
                ->latest()
                ->first();
        }

        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                FileUpload::make('rit_externo')
                    ->label('Subir RIT externo (PDF o Word)')
                    ->helperText('Si tiene un RIT propio que desea auditar, adjúntelo aquí. Si no, se auditará el RIT generado en el sistema.')
                    ->disk('local')
                    ->directory('tmp/auditoria')
                    ->acceptedFileTypes(['application/pdf',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/msword'])
                    ->maxSize(10240)
                    ->nullable(),
            ])
            ->statePath('data');
    }

    public function iniciarAuditoria(): void
    {
        if (!$this->empresa) {
            Notification::make()->warning()->title('Sin empresa asociada')->send();
            return;
        }

        $this->form->validate();

        // Verificar que hay algo que auditar
        $archivoExterno = $this->data['rit_externo'] ?? null;
        if (!$archivoExterno && (!$this->rit || empty($this->rit->texto_completo))) {
            Notification::make()
                ->warning()
                ->title('Sin RIT disponible')
                ->body('Genere primero su Reglamento Interno o suba un documento para auditar.')
                ->send();
            return;
        }

        $textoExterno = null;

        // Si subió un archivo externo, extraer su texto
        if ($archivoExterno) {
            try {
                $rutaAbsoluta = Storage::disk('local')->path($archivoExterno);
                $docLegal     = new \App\Models\DocumentoLegal([
                    'archivo_path'            => 'tmp/auditoria/' . basename($archivoExterno),
                    'archivo_nombre_original' => basename($archivoExterno),
                ]);
                $textoExterno = app(BibliotecaLegalService::class)->extraerTexto($docLegal);
            } catch (\Throwable $e) {
                Notification::make()
                    ->danger()
                    ->title('Error al leer el documento')
                    ->body('No se pudo extraer el texto del archivo: ' . $e->getMessage())
                    ->send();
                return;
            }
        }

        // Crear registro de auditoría
        $service         = app(AuditoriaRITService::class);
        $this->auditoria = $service->iniciar($this->empresa, $textoExterno);
        $this->procesando = true;

        // Despachar Job (con cola 'sync' se ejecuta en la misma solicitud)
        ProcesarAuditoriaRIT::dispatch($this->auditoria, Auth::id());

        // Refrescar estado desde BD (para cola sync donde el job ya terminó)
        $this->auditoria  = $this->auditoria->fresh();
        $this->procesando = $this->auditoria?->estaEnProceso() ?? false;

        if ($this->auditoria?->estado === 'completado') {
            Notification::make()
                ->success()
                ->title('Auditoría completada')
                ->body("La revisión finalizó con un score de {$this->auditoria->score}/100. Revise los resultados.")
                ->send();
        } elseif ($this->auditoria?->estado === 'error') {
            Notification::make()
                ->danger()
                ->title('Error en la auditoría')
                ->body($this->auditoria->mensaje_error ?? 'Ocurrió un error inesperado.')
                ->send();
        } else {
            Notification::make()
                ->info()
                ->title('Auditoría en proceso')
                ->body('Estamos revisando su RIT sección por sección. Recibirá una notificación al terminar.')
                ->send();
        }
    }

    #[Poll(5000)] // Pollar cada 5 segundos mientras procesa
    public function refrescarEstado(): void
    {
        if ($this->auditoria) {
            $this->auditoria = $this->auditoria->fresh();
            $this->procesando = $this->auditoria?->estaEnProceso() ?? false;
        }
    }

    public function nuevaAuditoria(): void
    {
        $this->auditoria  = null;
        $this->procesando = false;
        $this->data       = [];
        $this->form->fill();
    }

    public function getTitle(): string
    {
        return 'Auditoría de Reglamento Interno';
    }

    public static function canAccess(): bool
    {
        return Auth::check();
    }

    public function getNumSecciones(): int
    {
        return AuditoriaRITService::getNumSecciones();
    }
}
