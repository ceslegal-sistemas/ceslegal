@php
    use Filament\Support\Facades\FilamentView;

    $color = $this->getColor();
    $heading = $this->getHeading();
    $description = $this->getDescription();
    $filters = $this->getFilters();

    // Obtener datos para los botones
    $estadosData = $this->getEstadosParaBotones();
    $baseUrl = route('filament.admin.resources.proceso-disciplinarios.index');
@endphp

<x-filament-widgets::widget class="fi-wi-chart">
    <x-filament::section :description="$description" :heading="$heading">
        @if ($filters)
            <x-slot name="headerEnd">
                <x-filament::input.wrapper
                    inline-prefix
                    wire:target="filter"
                    class="w-max sm:-my-2"
                >
                    <x-filament::input.select
                        inline-prefix
                        wire:model.live="filter"
                    >
                        @foreach ($filters as $value => $label)
                            <option value="{{ $value }}">
                                {{ $label }}
                            </option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </x-slot>
        @endif

        <div
            @if ($pollingInterval = $this->getPollingInterval())
                wire:poll.{{ $pollingInterval }}="updateChartData"
            @endif
        >
            <div
                @if (FilamentView::hasSpaMode())
                    x-load="visible"
                @else
                    x-load
                @endif
                x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('chart', 'filament/widgets') }}"
                wire:ignore
                x-data="chart({
                            cachedData: @js($this->getCachedData()),
                            options: @js($this->getOptions()),
                            type: @js($this->getType()),
                        })"
                @class([
                    match ($color) {
                        'gray' => null,
                        default => 'fi-color-custom',
                    },
                    is_string($color) ? "fi-color-{$color}" : null,
                ])
            >
                <canvas
                    x-ref="canvas"
                    @if ($maxHeight = $this->getMaxHeight())
                        style="max-height: {{ $maxHeight }}"
                    @endif
                ></canvas>

                <span
                    x-ref="backgroundColorElement"
                    @class([
                        match ($color) {
                            'gray' => 'text-gray-100 dark:text-gray-800',
                            default => 'text-custom-50 dark:text-custom-400/10',
                        },
                    ])
                    @style([
                        \Filament\Support\get_color_css_variables(
                            $color,
                            shades: [50, 400],
                            alias: 'widgets::chart-widget.background',
                        ) => $color !== 'gray',
                    ])
                ></span>

                <span
                    x-ref="borderColorElement"
                    @class([
                        match ($color) {
                            'gray' => 'text-gray-400',
                            default => 'text-custom-500 dark:text-custom-400',
                        },
                    ])
                    @style([
                        \Filament\Support\get_color_css_variables(
                            $color,
                            shades: [400, 500],
                            alias: 'widgets::chart-widget.border',
                        ) => $color !== 'gray',
                    ])
                ></span>

                <span
                    x-ref="gridColorElement"
                    class="text-gray-200 dark:text-gray-800"
                ></span>

                <span
                    x-ref="textColorElement"
                    class="text-gray-500 dark:text-gray-400"
                ></span>
            </div>
        </div>

        {{-- Botones clicables por estado --}}
        @if (!empty($estadosData))
            <div class="mt-3 pt-3 border-t border-gray-100 dark:border-white/5">
                <div class="flex flex-wrap gap-2 justify-center">
                    @foreach ($estadosData as $estado)
                        <a
                            href="{{ $baseUrl }}?tableFilters[estado][values][0]={{ $estado['key'] }}"
                            wire:navigate
                            class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-medium ring-1 ring-inset transition-all duration-150 hover:scale-105 hover:shadow-sm"
                            style="
                                background-color: {{ $estado['color'] }}15;
                                color: {{ $estado['color'] }};
                                ring-color: {{ $estado['color'] }}30;
                            "
                            onmouseover="this.style.backgroundColor='{{ $estado['color'] }}25'"
                            onmouseout="this.style.backgroundColor='{{ $estado['color'] }}15'"
                        >
                            <span class="w-2 h-2 rounded-full" style="background-color: {{ $estado['color'] }}"></span>
                            <span>{{ $estado['label'] }}</span>
                            <span class="font-bold">{{ $estado['count'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
