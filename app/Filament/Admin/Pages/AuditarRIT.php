<?php

namespace App\Filament\Admin\Pages;

use App\Jobs\GenerarGAPReporteJob;
use App\Jobs\GenerarRITMejoradoJob;
use App\Jobs\ProcesarAuditoriaRIT;
use App\Models\AuditoriaRIT;
use App\Models\Empresa;
use App\Models\GapReporte;
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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AuditarRIT extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-magnifying-glass-circle';
    protected static ?string $navigationLabel = 'Auditoría de RIT';
    protected static ?string $navigationGroup = 'Empresa';
    protected static ?int    $navigationSort  = 11;
    protected static string  $view            = 'filament.pages.auditar-rit';

    public ?Empresa           $empresa              = null;
    public ?AuditoriaRIT      $auditoria            = null;
    public ?ReglamentoInterno $rit                  = null;
    public ?ReglamentoInterno $ritMejorado          = null;
    public ?GapReporte        $gapReporte           = null;
    public bool               $procesando           = false;
    public bool               $soloExternoPermitido = false;
    public array              $data                 = [];

    public function mount(): void
    {
        $user    = Auth::user();
        $esAdmin = $this->esAdmin();

        $this->empresa = $esAdmin
            ? Empresa::first()
            : $user->empresa ?? null;

        // Seguridad: cliente solo puede ver su propia empresa
        if (!$esAdmin && $this->empresa && $this->empresa->id !== ($user->empresa_id ?? null)) {
            abort(403);
        }

        if ($this->empresa) {
            $this->rit = ReglamentoInterno::where('empresa_id', $this->empresa->id)
                ->orderByDesc('updated_at')
                ->first();

            // Lógica de negocio: clientes con RIT construido/mejorado por el sistema
            // no pueden auditarlo directamente — el módulo es para RITs externos.
            if (!$esAdmin && $this->rit && in_array($this->rit->fuente, ['construido_ia', 'mejora_ia'])) {
                $this->soloExternoPermitido = true;
            }

            // Cargar auditoría más reciente
            $this->auditoria = AuditoriaRIT::where('empresa_id', $this->empresa->id)
                ->latest()
                ->first();

            if ($this->auditoria?->reglamento_mejorado_id) {
                $this->ritMejorado = $this->auditoria->reglamentoMejorado;
            }

            $this->gapReporte = GapReporte::where('auditoria_rit_id', $this->auditoria?->id)->first();
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

        // Seguridad: verificar que la empresa pertenece al usuario autenticado
        $this->verificarPropiedadEmpresa();

        $this->form->validate();

        // FileUpload guarda siempre como array aunque sea un solo archivo
        $archivoExterno = $this->data['rit_externo'] ?? null;
        if (is_array($archivoExterno)) {
            $archivoExterno = $archivoExterno[0] ?? null;
        }

        // Lógica de negocio: clientes con RIT generado por el sistema deben subir un archivo externo
        if ($this->soloExternoPermitido && !$archivoExterno) {
            Notification::make()
                ->warning()
                ->title('Se requiere documento externo')
                ->body('El módulo de auditoría verifica su propio RIT contra la normativa vigente. Adjunte el documento para continuar.')
                ->send();
            return;
        }

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

    // Polling gestionado desde la vista: wire:poll.2000ms solo cuando hay proceso activo
    public function refrescarEstado(): void
    {
        if ($this->auditoria) {
            $this->auditoria = $this->auditoria->fresh();
            $this->procesando = $this->auditoria?->estaEnProceso() ?? false;

            // Cargar RIT mejorado cuando la mejora se complete
            if ($this->auditoria?->reglamento_mejorado_id) {
                $this->ritMejorado = $this->auditoria->reglamentoMejorado()->first();
            }

            // Refrescar reporte GAP si está en proceso
            if ($this->gapReporte?->estaGenerando()) {
                $this->gapReporte = $this->gapReporte->fresh();
            } elseif ($this->auditoria && !$this->gapReporte) {
                // Por si el reporte fue creado después del último refresh
                $this->gapReporte = GapReporte::where('auditoria_rit_id', $this->auditoria->id)->first();
            }
        }
    }

    public function nuevaAuditoria(): void
    {
        $this->auditoria   = null;
        $this->ritMejorado = null;
        $this->gapReporte  = null;
        $this->procesando  = false;
        $this->data        = [];
        $this->form->fill();
    }

    public function reintentarMejora(): void
    {
        $this->verificarPropiedadEmpresa();
        if (!$this->auditoria || $this->auditoria->estado !== 'completado') {
            Notification::make()->warning()->title('No hay auditoría completada')->send();
            return;
        }

        $this->auditoria->update([
            'estado_mejora' => 'procesando',
            'mensaje_error' => null,
        ]);

        GenerarRITMejoradoJob::dispatch($this->auditoria->fresh(), Auth::id());

        Notification::make()
            ->info()
            ->title('Regenerando RIT Mejorado')
            ->body('El proceso ha sido reiniciado. Recibirá una notificación al terminar.')
            ->send();
    }

    public function downloadPDFMejorado(): mixed
    {
        if (!$this->ritMejorado?->ruta_pdf) {
            Notification::make()->warning()->title('PDF no disponible aún')->send();
            return null;
        }

        $rutaAbsoluta = Storage::path($this->ritMejorado->ruta_pdf);

        if (!file_exists($rutaAbsoluta)) {
            Notification::make()->danger()->title('Archivo no encontrado en el servidor')->send();
            return null;
        }

        $nombreEmpresa = $this->empresa?->razon_social ?? 'empresa';
        $nombreEmpresaSeguro = preg_replace('/[^A-Za-z0-9\-_]/', '_', $nombreEmpresa);
        $nombreArchivo = "RIT_v{$this->ritMejorado->version}_{$nombreEmpresaSeguro}.pdf";

        return response()->download($rutaAbsoluta, $nombreArchivo, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function generarReporteGAP(): void
    {
        $this->verificarPropiedadEmpresa();
        if (!$this->auditoria || $this->auditoria->estado !== 'completado') {
            Notification::make()->warning()->title('Auditoría no completada')->send();
            return;
        }

        // Crear registro en estado 'generando' de forma optimista
        $this->gapReporte = GapReporte::updateOrCreate(
            ['auditoria_rit_id' => $this->auditoria->id],
            [
                'empresa_id'     => $this->empresa->id,
                'estado'         => 'generando',
                'score_snapshot' => $this->auditoria->score,
                'mensaje_error'  => null,
            ]
        );

        GenerarGAPReporteJob::dispatch($this->auditoria, Auth::id());

        Notification::make()
            ->info()
            ->title('Generando Reporte GAP')
            ->body('Los reportes ejecutivo y técnico están siendo generados. Recibirá una notificación al terminar.')
            ->send();
    }

    public function downloadGapEjecutivo(): mixed
    {
        if (!$this->gapReporte?->ruta_ejecutivo) {
            Notification::make()->warning()->title('PDF ejecutivo no disponible aún')->send();
            return null;
        }

        $rutaAbsoluta = Storage::path($this->gapReporte->ruta_ejecutivo);
        if (!file_exists($rutaAbsoluta)) {
            Notification::make()->danger()->title('Archivo no encontrado en el servidor')->send();
            return null;
        }

        $nombreEmpresa = preg_replace('/[^A-Za-z0-9\-_]/', '_', $this->empresa?->razon_social ?? 'empresa');
        return response()->download($rutaAbsoluta, "GAP_Ejecutivo_{$nombreEmpresa}.pdf", [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function downloadGapTecnico(): mixed
    {
        if (!$this->gapReporte?->ruta_tecnico) {
            Notification::make()->warning()->title('PDF técnico no disponible aún')->send();
            return null;
        }

        $rutaAbsoluta = Storage::path($this->gapReporte->ruta_tecnico);
        if (!file_exists($rutaAbsoluta)) {
            Notification::make()->danger()->title('Archivo no encontrado en el servidor')->send();
            return null;
        }

        $nombreEmpresa = preg_replace('/[^A-Za-z0-9\-_]/', '_', $this->empresa?->razon_social ?? 'empresa');
        return response()->download($rutaAbsoluta, "GAP_Tecnico_{$nombreEmpresa}.pdf", [
            'Content-Type' => 'application/pdf',
        ]);
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

    // ── Helpers de seguridad ──────────────────────────────────────────────────

    private function esAdmin(): bool
    {
        $user = Auth::user();
        return $user->hasRole('super_admin') || $user->hasRole('abogado');
    }

    /**
     * Aborta con 403 si el usuario autenticado no pertenece a la empresa cargada.
     * Los roles admin/abogado tienen acceso irrestricto.
     */
    private function verificarPropiedadEmpresa(): void
    {
        if ($this->esAdmin()) {
            return;
        }

        $user = Auth::user();
        if (!$this->empresa || $this->empresa->id !== ($user->empresa_id ?? null)) {
            abort(403, 'No tiene acceso a los datos de esta empresa.');
        }
    }
}
