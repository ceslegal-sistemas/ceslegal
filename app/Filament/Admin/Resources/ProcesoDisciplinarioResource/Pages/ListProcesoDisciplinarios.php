<?php

namespace App\Filament\Admin\Resources\ProcesoDisciplinarioResource\Pages;

use App\Filament\Admin\Resources\ProcesoDisciplinarioResource;
use App\Models\Feedback;
use Filament\Actions;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\View\View;

class ListProcesoDisciplinarios extends ListRecords
{
    protected static string $resource = ProcesoDisciplinarioResource::class;

    public ?int $feedbackProcesoId = null;
    public bool $mostrarFeedbackAutomatico = false;

    public function mount(): void
    {
        parent::mount();

        // Verificar si hay feedback pendiente en sesión
        $feedbackData = session()->pull('mostrar_feedback');
        if ($feedbackData) {
            $this->feedbackProcesoId = $feedbackData['proceso_id'] ?? null;
            $this->mostrarFeedbackAutomatico = true;
        }
    }

    public function abrirModalFeedback(): void
    {
        $this->mountAction('feedback');
    }

    public function getFooter(): ?View
    {
        return view('filament.pages.list-procesos-footer', [
            'mostrarFeedback' => $this->mostrarFeedbackAutomatico,
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('feedback')
                ->label('Feedback')
                ->icon('heroicon-o-star')
                ->color('warning')
                ->modalHeading('¡Tu opinión es importante!')
                ->modalDescription('Ayúdanos a mejorar nuestra plataforma')
                ->modalIcon('heroicon-o-star')
                ->modalWidth('md')
                ->form([
                    Radio::make('calificacion')
                        ->label('¿Cómo calificarías tu experiencia?')
                        ->options([
                            '5' => '⭐⭐⭐⭐⭐ Excelente',
                            '4' => '⭐⭐⭐⭐ Bueno',
                            '3' => '⭐⭐⭐ Regular',
                            '2' => '⭐⭐ Malo',
                            '1' => '⭐ Muy malo',
                        ])
                        ->required()
                        ->inline()
                        ->inlineLabel(false),
                    Textarea::make('sugerencia')
                        ->label('¿Tienes alguna sugerencia para nosotros?')
                        ->placeholder('Escribe aquí tus comentarios, ideas o sugerencias...')
                        ->rows(3)
                        ->maxLength(2000),
                ])
                ->modalSubmitActionLabel('Enviar opinión')
                ->modalCancelActionLabel('Omitir')
                ->action(function (array $data) {
                    Feedback::create([
                        'calificacion' => (int) $data['calificacion'],
                        'sugerencia' => $data['sugerencia'] ?? null,
                        'tipo' => Feedback::TIPO_PLATAFORMA_GENERAL,
                        'proceso_disciplinario_id' => $this->feedbackProcesoId,
                        'user_id' => auth()->id(),
                        'ip_address' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                    ]);

                    $this->feedbackProcesoId = null;

                    Notification::make()
                        ->success()
                        ->title('¡Gracias por tu opinión!')
                        ->body('Tu feedback nos ayuda a mejorar constantemente.')
                        ->send();
                })
                ->closeModalByClickingAway(false),

            Actions\Action::make('tutorial')
                ->label('¿Cómo funciona?')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->extraAttributes([
                    'data-tour' => 'help-button',
                    'onclick' => 'window.iniciarTour(); return false;',
                ]),

            Actions\CreateAction::make()
                ->icon('heroicon-o-plus-circle')
                ->extraAttributes([
                    'data-tour' => 'create-button',
                ]),
        ];
    }

    /**
     * Filtrar procesos disciplinarios según el rol del usuario:
     * - Super Admin: ve TODOS los procesos
     * - Abogado: ve TODOS los procesos
     * - Cliente: ve SOLO procesos de su empresa
     */
    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();
        $user = auth()->user();

        if ($user->role === 'cliente' && $user->empresa_id) {
            $query->where('empresa_id', $user->empresa_id);
        }

        return $query;
    }
}
