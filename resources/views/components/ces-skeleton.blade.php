@props([
    'type' => 'text',   // text | page | cards | table | form
    'rows' => 3,
    'cards' => 4,
])

{{-- Skeleton de carga con shimmer de marca. El CSS (.ces-sk*) se inyecta
     globalmente desde AdminPanelProvider, así que aquí solo va el markup.
     Uso típico con Livewire:
       <div wire:loading.delay wire:target="metodo"><x-ces-skeleton type="table" :rows="6" /></div>
       <div wire:loading.remove wire:target="metodo"> ...contenido real... </div> --}}
<div {{ $attributes->merge(['class' => 'ces-sk-component']) }} aria-busy="true" aria-live="polite">
    @switch($type)
        @case('page')
            <div class="ces-sk ces-sk-title ces-sk-w-40" style="margin-bottom:1.25rem"></div>
            <div class="ces-sk-stats">
                @for ($i = 0; $i < $cards; $i++)
                    <div class="ces-sk ces-sk-card"></div>
                @endfor
            </div>
            <div class="ces-sk-panel">
                <div class="ces-sk ces-sk-line ces-sk-w-30" style="margin-bottom:1rem"></div>
                @for ($i = 0; $i < $rows; $i++)
                    <div class="ces-sk-row">
                        <div class="ces-sk ces-sk-circle" style="width:2rem;height:2rem;flex:0 0 auto"></div>
                        <div class="ces-sk ces-sk-line ces-sk-w-30"></div>
                        <div class="ces-sk ces-sk-line ces-sk-w-40"></div>
                        <div class="ces-sk ces-sk-line ces-sk-w-25" style="margin-left:auto"></div>
                    </div>
                @endfor
            </div>
            @break

        @case('cards')
            <div class="ces-sk-stats">
                @for ($i = 0; $i < $cards; $i++)
                    <div class="ces-sk ces-sk-card"></div>
                @endfor
            </div>
            @break

        @case('table')
            <div class="ces-sk-panel">
                @for ($i = 0; $i < $rows; $i++)
                    <div class="ces-sk-row">
                        <div class="ces-sk ces-sk-line ces-sk-w-30"></div>
                        <div class="ces-sk ces-sk-line ces-sk-w-40"></div>
                        <div class="ces-sk ces-sk-line ces-sk-w-25" style="margin-left:auto"></div>
                    </div>
                @endfor
            </div>
            @break

        @case('form')
            <div style="display:flex;flex-direction:column;gap:1.1rem">
                @for ($i = 0; $i < $rows; $i++)
                    <div>
                        <div class="ces-sk ces-sk-line ces-sk-w-25 sm" style="margin-bottom:.5rem"></div>
                        <div class="ces-sk" style="height:2.6rem;border-radius:.6rem"></div>
                    </div>
                @endfor
                <div class="ces-sk ces-sk-btn" style="margin-top:.25rem"></div>
            </div>
            @break

        @default
            <div style="display:flex;flex-direction:column;gap:.6rem">
                <div class="ces-sk ces-sk-line ces-sk-w-85"></div>
                @for ($i = 0; $i < max(1, (int) $rows); $i++)
                    <div class="ces-sk ces-sk-line ces-sk-w-70"></div>
                @endfor
                <div class="ces-sk ces-sk-line ces-sk-w-55"></div>
            </div>
    @endswitch
</div>
