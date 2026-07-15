<?php

namespace App\Filament\Admin\Resources\EmpresaResource\Pages;

use App\Filament\Admin\Resources\EmpresaResource;
use App\Jobs\ProcesarAuditoriaRIT;
use App\Models\AuditoriaRIT;
use App\Services\AceptacionMejoraRITService;
use App\Services\AuditoriaRITService;
use App\Services\ReglamentoInternoService;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
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

        // Campo de gate: no es columna de empresas.
        unset($data['auditoria_confirmada']);

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

                    // Gate: obligatorio solo si subió RIT ('tiene'). Se llena al decidir
                    // (o de forma fail-safe si la auditoría no pudo correr).
                    Hidden::make('auditoria_confirmada')
                        ->dehydrated(false)
                        ->required(fn (Get $get) => ($get('rit_opcion') ?? null) === 'tiene'),
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

    /** Marca el gate para permitir crear cuando no hay auditoría posible. */
    private function permitirCrearSinAuditoria(): void
    {
        $this->data['auditoria_confirmada'] = 'sin_auditoria';
    }

    /** Acción "Aceptar sugerencias": captura autoridad + responsable y habilita crear. */
    public function aceptarMejoraWizardAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('aceptarMejoraWizard')
            ->label('Actualizar RIT con las sugerencias')
            ->icon('heroicon-o-sparkles')
            ->color('primary')
            ->modalHeading('Actualizar el Reglamento Interno con las sugerencias')
            ->modalDescription('Al crear la empresa se generará la versión mejorada del RIT y se adoptará como vigente. Registramos quién autoriza el cambio.')
            ->modalSubmitActionLabel('Confirmar')
            ->form([
                Placeholder::make('aviso')->hiddenLabel()
                    ->content('Debe ser aprobado por alguien con autoridad para modificar el RIT de la empresa.'),
                Checkbox::make('autoridad_declarada')
                    ->label('Declaro que tengo la autoridad para aprobar cambios al Reglamento Interno de esta empresa.')
                    ->accepted()->required(),
                TextInput::make('responsable_nombre')->label('Nombre completo')->required()->maxLength(255),
                TextInput::make('responsable_documento')->label('Documento (cédula)')->required()->maxLength(50),
                TextInput::make('responsable_cargo')->label('Cargo')->required()->maxLength(255),
            ])
            ->action(function (array $data): void {
                if ($this->auditoria) {
                    app(AceptacionMejoraRITService::class)->registrarAceptacion($this->auditoria, $data);
                    $this->auditoria = $this->auditoria->fresh();
                }
                $this->data['auditoria_confirmada'] = 'aceptado';
                Notification::make()->success()
                    ->title('Sugerencias aceptadas')
                    ->body('Al crear la empresa se generará y adoptará el RIT mejorado. Ya puede crear la empresa.')
                    ->send();
            });
    }

    /** El responsable decide mantener el RIT subido tal cual; habilita crear. */
    public function mantenerRITWizard(): void
    {
        if ($this->auditoria) {
            app(AceptacionMejoraRITService::class)->mantenerActual($this->auditoria);
            $this->auditoria = $this->auditoria->fresh();
        }
        $this->data['auditoria_confirmada'] = 'mantener';
        Notification::make()->info()->title('Conservará el RIT subido')->send();
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

        // Enlazar la auditoría temporal a la empresa y disparar la mejora si se aceptó.
        if ($this->auditoria) {
            $svcAud = app(AuditoriaRITService::class);
            $svcAud->enlazarConEmpresa($this->auditoria, $this->record);
            $this->auditoria = $this->auditoria->fresh();

            if ($this->auditoria->decision_mejora === 'adoptado') {
                app(AceptacionMejoraRITService::class)->dispararMejora($this->auditoria, (int) auth()->id());
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
