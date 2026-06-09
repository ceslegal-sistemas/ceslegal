{{--
    Override de husam-tariq/filament-timepicker.

    Bug original: el formateador interno de mdtimepicker usa el regex
    /(hh|h|mm|ss|tt|t)/g SIN flag de mayus/minus, por lo que solo reconoce
    tokens en minuscula. El token 'hh' produce hora 24h con padding (0-23).
    Configurar 'HH:mm' (mayuscula) deja el "HH" sin reemplazar y devuelve
    literalmente "HH:30", valor invalido para <input type="time"> -> el campo
    quedaba en blanco tras seleccionar.

    Ademas, mdtimepicker escribe en el input usando 'format' (no 'timeFormat'),
    asi que 'format' tambien debe ser 24h valido o el campo se vacia al recargar
    el paso. Por eso ambos van en minuscula 'hh:mm' (24h con padding).
--}}
@php
    $isPrefixInline = $isPrefixInline();
    $isSuffixInline = $isSuffixInline();
    $prefixActions = $getPrefixActions();
    $prefixIcon = $getPrefixIcon();
    $prefixLabel = $getPrefixLabel();
    $suffixActions = $getSuffixActions();
    $suffixIcon = $getSuffixIcon();
    $suffixLabel = $getSuffixLabel();
    $isDisabled = $isDisabled();

    // <input type="time"> exige formato HH:mm (24h).
    // El valor almacenado en Livewire ya viene en H:i o H:i:s — lo normalizamos a HH:mm.
    $raw = $getState() ?? '';
    $initVal = '';
    if ($raw) {
        foreach (['H:i:s', 'H:i', 'G:i:s', 'G:i'] as $_fmt) {
            try {
                $initVal = \Carbon\Carbon::createFromFormat($_fmt, $raw)->format('H:i');
                break;
            } catch (\Throwable $_e) {}
        }
    }
@endphp
<x-dynamic-component :component="$getFieldWrapperView()" :id="$getId()" :label="$getLabel()" :label-sr-only="$isLabelHidden()" :helper-text="$getHelperText()"
    :hint="$getHint()" :hint-icon="$getHintIcon()" :required="$isRequired()" :state-path="$getStatePath()" :field="$field">

    <x-filament::input.wrapper
        :disabled="$isDisabled"
        :inline-prefix="$isPrefixInline"
        :inline-suffix="$isSuffixInline"
        :prefix="$prefixLabel"
        :prefix-actions="$prefixActions"
        :prefix-icon="$prefixIcon"
        :prefix-icon-color="$getPrefixIconColor()"
        :suffix="$suffixLabel"
        :suffix-actions="$getSuffixActions()"
        :suffix-icon="$suffixIcon"
        :suffix-icon-color="$getSuffixIconColor()"
        :valid="! $errors->has($getStatePath())"
        :attributes="\Filament\Support\prepare_inherited_attributes($getExtraAttributeBag())"
    >
        <input {{ $isDisabled ? 'disabled' : '' }} type="time"
            value="{{ $initVal }}"
            x-data="{}"
            x-init="
                let _wire = $wire;
                let _el   = $el;
                let _path = '{{ $getStatePath() }}';
                $nextTick(function () {
                    mdtimepicker(_el, {
                        okLabel:     '{{ $getOkLabel() }}',
                        cancelLabel: '{{ $getCancelLabel() }}',
                        // Minuscula obligatoria: 'hh' = 24h con padding. 'HH' no lo
                        // reconoce el regex interno y devolveria "HH:mm" literal.
                        format:      'hh:mm',
                        timeFormat:  'hh:mm',
                        events: {
                            timeChanged: function (data, timepicker) {
                                _el.value = data.time;
                                _wire.set(_path, data.time);
                            }
                        }
                    });
                });
            "
            {{ $applyStateBindingModifiers('wire:model') }}="{{ $getStatePath() }}" @class([
                'time-input-picker fi-input block w-full border-none bg-transparent text-base text-gray-950 outline-none transition duration-75 placeholder:text-gray-400 focus:ring-0 disabled:text-gray-500 disabled:[-webkit-text-fill-color:theme(colors.gray.500)] disabled:placeholder:[-webkit-text-fill-color:theme(colors.gray.400)] dark:text-white dark:placeholder:text-gray-500 dark:disabled:text-gray-400 dark:disabled:[-webkit-text-fill-color:theme(colors.gray.400)] dark:disabled:placeholder:[-webkit-text-fill-color:theme(colors.gray.500)] sm:text-sm sm:leading-6',
            ])>
    </x-filament::input.wrapper>
</x-dynamic-component>
