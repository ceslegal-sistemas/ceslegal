<?php

namespace App\Filament\Admin\Resources\ProcesoDisciplinarioResource\Pages;

use App\Filament\Admin\Resources\ProcesoDisciplinarioResource;
use App\Models\Feedback;
use App\Models\ProcesoDisciplinario;
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

        // Trigger directo por acción específica (session)
        $feedbackData = session()->pull('mostrar_feedback');
        if ($feedbackData) {
            $this->feedbackProcesoId = $feedbackData['proceso_id'] ?? null;
            $this->mostrarFeedbackAutomatico = true;
            return;
        }

        if (!auth()->check()) {
            return;
        }

        $user = auth()->user();

        // 1. Cooldown: no molestar si ya dio feedback en los últimos 14 días
        $ultimoFeedback = Feedback::where('user_id', $user->id)
            ->where('tipo', Feedback::TIPO_PLATAFORMA_GENERAL)
            ->latest('created_at')
            ->first();

        if ($ultimoFeedback && $ultimoFeedback->created_at->diffInDays(now()) < 14) {
            return;
        }

        // 2. Evento: verificar si hay procesos que pasaron a descargos_realizados
        //    desde el último feedback del usuario (o en total si nunca ha dado feedback)
        $query = ProcesoDisciplinario::where('estado', 'descargos_realizados');

        if ($ultimoFeedback) {
            $query->where('updated_at', '>', $ultimoFeedback->created_at);
        }

        // Clientes solo ven su empresa
        if ($user->role === 'cliente' && $user->empresa_id) {
            $query->where('empresa_id', $user->empresa_id);
        }

        $this->mostrarFeedbackAutomatico = $query->exists();
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
                ->closeModalByClickingAway(false)
                ->closeModalByEscaping(false)
                ->modalCloseButton(false)
                ->modalCancelAction(false)
                ->action(function (array $data) {
                    Feedback::create([
                        'calificacion' => (int) $data['calificacion'],
                        'sugerencia' => $data['sugerencia'] ?? null,
                        'tipo' => 'plataforma_general',
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
                }),

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
