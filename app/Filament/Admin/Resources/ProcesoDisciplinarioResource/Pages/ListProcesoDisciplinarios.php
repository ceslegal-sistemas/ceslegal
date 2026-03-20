<?php

namespace App\Filament\Admin\Resources\ProcesoDisciplinarioResource\Pages;

use App\Filament\Admin\Resources\ProcesoDisciplinarioResource;
use App\Models\Feedback;
use App\Models\ProcesoDisciplinario;
use Filament\Actions;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\View\View;

class ListProcesoDisciplinarios extends ListRecords
{
    protected static string $resource = ProcesoDisciplinarioResource::class;

    public ?int   $feedbackProcesoId         = null;
    public bool   $mostrarFeedbackAutomatico = false;
    public string $feedbackTrigger           = Feedback::TRIGGER_PERIODICO;
    public ?int   $feedbackHitoNumero        = null;

    public function mount(): void
    {
        parent::mount();

        // Trigger directo por sesión (ej: redirección desde worker)
        $feedbackData = session()->pull('mostrar_feedback');
        if ($feedbackData) {
            $this->feedbackProcesoId        = $feedbackData['proceso_id'] ?? null;
            $this->feedbackTrigger          = $feedbackData['trigger'] ?? Feedback::TRIGGER_POST_DILIGENCIA;
            $this->mostrarFeedbackAutomatico = true;
            return;
        }

        if (!auth()->check()) {
            return;
        }

        $user = auth()->user();

        // ── TRIGGER 1: primer_proceso ─────────────────────────────────────────
        // Una sola vez en la vida del usuario, sin cooldown.
        $yaVioOnboarding = Feedback::where('user_id', $user->id)
            ->where('trigger', Feedback::TRIGGER_PRIMER_PROCESO)
            ->exists();

        if (!$yaVioOnboarding) {
            $q = ProcesoDisciplinario::whereNotIn('estado', ['apertura', 'archivado']);
            if ($user->role === 'cliente' && $user->empresa_id) {
                $q->where('empresa_id', $user->empresa_id);
            }
            if ($q->exists()) {
                $this->feedbackTrigger          = Feedback::TRIGGER_PRIMER_PROCESO;
                $this->mostrarFeedbackAutomatico = true;
                return;
            }
        }

        // ── TRIGGER 4: hito ───────────────────────────────────────────────────
        // Cada múltiplo de 5 procesos completados, sin cooldown.
        $qHito = ProcesoDisciplinario::whereIn('estado', [
            'descargos_realizados', 'descargos_no_realizados',
            'sancion_emitida', 'impugnacion_realizada', 'cerrado',
        ]);
        if ($user->role === 'cliente' && $user->empresa_id) {
            $qHito->where('empresa_id', $user->empresa_id);
        }
        $totalCompletados = $qHito->count();
        $hitosAlcanzados  = intdiv($totalCompletados, 5);
        $feedbacksHito    = Feedback::where('user_id', $user->id)
            ->where('trigger', Feedback::TRIGGER_HITO)
            ->count();

        if ($hitosAlcanzados > $feedbacksHito) {
            $this->feedbackTrigger          = Feedback::TRIGGER_HITO;
            $this->feedbackHitoNumero       = ($feedbacksHito + 1) * 5;
            $this->mostrarFeedbackAutomatico = true;
            return;
        }

        // ── Cooldown general de 14 días para triggers periódicos ──────────────
        $ultimoFeedback = Feedback::where('user_id', $user->id)
            ->latest('created_at')
            ->first();

        if ($ultimoFeedback && $ultimoFeedback->created_at->diffInDays(now()) < 14) {
            return;
        }

        // ── TRIGGER 2: post_diligencia ────────────────────────────────────────
        // Proceso recién llegado a descargos_realizados desde el último feedback.
        $qPost = ProcesoDisciplinario::where('estado', 'descargos_realizados');
        if ($ultimoFeedback) {
            $qPost->where('updated_at', '>', $ultimoFeedback->created_at);
        }
        if ($user->role === 'cliente' && $user->empresa_id) {
            $qPost->where('empresa_id', $user->empresa_id);
        }
        if ($qPost->exists()) {
            $this->feedbackTrigger          = Feedback::TRIGGER_POST_DILIGENCIA;
            $this->mostrarFeedbackAutomatico = true;
            return;
        }

        // ── TRIGGER 3: periódico ──────────────────────────────────────────────
        // Han pasado 14 días y el usuario tiene al menos un proceso avanzado.
        $qPeriodico = ProcesoDisciplinario::whereIn('estado', [
            'descargos_realizados', 'sancion_emitida', 'cerrado',
        ]);
        if ($user->role === 'cliente' && $user->empresa_id) {
            $qPeriodico->where('empresa_id', $user->empresa_id);
        }
        if ($qPeriodico->exists()) {
            $this->feedbackTrigger          = Feedback::TRIGGER_PERIODICO;
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

    // ── Helpers del modal ─────────────────────────────────────────────────────

    protected function getFeedbackModalHeading(): string
    {
        return match ($this->feedbackTrigger) {
            Feedback::TRIGGER_PRIMER_PROCESO  => '¡Completaste tu primer proceso disciplinario!',
            Feedback::TRIGGER_POST_DILIGENCIA => 'Diligencia completada — ¿Cómo te fue?',
            Feedback::TRIGGER_HITO            => "¡{$this->feedbackHitoNumero} procesos completados!",
            default                           => '¡Tu opinión es importante!',
        };
    }

    protected function getFeedbackModalDescription(): string
    {
        return match ($this->feedbackTrigger) {
            Feedback::TRIGGER_PRIMER_PROCESO  => 'Queremos saber cómo fue tu experiencia inicial con la plataforma.',
            Feedback::TRIGGER_POST_DILIGENCIA => 'Tu experiencia con este proceso nos ayuda a mejorar.',
            Feedback::TRIGGER_HITO            => 'Tu experiencia acumulada es muy valiosa para nosotros.',
            default                           => 'Ayúdanos a mejorar nuestra plataforma con tu opinión.',
        };
    }

    protected function getFeedbackModalIcon(): string
    {
        return match ($this->feedbackTrigger) {
            Feedback::TRIGGER_PRIMER_PROCESO  => 'heroicon-o-rocket-launch',
            Feedback::TRIGGER_POST_DILIGENCIA => 'heroicon-o-document-check',
            Feedback::TRIGGER_HITO            => 'heroicon-o-trophy',
            default                           => 'heroicon-o-star',
        };
    }

    protected function getFeedbackFormFields(): array
    {
        return match ($this->feedbackTrigger) {
            Feedback::TRIGGER_PRIMER_PROCESO  => $this->getFieldsPrimerProceso(),
            Feedback::TRIGGER_POST_DILIGENCIA => $this->getFieldsPostDiligencia(),
            Feedback::TRIGGER_HITO            => $this->getFieldsHito(),
            default                           => $this->getFieldsPeriodico(),
        };
    }

    // ── Campos por trigger ────────────────────────────────────────────────────

    private function getFieldsPrimerProceso(): array
    {
        return [
            Radio::make('calificacion')
                ->label('¿Cómo fue su experiencia al registrar los trabajadores y creando las citaciones a descargos en la aplicación?')
                ->options([
                    '5' => 'Muy buena',
                    '4' => 'Buena',
                    '2' => 'Mala',
                    '1' => 'Muy mala',
                ])
                ->required()
                ->inline()
                ->inlineLabel(false),

            Radio::make('dificultad_proceso')
                ->label('¿En qué parte del proceso tuvo más dificultad o confusión?')
                ->options([
                    'registro_trabajadores' => 'Registro de trabajadores',
                    'creacion_citacion'     => 'Creación citación a descargos',
                    'ninguna'               => 'Ninguna',
                    'todas'                 => 'Todas',
                ])
                ->required()
                ->inline()
                ->inlineLabel(false),

            Radio::make('facilidad_citacion')
                ->label('¿Le resultó fácil crear una citación para diligencia de descargos?')
                ->options([
                    'si' => 'Sí',
                    'no' => 'No',
                ])
                ->required()
                ->inline()
                ->inlineLabel(false)
                ->live(),

            Textarea::make('facilidad_citacion_porque')
                ->label('¿Por qué no le resultó fácil?')
                ->placeholder('Cuéntenos qué dificultad tuvo...')
                ->rows(2)
                ->maxLength(1000)
                ->visible(fn (\Filament\Forms\Get $get) => $get('facilidad_citacion') === 'no'),

            Textarea::make('mejora_sugerida')
                ->label('¿Qué mejoraría de la aplicación para que sea más fácil y rápida de usar?')
                ->placeholder('Su sugerencia es muy valiosa para nosotros...')
                ->required()
                ->rows(3)
                ->maxLength(2000),

            Radio::make('completo_sin_ayuda')
                ->label('¿Pudo completar todo el proceso de registro y citación sin ayuda?')
                ->options([
                    'si' => 'Sí',
                    'no' => 'No',
                ])
                ->required()
                ->inline()
                ->inlineLabel(false)
                ->live(),

            Textarea::make('completo_sin_ayuda_porque')
                ->label('¿En qué necesitó ayuda?')
                ->placeholder('Cuéntenos dónde tuvo dificultades...')
                ->rows(2)
                ->maxLength(1000)
                ->visible(fn (\Filament\Forms\Get $get) => $get('completo_sin_ayuda') === 'no'),
        ];
    }

    private function getFieldsPostDiligencia(): array
    {
        return [
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

            Radio::make('calidad_acta')
                ->label('¿El acta de descargos generada fue de calidad?')
                ->options([
                    'excelente' => 'Excelente',
                    'buena'     => 'Buena',
                    'mejorable' => 'Mejorable',
                    'no_use'    => 'No la usé',
                ])
                ->required()
                ->inline()
                ->inlineLabel(false),

            Textarea::make('sugerencia')
                ->label('Comentario adicional')
                ->placeholder('¿Algo más que quieras contarnos sobre este proceso?')
                ->required()
                ->rows(2)
                ->maxLength(2000),
        ];
    }

    private function getFieldsPeriodico(): array
    {
        return [
            Radio::make('calificacion')
                ->label('¿Cómo calificarías tu experiencia general con la plataforma?')
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

            Radio::make('nps_score')
                ->label('¿Qué tan probable es que recomiendes esta plataforma a otro profesional de RRHH?')
                ->helperText('0 = Nada probable  ·  10 = Muy probable')
                ->options([
                    '0' => '0', '1' => '1', '2' => '2', '3' => '3', '4' => '4',
                    '5' => '5', '6' => '6', '7' => '7', '8' => '8', '9' => '9', '10' => '10',
                ])
                ->required()
                ->inline()
                ->inlineLabel(false),

            Textarea::make('un_cambio')
                ->label('Si pudieras cambiar una sola cosa de la plataforma, ¿qué sería?')
                ->placeholder('Tu sugerencia más importante...')
                ->required()
                ->rows(3)
                ->maxLength(2000),

            Textarea::make('funcionalidad_faltante')
                ->label('¿Hay alguna funcionalidad que esperabas encontrar y no encontraste?')
                ->placeholder('Describe la funcionalidad que echas de menos...')
                ->required()
                ->rows(2)
                ->maxLength(2000),
        ];
    }

    private function getFieldsHito(): array
    {
        return [
            Radio::make('nps_score')
                ->label('¿Qué tan probable es que recomiendes esta plataforma a otro profesional de RRHH?')
                ->helperText('0 = Nada probable  ·  10 = Muy probable')
                ->options([
                    '0' => '0', '1' => '1', '2' => '2', '3' => '3', '4' => '4',
                    '5' => '5', '6' => '6', '7' => '7', '8' => '8', '9' => '9', '10' => '10',
                ])
                ->required()
                ->inline()
                ->inlineLabel(false),

            CheckboxList::make('aspectos_valorados')
                ->label('¿Qué aspectos valoras más de la plataforma? (Selecciona todos los que apliquen)')
                ->options([
                    'documentos_legales'  => 'Documentos legales automáticos',
                    'seguimiento_proceso' => 'Seguimiento del proceso',
                    'facilidad_uso'       => 'Facilidad de uso',
                    'ahorro_tiempo'       => 'Ahorro de tiempo',
                    'seguridad_juridica'  => 'Seguridad jurídica',
                    'otro'                => 'Otro',
                ])
                ->columns(2)
                ->required(),

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
                ->label('¿Algo que quieras agregar?')
                ->placeholder('Cualquier comentario o sugerencia es bienvenido...')
                ->required()
                ->rows(2)
                ->maxLength(2000),
        ];
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
                ->modalHeading(fn () => $page->getFeedbackModalHeading())
                ->modalDescription(fn () => $page->getFeedbackModalDescription())
                ->modalIcon(fn () => $page->getFeedbackModalIcon())
                ->modalWidth('lg')
                ->form(fn () => $page->getFeedbackFormFields())
                ->modalSubmitActionLabel('Enviar opinión')
                ->closeModalByClickingAway(false)
                ->closeModalByEscaping(false)
                ->modalCloseButton(false)
                ->modalCancelAction(false)
                ->action(function (array $data) use ($page) {
                    $trigger = $page->feedbackTrigger;

                    $respuestasAdicionales = match ($trigger) {
                        Feedback::TRIGGER_PRIMER_PROCESO => [
                            'dificultad_proceso'        => $data['dificultad_proceso'] ?? null,
                            'facilidad_citacion'        => $data['facilidad_citacion'] ?? null,
                            'facilidad_citacion_porque' => $data['facilidad_citacion_porque'] ?? null,
                            'mejora_sugerida'           => $data['mejora_sugerida'] ?? null,
                            'completo_sin_ayuda'        => $data['completo_sin_ayuda'] ?? null,
                            'completo_sin_ayuda_porque' => $data['completo_sin_ayuda_porque'] ?? null,
                        ],
                        Feedback::TRIGGER_POST_DILIGENCIA => [
                            'seguridad_juridica' => $data['seguridad_juridica'] ?? null,
                            'tiempo_ahorrado'    => $data['tiempo_ahorrado'] ?? null,
                            'calidad_acta'       => $data['calidad_acta'] ?? null,
                        ],
                        Feedback::TRIGGER_PERIODICO => [
                            'un_cambio'              => $data['un_cambio'] ?? null,
                            'funcionalidad_faltante' => $data['funcionalidad_faltante'] ?? null,
                        ],
                        Feedback::TRIGGER_HITO => [
                            'aspectos_valorados' => $data['aspectos_valorados'] ?? null,
                            'recomendaria'       => $data['recomendaria'] ?? null,
                        ],
                        default => [],
                    };

                    Feedback::create([
                        'calificacion'             => isset($data['calificacion']) ? (int) $data['calificacion'] : null,
                        'nps_score'                => isset($data['nps_score']) ? (int) $data['nps_score'] : null,
                        'sugerencia'               => $data['sugerencia'] ?? null,
                        'respuestas_adicionales'   => $respuestasAdicionales,
                        'tipo'                     => Feedback::TIPO_PLATAFORMA_GENERAL,
                        'trigger'                  => $trigger,
                        'proceso_disciplinario_id' => $page->feedbackProcesoId,
                        'user_id'                  => auth()->id(),
                        'ip_address'               => request()->ip(),
                        'user_agent'               => request()->userAgent(),
                    ]);

                    $page->feedbackProcesoId        = null;
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
