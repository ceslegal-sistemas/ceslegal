@php
    // Mismas pestañas nativas que usa "Feedback" (icono + contador por color).
    $conteos = $this->getConteos();
    $secciones = [
        'todos' => ['Todos', 'heroicon-o-squares-2x2', 'primary'],
        'sanciones' => ['Sanciones', 'heroicon-o-exclamation-triangle', 'danger'],
        'rit' => ['Autorización RIT', 'heroicon-o-document-check', 'info'],
        'descargos' => ['Descargos', 'heroicon-o-chat-bubble-left-right', 'warning'],
        'trabajadores' => ['Aceptación RIT', 'heroicon-o-user-group', 'success'],
        'videos' => ['Videos', 'heroicon-o-play-circle', 'gray'],
    ];
@endphp

<x-filament-panels::page>
    <x-filament::tabs label="Secciones del reporte" style="margin-inline:auto;width:fit-content;max-width:100%">
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
