<?php

namespace App\Filament\Admin\Pages;

use AlexSyvolap\FilamentConfetti\Confetti;
use App\Jobs\GenerarTextoRITJob;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\SugerenciaActualizacionRit;
use App\Services\ReglamentoInternoService;
use App\Services\RitActualizacionAutomaticaService;
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
    use \App\Filament\Concerns\InteractsConAceptacionMejoraRIT;

    protected static ?string $navigationIcon  = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Mi Reglamento Interno';
    protected static ?string $navigationGroup = 'Empresa';
    protected static ?int    $navigationSort  = 10;
    protected static string  $view            = 'filament.pages.mi-reglamento-interno';

    public ?ReglamentoInterno $reglamento = null;
    public ?Empresa $empresa = null;
    public ?\App\Models\AuditoriaRIT $auditoria = null;
    public ?ReglamentoInterno $ritMejorado = null;

    /** Llegó desde la notificación de "nueva normativa disponible" (?resaltar=auditar) - resalta el botón de auditar. */
    public bool $resaltarAuditar = false;

    /** Cambios quirúrgicos propuestos por IA (Plan B) para el RIT vigente, pendientes de aprobar/rechazar. */
    public \Illuminate\Support\Collection $sugerenciasPendientes;

    public static function shouldRegisterNavigation(): bool
    {
        // Bufete: oculto hasta seleccionar una empresa específica en el topbar
        // (mismo criterio que ProcesoDisciplinarioResource/TrabajadorResource) -
        // sin empresa activa, esta página solo mostraría un aviso pidiendo elegir una.
        if (auth()->user()?->bufeteSinEmpresaActiva()) {
            return false;
        }

        return parent::shouldRegisterNavigation();
    }

    public function mount(): void
    {
        $this->resaltarAuditar = request()->query('resaltar') === 'auditar';
        $this->sugerenciasPendientes = collect();

        $user = Auth::user();
        if (!$user) {
            $this->redirect(\App\Filament\Admin\Pages\Dashboard::getUrl());
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
                ? (($aid = \App\Support\EmpresaActiva::id()) ? Empresa::find($aid) : Empresa::first())
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

            // Auditoría más reciente de la empresa (vista unificada RIT + salud legal).
            $this->auditoria = \App\Models\AuditoriaRIT::where('empresa_id', $this->empresa->id)
                ->latest()
                ->first();

            if ($this->auditoria?->reglamento_mejorado_id) {
                $this->ritMejorado = $this->auditoria->reglamentoMejorado()->first();
            }

            if ($this->reglamento) {
                $this->cargarSugerenciasPendientes();
            }

            // Auto-auditar: cliente con RIT subido que aún no tiene auditoría
            // (p. ej. justo después de registrarse subiendo su RIT).
            if (Auth::user()?->hasRole('cliente')
                && ! $this->auditoria
                && $this->reglamento
                && $this->reglamento->fuente === 'subido'
                && ! empty($this->reglamento->texto_completo)
            ) {
                $this->auditoria = app(\App\Services\AuditoriaRITService::class)->iniciar($this->empresa, null);
                \App\Jobs\ProcesarAuditoriaRIT::dispatch($this->auditoria, (int) Auth::id());
            }

            // Respaldo perezoso: RIT con texto pero sin sanciones_extraidas todavía
            // (dato legado de antes de que subirRITAction() lo disparara solo, o una
            // extracción que falló). El cliente ya no depende de un botón manual
            // para esto - ver ExtraerSancionesRITJob y el punto equivalente en
            // subirRITAction().
            if ($this->reglamento
                && ! empty($this->reglamento->texto_completo)
                && empty($this->reglamento->sanciones_extraidas)
            ) {
                \App\Jobs\ExtraerSancionesRITJob::dispatch($this->reglamento);
            }
        }

        // Confeti de bienvenida: solo la primera vez tras registrarse (flag de un solo uso).
        // class_exists evita fatal si el paquete aún no está instalado en vendor/.
        if (session()->pull('celebrar_registro_rit') && class_exists(Confetti::class)) {
            Confetti::fireworks()->shoot();
        }
    }

    /** Polling de la auditoría/mejora en la vista unificada. */
    public function refrescarAuditoria(): void
    {
        if ($this->auditoria) {
            $this->auditoria = $this->auditoria->fresh();
            if ($this->auditoria?->reglamento_mejorado_id) {
                $this->ritMejorado = $this->auditoria->reglamentoMejorado()->first();
            }
        }
    }

    /**
     * Cambios quirúrgicos propuestos por IA (Plan B de actualización
     * automática del RIT) para el RIT vigente de la empresa, pendientes de
     * aprobar/rechazar. Un registro por bloque afectado, nunca el RIT
     * completo - ver RitActualizacionAutomaticaService.
     */
    protected function cargarSugerenciasPendientes(): void
    {
        $this->sugerenciasPendientes = SugerenciaActualizacionRit::where('reglamento_interno_id', $this->reglamento->id)
            ->where('estado', 'pendiente')
            // Solo propuestas cuyo documento de origen sigue vigente: si la
            // firma retiró el documento (error de carga, versión equivocada),
            // el cliente no debe seguir viendo -ni pudiendo aprobar- un cambio
            // sustentado en él.
            ->whereHas('documentoLegal', fn ($q) => $q->where('activo', true))
            ->with('documentoLegal')
            ->latest()
            ->get();
    }

    /** Aprueba una sugerencia: aplica el cambio quirúrgico al RIT vigente y refresca la vista. */
    public function aprobarSugerencia(int $sugerenciaId): void
    {
        $sugerencia = SugerenciaActualizacionRit::find($sugerenciaId);
        if (!$sugerencia) {
            return;
        }

        $aplicada = app(RitActualizacionAutomaticaService::class)->aplicarSugerencia($sugerencia, Auth::user());

        if (!$aplicada) {
            // Solo llega aquí si el texto original ya no existe tal cual en el
            // RIT (se editó/borró) o aparece más de una vez. No se ofrece
            // "recargar": no existe tal acción y dejaba al cliente presionando
            // Aprobar sin salida. La salida real es rechazarla y volver a
            // auditar, que sí parte del texto vigente.
            Notification::make()
                ->warning()
                ->title('Este cambio ya no aplica a su Reglamento')
                ->body('El texto que se iba a modificar cambió desde que se propuso el ajuste. Puede rechazar esta sugerencia y volver a auditar su RIT para obtener una propuesta sobre el texto actual.')
                ->persistent()
                ->send();
            $this->cargarSugerenciasPendientes();
            return;
        }

        $this->reglamento = $this->reglamento->fresh();
        $this->cargarSugerenciasPendientes();

        Notification::make()
            ->success()
            ->title('Cambio aplicado a su Reglamento Interno')
            ->send();
    }

    /** Rechaza una sugerencia: no toca el RIT, solo cierra la propuesta. */
    public function rechazarSugerencia(int $sugerenciaId): void
    {
        $sugerencia = SugerenciaActualizacionRit::find($sugerenciaId);
        if (!$sugerencia) {
            return;
        }

        app(RitActualizacionAutomaticaService::class)->rechazarSugerencia($sugerencia, Auth::user());
        $this->cargarSugerenciasPendientes();

        Notification::make()->info()->title('Sugerencia rechazada')->send();
    }

    /** Reintenta la generación del RIT mejorado si falló. */
    public function reintentarMejora(): void
    {
        if (! $this->auditoria || $this->auditoria->estado !== 'completado') {
            return;
        }
        $this->auditoria->update(['estado_mejora' => 'procesando', 'mensaje_error' => null]);
        \App\Jobs\GenerarRITMejoradoJob::dispatch($this->auditoria->fresh(), (int) Auth::id());
        $this->auditoria = $this->auditoria->fresh();
        Notification::make()->info()->title('Regenerando RIT mejorado')->send();
    }

    /** Descarga el PDF del RIT mejorado. */
    public function downloadPDFMejorado(): mixed
    {
        if (! $this->ritMejorado) {
            Notification::make()->warning()->title('RIT mejorado no disponible aún')->send();
            return null;
        }

        $nombreEmpresa = preg_replace('/[^A-Za-z0-9\-_]/', '_', $this->empresa?->razon_social ?? 'empresa');
        $nombreArchivo = "RIT_v{$this->ritMejorado->version}_{$nombreEmpresa}.pdf";

        if ($this->ritMejorado->ruta_pdf) {
            $rutaAbsoluta = Storage::path($this->ritMejorado->ruta_pdf);
            if (file_exists($rutaAbsoluta)) {
                return response()->download($rutaAbsoluta, $nombreArchivo, ['Content-Type' => 'application/pdf']);
            }
        }

        if (! empty($this->ritMejorado->texto_completo) && $this->empresa) {
            $tmpPath = app(\App\Services\RITGeneratorService::class)
                ->generarPDFTemp($this->ritMejorado->texto_completo, $this->empresa);
            return response()->download($tmpPath, $nombreArchivo, ['Content-Type' => 'application/pdf'])
                ->deleteFileAfterSend();
        }

        Notification::make()->danger()->title('Archivo no encontrado en el servidor')->send();
        return null;
    }

    /** Lanza manualmente la auditoría del RIT vigente (si aún no hay ninguna). */
    public function iniciarAuditoriaManual(): void
    {
        if (! $this->empresa || ! $this->reglamento || empty($this->reglamento->texto_completo)) {
            Notification::make()->warning()->title('No hay un RIT para auditar')->send();
            return;
        }

        $this->auditoria = app(\App\Services\AuditoriaRITService::class)->iniciar($this->empresa, null);
        \App\Jobs\ProcesarAuditoriaRIT::dispatch($this->auditoria, (int) Auth::id());

        Notification::make()
            ->info()
            ->title('Auditoría iniciada')
            ->body('Estamos revisando su Reglamento Interno contra la normativa vigente. Verá el resultado en unos segundos.')
            ->send();
    }

    /** El responsable decide mantener su RIT actual (la mejora queda archivada). */
    public function mantenerRITActual(): void
    {
        if ($this->auditoria) {
            app(\App\Services\AceptacionMejoraRITService::class)->mantenerActual($this->auditoria);
            $this->auditoria = $this->auditoria->fresh();
            Notification::make()->info()->title('Conservó su RIT actual')->send();
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
     *
     * Ya NO es el único disparador: ExtraerSancionesRITJob corre solo al subir un
     * RIT y como respaldo perezoso en mount() (ver ambos). El cliente/bufete no
     * debería necesitar esto nunca en el flujo normal, así que el botón manual
     * queda solo para super_admin como herramienta de reintento/soporte.
     */
    public function reextraerSancionesAction(): Action
    {
        return Action::make('reextraerSanciones')
            ->label('Re-extraer sanciones')
            ->icon('heroicon-o-arrow-path')
            ->color('gray')
            ->visible(fn() => $this->reglamento
                && !empty($this->reglamento->texto_completo)
                && (Auth::user()?->hasRole('super_admin') ?? false))
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

    /**
     * Genera (o regenera) el listado de conductas sancionables del RIT con IA.
     * Solo super_admin: para bufete/cliente confundía verlo como un botón
     * pendiente de usar incluso cuando las conductas ya estaban generadas -
     * queda como herramienta de generación/regeneración para el equipo interno.
     */
    public function generarConductasAction(): Action
    {
        return Action::make('generarConductas')
            ->label('Generar conductas sancionables')
            ->icon('heroicon-o-sparkles')
            ->color('primary')
            ->visible(fn() => $this->reglamento && (
                !empty($this->reglamento->texto_completo)
                || !empty($this->reglamento->respuestas_cuestionario['sanciones_configuradas'])
            ) && (Auth::user()?->hasRole('super_admin') ?? false))
            ->requiresConfirmation()
            ->modalHeading('Generar conductas sancionables')
            ->modalDescription('La IA construye el listado de conductas sancionables por gravedad (leve, grave, gravísima) con su medida disciplinaria, conforme al CST. Este contenido es público dentro del RIT. Si ya existe, se reemplaza.')
            ->modalSubmitActionLabel('Generar')
            ->action(function (): void {
                if (!$this->reglamento) {
                    Notification::make()->danger()->title('No hay Reglamento activo')->send();
                    return;
                }
                try {
                    $conductas = app(ReglamentoInternoService::class)->generarConductasSancionables($this->reglamento);
                    $total = count($conductas['leve'] ?? []) + count($conductas['grave'] ?? []) + count($conductas['gravisima'] ?? []);
                    if ($total === 0) {
                        Notification::make()->warning()
                            ->title('No se pudieron generar conductas')
                            ->body('La IA no devolvió un listado válido. Intente de nuevo o verifique el contenido del RIT.')
                            ->send();
                        return;
                    }
                    Notification::make()->success()
                        ->title('Conductas sancionables generadas')
                        ->body("Se generaron {$total} conductas por gravedad, conforme al CST.")
                        ->send();
                    $this->reglamento = $this->reglamento->fresh();
                } catch (\Throwable $e) {
                    Notification::make()->danger()
                        ->title('Error al generar conductas')
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
            // "Subir Reglamento" en vez de "Guardar RIT": el jefe reportó que el
            // cliente se confundía y pensaba que el RIT aún no quedaba guardado
            // (porque después de este modal solo ve el visor de texto plano, sin
            // nada que diga explícitamente "guardado") - ver también el label del
            // visor en mi-reglamento-interno.blade.php ("...vigente").
            ->modalSubmitActionLabel('Subir Reglamento')
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
                    $servicio = app(ReglamentoInternoService::class);
                    $motivo   = $servicio->motivoTextoVacio($rutaAbsoluta);

                    Notification::make()
                        ->warning()
                        ->title('No se pudo leer el contenido del reglamento')
                        ->body($servicio->mensajeTextoVacio($motivo))
                        ->persistent()
                        ->send();

                    return;
                }

                // Auto-auditar el RIT recién subido (consistencia con el registro):
                // subir siempre dispara la auditoría automática, que se muestra en el
                // panel de esta misma página (vista unificada).
                $this->auditoria = app(\App\Services\AuditoriaRITService::class)->iniciar($this->empresa, null);
                \App\Jobs\ProcesarAuditoriaRIT::dispatch($this->auditoria, (int) Auth::id());

                // Extraer las sanciones por gravedad en segundo plano - el cliente ya
                // no depende de hacer clic en "Re-extraer sanciones" (ver job).
                \App\Jobs\ExtraerSancionesRITJob::dispatch($ritCreado);

                Notification::make()
                    ->success()
                    ->title('RIT subido - auditando')
                    ->body('El documento se guardó como versión vigente y estamos auditándolo contra la normativa. Verá el resultado aquí mismo en unos segundos.')
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
