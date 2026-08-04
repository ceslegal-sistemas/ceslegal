{{-- Login split-screen LUPE - un solo nodo raíz (Livewire) --}}
<div class="ces-auth-root">

    <aside class="ces-auth-brand">
        <div>
            <div class="ces-auth-logo">CES</div>
            <div class="ces-auth-sub">LEGAL DIGITAL</div>
        </div>
        <div class="ces-auth-brand__bottom">
            <p class="ces-auth-tag">Procesos disciplinarios con respaldo constitucional.</p>
            <p class="ces-auth-cap">Cada decisión anclada en la Constitución, la jurisprudencia y el Código Sustantivo del Trabajo.</p>
        </div>
    </aside>

    <main class="ces-auth-main">
        <div class="ces-auth-card">
            <img src="{{ asset('images/lupe-logo.png') }}" alt="LUPE" class="ces-auth-logo-img">
            <h1 class="ces-auth-title">Bienvenido de vuelta</h1>
            <p class="ces-auth-lead">Ingrese a su panel de LUPE.</p>

            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE, scopes: $this->getRenderHookScopes()) }}

            <x-filament-panels::form id="form" wire:submit="authenticate">
                {{ $this->form }}

                <x-filament-panels::form.actions
                    :actions="$this->getCachedFormActions()"
                    :full-width="$this->hasFullWidthFormActions()"
                />
            </x-filament-panels::form>

            @if (filament()->hasRegistration())
                <p class="ces-auth-foot">
                    {{ __('filament-panels::pages/auth/login.actions.register.before') }}
                    {{ $this->registerAction }}
                </p>
            @endif

            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, scopes: $this->getRenderHookScopes()) }}
        </div>
    </main>

</div>
