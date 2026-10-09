<x-filament-panels::page>
    @include('filament.components.lupe-hero-styles')
    @include('filament.components.documento-viewer-styles')

    <div style="display:flex;flex-wrap:wrap;gap:.5rem;">
        <button type="button" wire:click="cambiarSeccion('todos')"
            class="rit-btn {{ $seccion === 'todos' ? 'rit-btn-primary' : 'rit-btn-secondary' }}">
            Trazabilidad completa
        </button>
        <button type="button" wire:click="cambiarSeccion('sanciones')"
            class="rit-btn {{ $seccion === 'sanciones' ? 'rit-btn-primary' : 'rit-btn-secondary' }}">
            Sanciones / Incidentes
        </button>
        <button type="button" wire:click="cambiarSeccion('rit')"
            class="rit-btn {{ $seccion === 'rit' ? 'rit-btn-primary' : 'rit-btn-secondary' }}">
            Autorización de Reglamento de Trabajo
        </button>
        <button type="button" wire:click="cambiarSeccion('descargos')"
            class="rit-btn {{ $seccion === 'descargos' ? 'rit-btn-primary' : 'rit-btn-secondary' }}">
            Descargos
        </button>
        <button type="button" wire:click="cambiarSeccion('trabajadores')"
            class="rit-btn {{ $seccion === 'trabajadores' ? 'rit-btn-primary' : 'rit-btn-secondary' }}">
            Aceptación de trabajadores
        </button>
        <button type="button" wire:click="cambiarSeccion('videos')"
            class="rit-btn {{ $seccion === 'videos' ? 'rit-btn-primary' : 'rit-btn-secondary' }}">
            Videos del Reglamento
        </button>
    </div>

    <div style="margin-top:1.25rem;">
        @if($seccion === 'todos')
            @include('filament.admin.pages.partials.equivalente-funcional-trazabilidad', ['datos' => $this->eventos()])
        @else
            {{ $this->table }}
        @endif
    </div>
</x-filament-panels::page>
