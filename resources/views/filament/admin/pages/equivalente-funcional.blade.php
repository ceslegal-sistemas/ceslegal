@php
    $secciones = [
        'todos' => ['Trazabilidad completa', 'heroicon-m-shield-check'],
        'sanciones' => ['Sanciones / Incidentes', 'heroicon-m-exclamation-triangle'],
        'rit' => ['Autorización del Reglamento', 'heroicon-m-document-check'],
        'descargos' => ['Descargos', 'heroicon-m-chat-bubble-left-right'],
        'trabajadores' => ['Aceptación de trabajadores', 'heroicon-m-user-group'],
        'videos' => ['Videos del Reglamento', 'heroicon-m-play-circle'],
    ];
@endphp

<x-filament-panels::page>
    <x-filament::tabs label="Secciones del reporte">
        @foreach ($secciones as $clave => [$etiqueta, $icono])
            <x-filament::tabs.item
                :active="$seccion === $clave"
                :icon="$icono"
                wire:click="cambiarSeccion('{{ $clave }}')"
            >
                {{ $etiqueta }}
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>

    {{ $this->table }}
</x-filament-panels::page>
