<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\StatsOverviewWidget;
use App\Filament\Admin\Widgets\RecentProcessesWidget;
use App\Filament\Admin\Widgets\ExpiringTermsWidget;
use App\Filament\Admin\Widgets\ProcessesByStatusChart;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static string $view = 'filament.admin.pages.dashboard';

    protected static ?string $title = 'Panel de Control';

    protected static ?string $navigationLabel = 'Inicio';

    public function getWidgets(): array
    {
        return [
            StatsOverviewWidget::class,
            ProcessesByStatusChart::class,
            RecentProcessesWidget::class,
            ExpiringTermsWidget::class,
        ];
    }

    public function getColumns(): int | string | array
    {
        return [
            'md' => 2,
            'xl' => 3,
        ];
    }
}
