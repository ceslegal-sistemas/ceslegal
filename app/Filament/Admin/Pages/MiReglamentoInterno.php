<?php

namespace App\Filament\Admin\Pages;

use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class MiReglamentoInterno extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Mi Reglamento Interno';
    protected static ?string $navigationGroup = 'Empresa';
    protected static ?int    $navigationSort  = 10;
    protected static string  $view            = 'filament.pages.mi-reglamento-interno';

    public ?ReglamentoInterno $reglamento = null;
    public ?Empresa $empresa = null;

    public function mount(): void
    {
        $user = Auth::user();
        if (!$user) {
            $this->redirect(route('filament.admin.pages.dashboard'));
            return;
        }

        $this->empresa = ($user->hasRole('super_admin') || $user->hasRole('abogado'))
            ? Empresa::first()
            : ($user->empresa ?? null);

        if ($this->empresa) {
            $this->reglamento = ReglamentoInterno::where('empresa_id', $this->empresa->id)
                ->orderByDesc('updated_at')
                ->first();
        }
    }

    public function getTitle(): string
    {
        return 'Reglamento Interno de Trabajo';
    }

    public static function canAccess(): bool
    {
        return Auth::check();
    }
}
