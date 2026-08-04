{{-- Register split-screen LUPE - un solo nodo raíz (Livewire) --}}
<div class="ces-auth-root">

    <aside class="ces-auth-brand">
        <div>
            <div class="ces-auth-logo">LUPE</div>
        </div>
        <div class="ces-auth-brand__bottom">
            <p class="ces-auth-tag">Procesos disciplinarios con respaldo constitucional.</p>
            <p class="ces-auth-cap">Cada decisión anclada en la Constitución, la jurisprudencia y el Código Sustantivo del Trabajo.</p>
        </div>
    </aside>

    <main class="ces-auth-main">
        <div class="ces-auth-card ces-auth-card--wide">
            <img src="{{ asset('images/lupe-logo.png') }}" alt="LUPE" class="ces-auth-logo-img">
            <h1 class="ces-auth-title">Cree su cuenta</h1>
            <p class="ces-auth-lead">Configure su empresa para empezar a operar en LUPE.</p>

            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_REGISTER_FORM_BEFORE, scopes: $this->getRenderHookScopes()) }}

            <x-filament-panels::form id="form" wire:submit="register">
                {{ $this->form }}

                <x-filament-panels::form.actions
                    :actions="$this->getCachedFormActions()"
                    :full-width="$this->hasFullWidthFormActions()"
                />
            </x-filament-panels::form>

            @if (filament()->hasLogin())
                <p class="ces-auth-foot">
                    {{ __('filament-panels::pages/auth/register.actions.login.before') }}
                    {{ $this->loginAction }}
                </p>
            @endif

            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_REGISTER_FORM_AFTER, scopes: $this->getRenderHookScopes()) }}
        </div>
    </main>

</div>
