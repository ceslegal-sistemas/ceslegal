<?php

namespace App\Filament\Admin\Pages;

use App\Models\Configuracion;
use App\Services\TerminacionContratoService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Valores legales nacionales (hoy solo el SMLMV vigente, usado por
 * TerminacionContratoService para la indemnización de contratos a término
 * indefinido - Art. 64 CST). Es un dato que afecta el cálculo de TODOS los
 * bufetes del sistema, por eso solo super_admin puede editarlo (pedido
 * explícito del usuario) - no es una configuración de bufete/empresa.
 */
class ParametrosLegales extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-scale';
    protected static ?string $navigationGroup = 'Configuración';
    protected static ?string $navigationLabel = 'Parámetros Legales';
    protected static ?string $title = 'Parámetros Legales';
    protected static ?string $slug = 'parametros-legales';
    protected static ?int $navigationSort = 51;
    protected static string $view = 'filament.admin.pages.parametros-legales';

    public ?array $data = [];

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'super_admin';
    }

    public function mount(): void
    {
        $this->form->fill([
            'smlmv_vigente' => Configuracion::obtener(TerminacionContratoService::CLAVE_SMLMV_VIGENTE),
        ]);
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Salario Mínimo Legal Mensual Vigente (SMLMV)')
                    ->icon('heroicon-o-banknotes')
                    ->description('Se usa para calcular la indemnización de contratos a término indefinido (Art. 64 CST) - actualícelo cada vez que el Gobierno Nacional expida el nuevo decreto de salario mínimo.')
                    ->schema([
                        // Mismo mecanismo de puntos de miles ya usado en
                        // 'salario_propuesto' (SolicitudContratoResource) - sin
                        // ->numeric() (fuerza <input type="number">, que rechaza
                        // el punto como separador de miles), con el mask $money
                        // de Livewire formateando en el cliente sin ida y vuelta
                        // al servidor.
                        Forms\Components\TextInput::make('smlmv_vigente')
                            ->label('SMLMV vigente')
                            ->required()
                            ->rule('numeric')
                            ->minValue(1)
                            ->mask(\Filament\Support\RawJs::make(<<<'JS'
                                $money($input, ',', '.', 0)
                            JS))
                            ->stripCharacters('.')
                            ->extraInputAttributes(['onkeydown' => "return !['-','+','e','E'].includes(event.key)"])
                            ->prefix('$')
                            ->placeholder('Ej: 1.423.500')
                            ->helperText('Valor del salario mínimo mensual vigente en Colombia, sin auxilio de transporte.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function guardar(): void
    {
        $datos = $this->form->getState();

        Configuracion::updateOrCreate(
            ['clave' => TerminacionContratoService::CLAVE_SMLMV_VIGENTE],
            [
                'valor' => (string) $datos['smlmv_vigente'],
                'tipo' => 'number',
                'categoria' => 'legal',
                'editable' => true,
            ]
        );

        Notification::make()
            ->title('SMLMV actualizado')
            ->success()
            ->send();
    }
}
