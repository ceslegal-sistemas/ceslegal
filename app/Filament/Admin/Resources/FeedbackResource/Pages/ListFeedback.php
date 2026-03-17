<?php

namespace App\Filament\Admin\Resources\FeedbackResource\Pages;

use App\Filament\Admin\Resources\FeedbackResource;
use App\Models\Feedback;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListFeedback extends ListRecords
{
    protected static string $resource = FeedbackResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            FeedbackResource\Widgets\FeedbackStatsWidget::class,
        ];
    }

    public function getTabs(): array
    {
        $totalTrabajadores = Feedback::where('tipo', Feedback::TIPO_DESCARGO_TRABAJADOR)->count();
        $totalClientes     = Feedback::where('tipo', Feedback::TIPO_DESCARGO_REGISTRO)->count();
        $totalSugerencias  = Feedback::whereNotNull('sugerencia')->count();
        $totalNps          = Feedback::whereNotNull('nps_score')->count();
        $totalNegativos    = Feedback::where('calificacion', '<=', 2)->count();

        return [
            'todos' => Tab::make('Todos')
                ->icon('heroicon-o-squares-2x2')
                ->badge(Feedback::count()),

            'trabajadores' => Tab::make('Trabajadores')
                ->icon('heroicon-o-user')
                ->badge($totalTrabajadores)
                ->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('tipo', Feedback::TIPO_DESCARGO_TRABAJADOR)),

            'clientes' => Tab::make('Clientes')
                ->icon('heroicon-o-briefcase')
                ->badge($totalClientes)
                ->badgeColor('primary')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('tipo', Feedback::TIPO_DESCARGO_REGISTRO)),

            'con_sugerencia' => Tab::make('Con comentario')
                ->icon('heroicon-o-chat-bubble-left-ellipsis')
                ->badge($totalSugerencias)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('sugerencia')),

            'nps' => Tab::make('Con NPS')
                ->icon('heroicon-o-chart-bar')
                ->badge($totalNps)
                ->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('nps_score')),

            'negativos' => Tab::make('Negativos')
                ->icon('heroicon-o-face-frown')
                ->badge($totalNegativos ?: null)
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('calificacion', '<=', 2)),
        ];
    }
}
