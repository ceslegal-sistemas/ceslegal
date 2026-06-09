{{--
    Override de husam-tariq/filament-timepicker.

    Se mantiene la MISMA estructura que la vista original del paquete
    (x-data="mdtimepicker($refs.timePicker, {...})") para que el reloj
    aparezca igual que siempre. Solo se ajustan dos cosas:

    1) format / timeFormat en minuscula 'hh:mm'. El formateador interno usa
       el regex /(hh|h|mm|ss|tt|t)/g SIN flag de mayus/minus: 'hh' (minuscula)
       devuelve hora 24h con padding (0-23) — valor valido para <input
       type="time">. 'HH' (mayuscula) NO lo reconoce y devolveria "HH:mm"
       literal, dejando el campo en blanco.

    2) value="{{ ... }}" inicial normalizado a H:i, para que al volver al paso
       el campo muestre la hora ya guardada.

    No usar comentarios // dentro del atributo x-data: si el HTML colapsa los
    saltos de linea, el // comenta el resto de la config y mdtimepicker no
    se inicializa (no aparece el reloj).
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

    // <input type="time"> exige HH:mm (24h). El valor guardado puede venir como
    // H:i o H:i:s — lo normalizamos a H:i para el value inicial.
    $raw = $getState() ?? '';
    $initVal = '';
    if ($raw) {
        foreach (['H:i:s', 'H:i', 'G:i:s', 'G:i'] as $_fmt) {
            try {
                $initVal = \Carbon\Carbon::createFromFormat($_fmt, $raw)->format('H:i');
                break;
            } catch (\Throwable $_e) {
            }
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
        :suffix-actions="$suffixActions"
        :suffix-icon="$suffixIcon"
        :suffix-icon-color="$getSuffixIconColor()"
        :valid="! $errors->has($getStatePath())"
        :attributes="\Filament\Support\prepare_inherited_attributes($getExtraAttributeBag())"
    >
        <input {{ $isDisabled ? 'disabled' : '' }} type="time" x-ref="timePicker"
            value="{{ $initVal }}"
            x-data="mdtimepicker($refs.timePicker, {
                okLabel: '{{ $getOkLabel() }}',
                cancelLabel: '{{ $getCancelLabel() }}',
                format: 'hh:mm',
                timeFormat: 'hh:mm',
                events: {
                    timeChanged: function(data, timepicker) {
                        @this.set('{!! $getStatePath() !!}', data.time);
                    },
                }
            })"
            {{ $applyStateBindingModifiers('wire:model') }}="{{ $getStatePath() }}" @class([
                'time-input-picker fi-input block w-full border-none bg-transparent text-base text-gray-950 outline-none transition duration-75 placeholder:text-gray-400 focus:ring-0 disabled:text-gray-500 disabled:[-webkit-text-fill-color:theme(colors.gray.500)] disabled:placeholder:[-webkit-text-fill-color:theme(colors.gray.400)] dark:text-white dark:placeholder:text-gray-500 dark:disabled:text-gray-400 dark:disabled:[-webkit-text-fill-color:theme(colors.gray.400)] dark:disabled:placeholder:[-webkit-text-fill-color:theme(colors.gray.500)] sm:text-sm sm:leading-6',
            ])>
    </x-filament::input.wrapper>
</x-dynamic-component>
