<?php

namespace App\Filament\Admin\Resources\EmpresaResource\Pages;

use App\Filament\Admin\Resources\EmpresaResource;
use App\Jobs\ProcesarAuditoriaRIT;
use App\Models\AuditoriaRIT;
use App\Services\AceptacionMejoraRITService;
use App\Services\AuditoriaRITService;
use App\Services\ReglamentoInternoService;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\View as ViewComponent;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CreateEmpresa extends CreateRecord
{
    use HasWizard;

    protected static string $resource = EmpresaResource::class;

    /** Auditoría temporal del RIT subido (empresa_id null hasta afterCreate). */
    public ?AuditoriaRIT $auditoria = null;

    /**
     * Multi-tenant: si un abogado de bufete crea la empresa, queda vinculada a su bufete.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (auth()->user()?->esAbogadoDeBufete()) {
            $data['bufete_id'] = auth()->user()->bufete_id;
        }

        // Campos del wizard que no son columnas de empresas.
        unset(
            $data['rit_decision'],
            $data['rit_autoridad'],
            $data['rit_resp_nombre'],
            $data['rit_resp_documento'],
            $data['rit_resp_cargo'],
        );

        return $data;
    }

    // ── Wizard ────────────────────────────────────────────────────────────────

    protected function getSteps(): array
    {
        return [
            Step::make('Datos de la empresa')
                ->icon('heroicon-o-building-office-2')
                ->schema(EmpresaResource::formSchema())
                ->afterValidation(function (Get $get): void {
                    // Al pasar de datos → auditoría: si subió RIT, arranca la auditoría async.
                    if (($get('rit_opcion') ?? null) === 'tiene' && ! $this->auditoria) {
                        $this->iniciarAuditoriaWizard($get('reglamento_docx_temp'), (string) $get('razon_social'));
                    }
                }),

            Step::make('Auditoría del RIT')
                ->icon('heroicon-o-shield-check')
                ->schema([
                    ViewComponent::make('filament.components.rit-wizard-auditoria'),

                    // Decisión (gate nativo): obligatoria solo si subió RIT. En fail-safe se
                    // pre-marca 'mantener' para no bloquear la creación.
                    Radio::make('rit_decision')
                        ->label('¿Desea actualizar su Reglamento Interno con las sugerencias de la auditoría?')
                        ->options([
                            'aceptar'  => 'Sí, generar y adoptar la versión mejorada del RIT',
                            'mantener' => 'No, mantener mi RIT tal como lo subí',
                        ])
                        ->required(fn (Get $get) => ($get('rit_opcion') ?? null) === 'tiene')
                        ->dehydrated(false)
                        ->live(),

                    // Autoridad + datos del responsable (solo si acepta la actualización).
                    Group::make([
                        Placeholder::make('aviso_autoridad')->hiddenLabel()
                            ->content('Esta actualización debe ser aprobada por alguien con autoridad para modificar el Reglamento Interno de la empresa. Registramos quién la autoriza.'),
                        Checkbox::make('rit_autoridad')
                            ->label('Declaro que tengo la autoridad para aprobar cambios al Reglamento Interno de esta empresa.')
                            ->accepted()
                            ->dehydrated(false)
                            ->required(fn (Get $get) => $get('rit_decision') === 'aceptar'),
                        TextInput::make('rit_resp_nombre')->label('Nombre completo')
                            ->dehydrated(false)->maxLength(255)
                            ->required(fn (Get $get) => $get('rit_decision') === 'aceptar'),
                        TextInput::make('rit_resp_documento')->label('Documento (cédula)')
                            ->dehydrated(false)->maxLength(50)
                            ->required(fn (Get $get) => $get('rit_decision') === 'aceptar'),
                        TextInput::make('rit_resp_cargo')->label('Cargo')
                            ->dehydrated(false)->maxLength(255)
                            ->required(fn (Get $get) => $get('rit_decision') === 'aceptar'),
                    ])->visible(fn (Get $get) => $get('rit_decision') === 'aceptar'),
                ]),
        ];
    }

    // ── Auditoría dentro del wizard ─────────────────────────────────────────────

    /**
     * Lanza la auditoría del RIT subido (async). Fail-safe: cualquier problema NO
     * bloquea la creación (se marca el gate para permitir continuar).
     */
    public function iniciarAuditoriaWizard(mixed $archivo, string $razonSocial): void
    {
        try {
            // El estado del FileUpload puede venir como [uuid => ruta], como string, o como
            // un TemporaryUploadedFile (aún sin mover). Se resuelve la ruta absoluta real.
            $valor = is_array($archivo) ? (reset($archivo) ?: null) : $archivo;

            if (is_object($valor) && method_exists($valor, 'getRealPath')) {
                $rutaAbsoluta = $valor->getRealPath(); // TemporaryUploadedFile
            } elseif (is_string($valor) && $valor !== '') {
                $rutaAbsoluta = Storage::disk('local')->path($valor);
            } else {
                $rutaAbsoluta = null;
            }

            Log::info('CreateEmpresa: intento de auditoría en wizard', [
                'tipo_valor' => is_object($valor) ? get_class($valor) : gettype($valor),
                'valor'      => is_string($valor) ? $valor : null,
                'abs'        => $rutaAbsoluta,
                'existe'     => $rutaAbsoluta ? is_file($rutaAbsoluta) : false,
            ]);

            if (! $rutaAbsoluta || ! is_file($rutaAbsoluta)) {
                $this->permitirCrearSinAuditoria();
                return;
            }

            // Extraer el texto desde la ruta absoluta (mismo extractor que procesarDocumento).
            $texto = app(ReglamentoInternoService::class)->extraerTextoDeArchivo($rutaAbsoluta);

            if (empty(trim($texto))) {
                // No se puede auditar un documento ilegible → no bloquear la creación.
                Notification::make()
                    ->warning()
                    ->title('No se pudo leer el RIT')
                    ->body('El documento no tiene texto legible, por lo que no se puede auditar. Podrá auditarlo luego desde "Auditar RIT". Puede continuar y crear la empresa.')
                    ->persistent()
                    ->send();
                $this->permitirCrearSinAuditoria();
                return;
            }

            $this->auditoria = app(AuditoriaRITService::class)
                ->iniciarDesdeTexto($texto, $razonSocial ?: 'La empresa', (int) auth()->id());
            ProcesarAuditoriaRIT::dispatch($this->auditoria, (int) auth()->id());
        } catch (\Throwable $e) {
            Log::error('CreateEmpresa: no se pudo iniciar auditoría del RIT', ['error' => $e->getMessage()]);
            $this->permitirCrearSinAuditoria();
        }
    }

    /** Polling del progreso de la auditoría/mejora en el paso del wizard. */
    public function refrescarAuditoriaWizard(): void
    {
        if ($this->auditoria) {
            $this->auditoria = $this->auditoria->fresh();
        }
    }

    /** Fail-safe: si no se pudo auditar, se pre-marca 'mantener' para no bloquear la creación. */
    private function permitirCrearSinAuditoria(): void
    {
        $this->data['rit_decision'] = 'mantener';
    }

    // ── Post-creación ───────────────────────────────────────────────────────────

    protected function afterCreate(): void
    {
        // La empresa recién creada queda seleccionada como activa.
        \App\Support\EmpresaActiva::set($this->record->id);

        // Persistir el RIT subido (extrae texto + sanciones).
        $path = $this->data['reglamento_docx_temp'] ?? null;
        if ($path) {
            try {
                $nombreArchivo  = basename($path);
                $rutaPermanente = 'reglamentos/' . $this->record->id . '/' . $nombreArchivo;
                Storage::disk('local')->move($path, $rutaPermanente);

                app(ReglamentoInternoService::class)->procesarDocumento(
                    storage_path("app/{$rutaPermanente}"),
                    $this->record->id,
                    $nombreArchivo,
                    $rutaPermanente,
                );
            } catch (\Exception $e) {
                Log::error('Error al procesar reglamento interno en CreateEmpresa', [
                    'empresa_id' => $this->record->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        // Enlazar la auditoría temporal a la empresa y, si el responsable aceptó, registrar
        // la aceptación con sus datos y disparar la generación + adopción del RIT mejorado.
        if ($this->auditoria) {
            $svcAud = app(AuditoriaRITService::class);
            $svcAud->enlazarConEmpresa($this->auditoria, $this->record);
            $this->auditoria = $this->auditoria->fresh();

            if (($this->data['rit_decision'] ?? null) === 'aceptar') {
                $aceptacion = app(AceptacionMejoraRITService::class);
                $aceptacion->registrarAceptacion($this->auditoria, [
                    'responsable_nombre'    => $this->data['rit_resp_nombre'] ?? null,
                    'responsable_documento' => $this->data['rit_resp_documento'] ?? null,
                    'responsable_cargo'     => $this->data['rit_resp_cargo'] ?? null,
                ]);
                $aceptacion->dispararMejora($this->auditoria->fresh(), (int) auth()->id());
            } elseif (($this->data['rit_decision'] ?? null) === 'mantener') {
                app(AceptacionMejoraRITService::class)->mantenerActual($this->auditoria);
            }
        }
    }

    protected function getRedirectUrl(): string
    {
        $opcion = $this->data['rit_opcion'] ?? null;

        if ($this->record->requiereRit() === true && ! in_array($opcion, ['tiene', 'construir'], true)) {
            return route('filament.admin.pages.mi-reglamento-interno');
        }

        return match ($opcion) {
            'tiene'     => route('filament.admin.pages.auditar-r-i-t'),
            'construir' => route('filament.admin.pages.mi-reglamento-interno'),
            default     => $this->getResource()::getUrl('index'),
        };
    }
}
