<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Widgets\Concerns\HasStatsSkeleton;
use App\Services\EquivalenteFuncionalService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class EquivalenteFuncionalStats extends BaseWidget
{
    use HasStatsSkeleton;

    protected function getStats(): array
    {
        $c = app(EquivalenteFuncionalService::class)->conteos();
        $n = fn (string $k) => $c[$k] ?? '-';

        return [
            Stat::make('Evidencias registradas', $n('todos'))
                ->description('Aceptaciones y autorizaciones')
                ->descriptionIcon('heroicon-o-shield-check')
                ->color('primary'),

            Stat::make('Con selfie de verificación', $n('con_selfie'))
                ->description('Identidad verificada')
                ->descriptionIcon('heroicon-o-camera')
                ->color('success'),

            Stat::make('Sanciones autorizadas', $n('sanciones'))
                ->description('Decisiones con responsable')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color('danger'),

            Stat::make('Reglamentos autorizados', $n('rit'))
                ->description('Versiones del RIT')
                ->descriptionIcon('heroicon-o-document-check')
                ->color('info'),

            Stat::make('Trabajadores que aceptaron', $n('trabajadores'))
                ->description('Socialización del RIT')
                ->descriptionIcon('heroicon-o-user-group')
                ->color('warning'),
        ];
    }
}
