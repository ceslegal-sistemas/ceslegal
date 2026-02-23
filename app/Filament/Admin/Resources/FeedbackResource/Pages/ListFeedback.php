<?php

namespace App\Filament\Admin\Resources\FeedbackResource\Pages;

use App\Filament\Admin\Resources\FeedbackResource;
use Filament\Actions;
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
        return [
            'todos' => Tab::make('Todos')
                ->icon('heroicon-o-star'),
            'excelentes' => Tab::make('Excelentes')
                ->icon('heroicon-o-face-smile')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('calificacion', 5)),
            'buenos' => Tab::make('Buenos')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('calificacion', 4)),
            'regulares' => Tab::make('Regulares')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('calificacion', 3)),
            'negativos' => Tab::make('Negativos')
                ->icon('heroicon-o-face-frown')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('calificacion', '<=', 2)),
        ];
    }
}
