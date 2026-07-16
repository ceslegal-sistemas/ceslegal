<?php

namespace App\Filament\Admin\Resources\EmpresaResource\Pages;

use App\Filament\Admin\Resources\EmpresaResource;
use App\Jobs\ProcesarAuditoriaRIT;
use App\Models\User;
use App\Services\AuditoriaRITService;
use App\Services\ReglamentoInternoService;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class CreateEmpresa extends CreateRecord
{
    protected static string $resource = EmpresaResource::class;

    /**
     * Onboarding B2B: la empresa SIEMPRE se crea junto con el usuario que la administrará
     * (rol cliente). Se agrega una sección extra al formulario compartido de EmpresaResource,
     * solo en esta página (EditEmpresa conserva el formulario original de EmpresaResource).
     */
    public function form(Form $form): Form
    {
        return $form->schema([
            ...EmpresaResource::formSchema(),

            Section::make('Usuario administrador')
                ->description('Con estas credenciales el cliente ingresará a la plataforma para gestionar su Reglamento Interno y sus procesos disciplinarios.')
                ->icon('heroicon-o-user-plus')
                ->schema([
                    TextInput::make('user_nombre')
                        ->label('Nombre completo')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('user_email')
                        ->label('Correo electrónico')
                        ->email()
                        ->required()
                        ->unique(table: 'users', column: 'email')
                        ->maxLength(255)
                        ->helperText('Con este correo el cliente inicia sesión.'),

                    TextInput::make('user_password')
                        ->label('Contraseña')
                        ->password()
                        ->revealable()
                        ->required()
                        ->rule(Password::default())
                        ->same('user_password_confirmation'),

                    TextInput::make('user_password_confirmation')
                        ->label('Confirmar contraseña')
                        ->password()
                        ->revealable()
                        ->required()
                        ->dehydrated(false),
                ])
                ->columns(2),
        ]);
    }

    /**
     * Multi-tenant: si un abogado de bufete crea la empresa, queda vinculada a su bufete.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (auth()->user()?->esAbogadoDeBufete()) {
            $data['bufete_id'] = auth()->user()->bufete_id;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        // La empresa recién creada queda seleccionada como activa (para las herramientas de RIT).
        \App\Support\EmpresaActiva::set($this->record->id);

        // Usuario administrador de la empresa (rol cliente) — obligatorio en el onboarding B2B.
        $cliente = User::create([
            'name'       => $this->data['user_nombre'],
            'email'      => $this->data['user_email'],
            'password'   => Hash::make($this->data['user_password']),
            'role'       => 'cliente',
            'empresa_id' => $this->record->id,
            'active'     => true,
        ]);
        $cliente->assignRole('cliente');

        // Persistir el RIT subido (extrae texto + sanciones + conductas).
        // El estado del FileUpload puede venir como [uuid => ruta]; normalizar a string.
        $rawPath       = $this->data['reglamento_docx_temp'] ?? null;
        $path          = is_array($rawPath) ? (reset($rawPath) ?: null) : $rawPath;
        $ritConTexto   = false;
        if (is_string($path) && $path !== '') {
            try {
                $nombreArchivo  = basename($path);
                $rutaPermanente = 'reglamentos/' . $this->record->id . '/' . $nombreArchivo;
                Storage::disk('local')->move($path, $rutaPermanente);

                $rit = app(ReglamentoInternoService::class)->procesarDocumento(
                    storage_path("app/{$rutaPermanente}"),
                    $this->record->id,
                    $nombreArchivo,
                    $rutaPermanente,
                );
                $ritConTexto = !empty($rit->texto_completo);
            } catch (\Exception $e) {
                Log::error('Error al procesar reglamento interno en CreateEmpresa', [
                    'empresa_id' => $this->record->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        // Auto-auditar el RIT subido - MISMO flujo que "Mi Reglamento Interno": la auditoría
        // corre y, al completar, se genera automáticamente la versión mejorada, que se ofrece
        // en la vista unificada con la aceptación (autoridad + responsable).
        if (($this->data['rit_opcion'] ?? null) === 'tiene' && $ritConTexto) {
            $auditoria = app(AuditoriaRITService::class)->iniciar($this->record, null);
            ProcesarAuditoriaRIT::dispatch($auditoria, (int) auth()->id());

            Notification::make()
                ->success()
                ->title('Empresa creada - auditando su RIT')
                ->body('Estamos revisando el Reglamento Interno y generando la versión mejorada. Aquí mismo verá el resultado y podrá aceptar las sugerencias.')
                ->send();
        }
    }

    /**
     * Redirección según la rama de RIT elegida:
     *  - 'tiene' / 'construir' → "Mi Reglamento Interno" (audita, mejora y aceptación).
     *  - obligada sin cargar/construir → constructor.
     *  - otro/CST → listado de empresas.
     */
    protected function getRedirectUrl(): string
    {
        $opcion = $this->data['rit_opcion'] ?? null;

        if ($this->record->requiereRit() === true && ! in_array($opcion, ['tiene', 'construir'], true)) {
            return route('filament.admin.pages.mi-reglamento-interno');
        }

        return match ($opcion) {
            'tiene'     => route('filament.admin.pages.mi-reglamento-interno'),
            'construir' => route('filament.admin.pages.mi-reglamento-interno'),
            default     => $this->getResource()::getUrl('index'),
        };
    }
}
