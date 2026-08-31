<x-filament-panels::page>
@include('filament.components.lupe-hero-styles')

<div class="rit-hero">
    <div class="rit-orb-b"></div>
    <div class="rit-orb-g"></div>
    <div class="rit-overlay"></div>
    <div style="position:relative;z-index:2">
        <span class="rit-badge rit-badge-ia">Nueva Solicitud</span>
        <h1 class="rit-title">Solicitud de Contrato</h1>
        <p class="rit-sub">Complete los 4 pasos para generar el contrato automáticamente con IA.</p>
    </div>
</div>

<div class="flex items-center justify-center gap-2 my-6">
    @foreach ([1 => 'Información Básica', 2 => 'Trabajador', 3 => 'Detalles del Cargo', 4 => 'Documentos'] as $n => $label)
        <div class="flex items-center gap-2 {{ $n < 4 ? 'flex-1' : '' }}">
            <div class="flex flex-col items-center gap-1">
                <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-semibold
                    {{ $paso === $n ? 'bg-primary-600 text-white' : ($paso > $n ? 'bg-success-500 text-white' : 'bg-gray-200 text-gray-500 dark:bg-gray-700') }}">
                    {{ $paso > $n ? '✓' : $n }}
                </div>
                <span class="text-xs text-gray-500 dark:text-gray-400 hidden sm:block">{{ $label }}</span>
            </div>
            @if ($n < 4)
                <div class="flex-1 h-0.5 {{ $paso > $n ? 'bg-success-500' : 'bg-gray-200 dark:bg-gray-700' }}"></div>
            @endif
        </div>
    @endforeach
</div>

<div class="space-y-6">
    @if ($paso === 1)
        @include('filament.pages.crear-solicitud-contrato.paso-1')
    @elseif ($paso === 2)
        @include('filament.pages.crear-solicitud-contrato.paso-2')
    @elseif ($paso === 3)
        @include('filament.pages.crear-solicitud-contrato.paso-3')
    @elseif ($paso === 4)
        @include('filament.pages.crear-solicitud-contrato.paso-4')
    @endif
</div>
</x-filament-panels::page>
