@php
    /**
     * Recap en vivo del wizard RIT.
     * Muestra de forma compacta lo diligenciado hasta el momento. Cada celda
     * aparece "completa" (valor + check) o "pendiente" (guion). Se alimenta de
     * los $get() del formulario, así que se refresca al navegar entre pasos y
     * cuando cambia cualquier campo reactivo (->live()).
     */
    $items = collect($items ?? [])->filter(fn($i) => !empty($i['label']));
@endphp

<div class="rit-recap-vivo grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
    @foreach ($items as $item)
        @php $done = !empty($item['value']); @endphp
        <div @class([
            'flex flex-col gap-0.5 rounded-lg border px-3 py-2 text-xs',
            'border-primary-200 bg-primary-50 dark:border-primary-500/30 dark:bg-primary-500/10' => $done,
            'border-gray-200 bg-gray-50/60 dark:border-white/10 dark:bg-white/5' => ! $done,
        ])>
            <span class="flex items-center gap-1 font-medium text-gray-500 dark:text-gray-400">
                @if ($done)
                    @svg('heroicon-m-check-circle', 'h-3.5 w-3.5 text-primary-500')
                @else
                    @svg('heroicon-m-minus-circle', 'h-3.5 w-3.5 text-gray-300 dark:text-gray-600')
                @endif
                {{ $item['label'] }}
            </span>
            <span @class([
                'truncate font-semibold',
                'text-gray-900 dark:text-white' => $done,
                'text-gray-400 dark:text-gray-600' => ! $done,
            ]) title="{{ $done ? $item['value'] : '' }}">
                {{ $done ? $item['value'] : 'Pendiente' }}
            </span>
        </div>
    @endforeach
</div>
