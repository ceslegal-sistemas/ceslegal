<?php

namespace App\Filament\Admin\Pages;

use App\Services\LogrosVitrinaService;
use Filament\Pages\Page;

/**
 * Vitrina de logros del cliente (pedido explícito del usuario, 2026-09-25):
 * hasta ahora la única forma de ver un logro era la tarjeta de progreso del
 * Dashboard (solo el siguiente pendiente) - esta página muestra TODOS los
 * logros existentes, obtenidos y en progreso, como medallas.
 */
class MisLogros extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-trophy';
    protected static ?string $navigationLabel = 'Mis Logros';
    protected static ?string $navigationGroup = 'Empresa';
    protected static ?int $navigationSort = 11;
    protected static string $view = 'filament.pages.mis-logros';

    public array $logros = [];

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->isCliente() ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        if (!auth()->user()?->isCliente()) {
            return null;
        }

        $empresa = auth()->user()?->empresa;
        if (!$empresa) {
            return null;
        }

        $completados = count(array_filter(
            app(LogrosVitrinaService::class)->paraEmpresa($empresa),
            fn (array $logro) => $logro['completado']
        ));

        return $completados > 0 ? (string) $completados : null;
    }

    public function mount(): void
    {
        $empresa = auth()->user()?->empresa;

        $this->logros = $empresa
            ? app(LogrosVitrinaService::class)->paraEmpresa($empresa)
            : [];
    }
}
