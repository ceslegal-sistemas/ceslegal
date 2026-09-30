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
use Filament\Forms\Components\DatePicker;
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

            // Auditoría del RIT actualmente VIGENTE (no "la más reciente de la empresa
            // sin más" - un cliente puede construir un RIT nuevo desde cero con el wizard,
            // que reemplaza y desactiva el anterior; si aquí se tomara la auditoría más
            // reciente sin filtrar, seguiría mostrando "Reglamento actualizado con IA...
            // esta es su versión vigente" sobre una mejora adoptada en el RIT VIEJO ya
            // reemplazado - bug real reportado por el usuario).
            $this->auditoria = $this->reglamento
                ? \App\Models\AuditoriaRIT::where('empresa_id', $this->empresa->id)
                    ->where('reglamento_interno_id', $this->reglamento->id)
                    ->latest()
                    ->first()
                : null;

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

        // Auditar con nuestra propia IA un RIT que la misma IA ya construyó
        // (construido_ia) o mejoró (mejora_ia) es circular - no aporta una
        // verificación independiente. El botón ya está oculto para esos casos
        // en la vista; este candado es solo la segunda capa de defensa.
        if ($this->reglamento->fuente !== 'subido') {
            Notification::make()->warning()->title('Este Reglamento no se puede auditar')->send();
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

    /**
     * Extrae (o regenera) el organigrama del RIT con IA - solo tiene sentido
     * para RIT subido/redactado libremente (un RIT construido con el wizard
     * ya trae los cargos estructurados por el propio cliente, ver
     * ReglamentoInternoService::cargosDeEmpresa()). Solo super_admin, mismo
     * criterio que generarConductasAction()/reextraerSancionesAction().
     */
    public function generarOrganigramaAction(): Action
    {
        return Action::make('generarOrganigrama')
            ->label('Generar organigrama')
            ->icon('heroicon-o-users')
            ->color('gray')
            ->visible(fn() => $this->reglamento
                && !empty($this->reglamento->texto_completo)
                && empty($this->reglamento->respuestas_cuestionario['cargos'])
                && (Auth::user()?->hasRole('super_admin') ?? false))
            ->requiresConfirmation()
            ->modalHeading('Generar organigrama del RIT')
            ->modalDescription('La IA leerá el Reglamento Interno y extraerá los cargos mencionados (y su facultad disciplinaria si el texto la indica). Se usa para sugerir el cargo al crear una Solicitud de Contrato. Si ya existe, se reemplaza.')
            ->modalSubmitActionLabel('Generar')
            ->action(function (): void {
                if (!$this->reglamento) {
                    Notification::make()->danger()->title('No hay Reglamento activo')->send();
                    return;
                }
                try {
                    $organigrama = app(ReglamentoInternoService::class)->generarOrganigrama($this->reglamento);
                    if (empty($organigrama)) {
                        Notification::make()->warning()
                            ->title('No se pudo extraer el organigrama')
                            ->body('La IA no encontró cargos mencionados explícitamente en el texto del RIT.')
                            ->send();
                        return;
                    }
                    Notification::make()->success()
                        ->title('Organigrama generado')
                        ->body(count($organigrama) . ' cargo(s) detectado(s). Ya están disponibles al crear una Solicitud de Contrato.')
                        ->send();
                    $this->reglamento = $this->reglamento->fresh();
                } catch (\Throwable $e) {
                    Notification::make()->danger()
                        ->title('Error al generar el organigrama')
                        ->body($e->getMessage())
                        ->send();
                }
            });
    }

    /**
     * Genera el video didáctico de "segunda socialización" con IA (pedido
     * explícito del usuario/su equipo, 2026-09-28: reemplaza la idea de
     * diapositivas por un video real con Gemini Omni Flash). Disparo
     * SIEMPRE manual y solo super_admin por ahora - un video de IA cuesta
     * ordenes de magnitud más que las llamadas de texto que ya agotaron la
     * cuota mensual una vez esta misma sesión (ver RitVideoDidacticoService),
     * y el costo real por video todavía no se ha confirmado en
     * https://ai.studio/spend.
     */
    public function generarVideoDidacticoAction(): Action
    {
        return Action::make('generarVideoDidactico')
            ->label(fn () => $this->reglamento?->video_didactico_path ? 'Regenerar video didáctico' : 'Generar video didáctico')
            ->icon('heroicon-o-video-camera')
            ->color('primary')
            ->visible(fn () => $this->reglamento
                && !empty($this->reglamento->texto_completo)
                && !$this->reglamento->generandoVideoDidactico()
                && (Auth::user()?->hasRole('super_admin') ?? false))
            ->requiresConfirmation()
            ->modalHeading('Generar video didáctico del Reglamento')
            ->modalDescription('La IA genera un video corto explicando en lenguaje sencillo los puntos clave del Reglamento (incluye el logo de la empresa si ya lo cargó). Este proceso tarda varios minutos y tiene un costo real de IA - úselo con moderación mientras se confirma el costo por video.')
            ->modalSubmitActionLabel('Generar')
            ->action(function (): void {
                if (!$this->reglamento) {
                    Notification::make()->danger()->title('No hay Reglamento activo')->send();
                    return;
                }

                // El video habla de "qué cambió" SOLO si la versión anterior
                // (el origen) ya tuvo su propio video generado - eso significa
                // que a los trabajadores ya se les explicó esa versión antes,
                // así que tiene sentido narrarles la diferencia. Si el origen
                // nunca tuvo video (aunque el RIT técnicamente venga de una
                // mejora IA - fuente=mejora_ia), nadie vio nunca un video de
                // "la versión vieja", así que no hay nada que "actualizar"
                // desde la perspectiva del trabajador: el video debe explicar
                // el reglamento completo en temas generales, como si fuera la
                // primera vez (hallazgo real en producción, 2026-09-29: Renbel
                // tenía reglamento_origen_id pero nunca se le había generado
                // video antes, y aun así salió narrando cambios puntuales en
                // vez de una explicación general).
                $cambios = [];
                if ($this->reglamento->reglamento_origen_id) {
                    $origen = ReglamentoInterno::withoutGlobalScope('bufeteOrEmpresa')
                        ->find($this->reglamento->reglamento_origen_id);
                    if ($origen?->video_didactico_generado_en && $origen->texto_completo && $this->reglamento->texto_completo) {
                        $cambios = app(\App\Services\RitDiffService::class)->compararDocumentos(
                            $origen->texto_completo,
                            $this->reglamento->texto_completo
                        );
                    }
                }

                $this->reglamento->update(['video_didactico_estado' => 'generando']);
                \App\Jobs\GenerarVideoDidacticoRITJob::dispatch($this->reglamento, (int) Auth::id(), $cambios);
                $this->reglamento = $this->reglamento->fresh();

                Notification::make()
                    ->info()
                    ->title('Generando video didáctico')
                    ->body('Puede tardar varios minutos. Le notificaremos cuando esté listo.')
                    ->send();
            });
    }

    /**
     * Declarar la "fecha de publicación" del Reglamento (pedido del equipo,
     * 2026-09-29): la empresa puede tardar días en publicar físicamente el
     * Reglamento (carteleras) después de generarlo en el sistema, así que
     * los 15 días hábiles para objetar deben contarse desde ESA fecha, no
     * desde la fecha de creación. El texto del modal se redactó en tono de
     * servicio ("calculamos el plazo por usted"), no de advertencia/regaño -
     * pedido explícito del usuario (2026-09-30) tras ver la primera
     * redacción: "estamos brindando un servicio no regañando y culpando a
     * los clientes".
     */
    public function declararFechaPublicacionAction(): Action
    {
        return Action::make('declararFechaPublicacion')
            ->label(fn () => $this->reglamento?->fecha_publicacion_socializacion
                ? 'Cambiar fecha de publicación'
                : 'Declarar fecha de publicación')
            ->icon('heroicon-o-calendar-days')
            ->color('gray')
            ->visible(fn () => $this->reglamento && !empty($this->reglamento->texto_completo))
            ->modalHeading('Fecha de publicación del Reglamento Interno')
            ->modalDescription('Indique la fecha en que el Reglamento quedará disponible para sus trabajadores - por ejemplo, el día que va a compartir este link o a pegar las carteleras. No tiene que coincidir con el día que lo generó aquí en el sistema. Con esta fecha calculamos automáticamente el plazo de 15 días hábiles que la ley les da a sus trabajadores para objetarlo, así usted no tiene que llevar la cuenta.')
            ->modalSubmitActionLabel('Guardar fecha')
            ->form([
                DatePicker::make('fecha_publicacion_socializacion')
                    ->label('Fecha de publicación')
                    ->native(false)
                    ->minDate(now())
                    ->default(fn () => $this->reglamento?->fecha_publicacion_socializacion)
                    ->required(),
            ])
            ->action(function (array $data): void {
                if (!$this->reglamento) {
                    return;
                }

                $this->reglamento->update(['fecha_publicacion_socializacion' => $data['fecha_publicacion_socializacion']]);
                $this->reglamento = $this->reglamento->fresh();

                Notification::make()
                    ->success()
                    ->title('Fecha de publicación guardada')
                    ->body('Los 15 días hábiles para objetar el Reglamento se cuentan desde esta fecha.')
                    ->send();
            });
    }

    /**
     * Detalle por trabajador de quién aceptó el RIT VIGENTE y quién no
     * (pedido de Andrés Sarmiento, 2026-09-29: la barra de progreso
     * "X de Y trabajadores han aceptado" necesitaba un desglose, no solo el
     * conteo). Compara por HASH del texto vigente, igual que
     * Trabajador::aceptoRitVigente(), para que un trabajador que aceptó una
     * versión ya reemplazada aparezca correctamente como "pendiente".
     *
     * @return array<int, array{nombre: string, cargo: ?string, acepto: bool, fecha_aceptacion: ?\Carbon\Carbon}>
     */
    public function detalleTrabajadoresSocializacion(): array
    {
        if (!$this->empresa || !$this->reglamento || empty($this->reglamento->texto_completo)) {
            return [];
        }

        $hashVigente = hash('sha256', $this->reglamento->texto_completo);

        return $this->empresa->trabajadores()
            ->where('active', true)
            ->get()
            ->map(function (\App\Models\Trabajador $trabajador) use ($hashVigente) {
                $aceptacion = $trabajador->aceptacionesReglamentoInterno()
                    ->where('texto_rit_hash', $hashVigente)
                    ->latest('aceptado_en')
                    ->first();

                return [
                    'nombre' => $trabajador->nombre_completo,
                    'cargo' => $trabajador->cargo,
                    'acepto' => (bool) $aceptacion,
                    'fecha_aceptacion' => $aceptacion?->aceptado_en,
                ];
            })
            ->sortBy('acepto')
            ->values()
            ->all();
    }

    /** Modal con el reporte completo de socialización (mismo detalle que el desplegable inline, sin recortar). */
    public function verReporteSocializacionAction(): Action
    {
        return Action::make('verReporteSocializacion')
            ->label('Ver reporte completo')
            ->icon('heroicon-o-clipboard-document-list')
            ->color('gray')
            ->visible(fn () => $this->estadoSocializacionRitParaVista()['total'] > 0)
            ->modalHeading('Reporte de socialización del Reglamento Interno')
            ->modalContent(fn () => view('filament.components.rit-reporte-socializacion-modal', [
                'detalle' => $this->detalleTrabajadoresSocializacion(),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar');
    }

    /** Mismo estado que usa la vista para la barra de progreso - evita duplicar la consulta al servicio de logros. */
    public function estadoSocializacionRitParaVista(): array
    {
        if (!$this->empresa) {
            return ['aceptados' => 0, 'total' => 0, 'porcentaje' => 0, 'completo' => false];
        }

        return app(\App\Services\LogroSocializacionRitService::class)->estadoDashboard($this->empresa);
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
