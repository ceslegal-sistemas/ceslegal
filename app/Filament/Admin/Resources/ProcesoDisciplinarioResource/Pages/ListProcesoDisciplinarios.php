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

    public ?int  $feedbackProcesoId         = null;
    public bool  $mostrarFeedbackAutomatico = false;

    public function mount(): void
    {
        parent::mount();

        // El super_admin no participa del feedback
        if (!auth()->check() || auth()->user()->role === 'super_admin') {
            return;
        }

        $user = auth()->user();

        // Procesos ya evaluados por este usuario
        $procesosConFeedback = Feedback::where('user_id', $user->id)
            ->whereNotNull('proceso_disciplinario_id')
            ->pluck('proceso_disciplinario_id');

        // Primer proceso completado que aún no tiene feedback de este usuario
        $q = ProcesoDisciplinario::whereIn('estado', [
            'descargos_realizados', 'descargos_no_realizados',
            'sancion_emitida', 'impugnacion_realizada', 'cerrado',
        ])->whereNotIn('id', $procesosConFeedback);

        if ($user->role === 'cliente' && $user->empresa_id) {
            $q->where('empresa_id', $user->empresa_id);
        }

        $proceso = $q->oldest('updated_at')->first();

        if ($proceso) {
            $this->feedbackProcesoId         = $proceso->id;
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

    // ── Header actions ────────────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        $page = $this;

        return [
            Actions\Action::make('feedback')
                ->label('Feedback')
                ->icon('heroicon-o-star')
                ->color('warning')
                ->visible(fn () => auth()->user()?->role !== 'super_admin')
                ->modalHeading('Diligencia completada — ¿Cómo te fue?')
                ->modalDescription('Tu opinión nos ayuda a mejorar. Todos los campos son obligatorios.')
                ->modalIcon('heroicon-o-document-check')
                ->modalWidth('lg')
                ->form([
                    Radio::make('calificacion')
                        ->label('¿Cómo calificarías la experiencia con este proceso?')
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

                    Radio::make('seguridad_juridica')
                        ->label('¿La plataforma te brindó seguridad jurídica en este proceso?')
                        ->options([
                            'si'           => 'Sí, completamente',
                            'parcialmente' => 'Parcialmente',
                            'no'           => 'No',
                        ])
                        ->required()
                        ->inline()
                        ->inlineLabel(false),

                    Radio::make('tiempo_ahorrado')
                        ->label('¿Cuánto tiempo aproximado te ahorró esta herramienta?')
                        ->options([
                            'menos_1h' => 'Menos de 1 hora',
                            '1_2h'     => 'Entre 1 y 2 horas',
                            'mas_2h'   => 'Más de 2 horas',
                            'no_se'    => 'No lo sé',
                        ])
                        ->required()
                        ->inline()
                        ->inlineLabel(false),

                    Radio::make('recomendaria')
                        ->label('¿Recomendarías CES Legal a otro profesional de RRHH?')
                        ->options([
                            'si_ya_lo_hice' => 'Sí, ya lo recomendé',
                            'si_lo_haria'   => 'Sí, lo recomendaría',
                            'tal_vez'       => 'Tal vez',
                            'no'            => 'No por ahora',
                        ])
                        ->required()
                        ->inline()
                        ->inlineLabel(false),

                    Textarea::make('sugerencia')
                        ->label('¿Qué mejoraría del proceso o de la plataforma?')
                        ->placeholder('Su sugerencia es muy valiosa para nosotros...')
                        ->required()
                        ->rows(3)
                        ->maxLength(2000),
                ])
                ->modalSubmitActionLabel('Enviar opinión')
                ->closeModalByClickingAway(false)
                ->closeModalByEscaping(false)
                ->modalCloseButton(false)
                ->modalCancelAction(false)
                ->action(function (array $data) use ($page) {
                    Feedback::create([
                        'calificacion'             => (int) $data['calificacion'],
                        'sugerencia'               => $data['sugerencia'],
                        'tipo'                     => Feedback::TIPO_PLATAFORMA_GENERAL,
                        'trigger'                  => Feedback::TRIGGER_POST_DILIGENCIA,
                        'proceso_disciplinario_id' => $page->feedbackProcesoId,
                        'user_id'                  => auth()->id(),
                        'ip_address'               => request()->ip(),
                        'user_agent'               => request()->userAgent(),
                        'respuestas_adicionales'   => [
                            'seguridad_juridica' => $data['seguridad_juridica'],
                            'tiempo_ahorrado'    => $data['tiempo_ahorrado'],
                            'recomendaria'       => $data['recomendaria'],
                        ],
                    ]);

                    $page->feedbackProcesoId         = null;
                    $page->mostrarFeedbackAutomatico = false;

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
                    'onclick'   => 'window.iniciarTour(); return false;',
                ]),

            Actions\CreateAction::make()
                ->icon('heroicon-o-plus-circle')
                ->extraAttributes([
                    'data-tour' => 'create-button',
                ]),
        ];
    }

    /**
     * Filtrar procesos según rol: Admin/Abogado ven todos, Cliente ve solo su empresa.
     */
    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();
        $user  = auth()->user();

        if ($user->role === 'cliente' && $user->empresa_id) {
            $query->where('empresa_id', $user->empresa_id);
        }

        return $query;
    }
}
