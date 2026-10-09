@php
    // Mismas pestañas nativas que usa "Feedback" (icono + contador por color).
    $conteos = $this->getConteos();
    $secciones = [
        'todos' => ['Trazabilidad completa', 'heroicon-o-squares-2x2', 'primary'],
        'sanciones' => ['Sanciones / Incidentes', 'heroicon-o-exclamation-triangle', 'danger'],
        'rit' => ['Autorización del Reglamento', 'heroicon-o-document-check', 'info'],
        'descargos' => ['Descargos', 'heroicon-o-chat-bubble-left-right', 'warning'],
        'trabajadores' => ['Aceptación de trabajadores', 'heroicon-o-user-group', 'success'],
        'videos' => ['Videos del Reglamento', 'heroicon-o-play-circle', 'gray'],
    ];
@endphp

<x-filament-panels::page>
    <x-filament::tabs label="Secciones del reporte" class="fi-resource-tabs">
        @foreach ($secciones as $clave => [$titulo, $icono, $color])
            <x-filament::tabs.item
                :active="$seccion === $clave"
                :icon="$icono"
                :badge="$conteos[$clave]"
                :badge-color="$color"
                wire:click="cambiarSeccion('{{ $clave }}')"
            >
                {{ $titulo }}
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>

    {{ $this->table }}
</x-filament-panels::page>
