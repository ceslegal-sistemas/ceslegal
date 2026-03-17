{{--
    Chatbot conversacional — UI nativa Filament, compatible light/dark.
    Props: $conversacion, $chatIniciado, $chatListo, $enviando, $datosExtraidos
--}}
<div class="flex flex-col overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
    style="height: 520px;" x-data="{
        scrollBottom() {
            const c = this.$refs.msgs;
            if (c) c.scrollTop = c.scrollHeight;
        }
    }" x-init="scrollBottom()"
    x-on:chatbot-actualizado.window="$nextTick(() => scrollBottom())">

    {{-- ┌─ CABECERA ─────────────────────────────────────────────────────────┐ --}}
    <div class="flex flex-shrink-0 items-center gap-3 bg-primary-600 px-4 py-3 dark:bg-primary-500">
        {{-- Icono del asistente --}}
        <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-white/20">
            <x-filament::icon icon="heroicon-m-sparkles" class="h-4 w-4 text-white" />
        </span>

        {{-- Título --}}
        <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold leading-none text-white">Asistente Legal CES</p>
            <p class="mt-0.5 text-xs text-primary-200 dark:text-primary-400">
                Documentación asistida por inteligencia artificial
            </p>
        </div>

        {{-- Badge de estado --}}
        @if ($chatListo)
            <x-filament::badge color="success" icon="heroicon-m-check-circle" class="flex-shrink-0">
                Completado
            </x-filament::badge>
        @elseif ($chatIniciado)
            <span
                class="inline-flex flex-shrink-0 items-center gap-1.5 rounded-full bg-white/15 px-2.5 py-1 text-xs text-primary-100">
                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-green-400"></span>
                En conversación
            </span>
        @endif
    </div>
    {{-- └────────────────────────────────────────────────────────────────────┘ --}}


    {{-- ┌─ ÁREA DE MENSAJES ─────────────────────────────────────────────────┐ --}}
    <div x-ref="msgs" class="flex-1 space-y-4 overflow-y-auto px-4 py-5 scroll-smooth">
        @if (!$chatIniciado)
            {{-- ── Estado inicial: pantalla de bienvenida ─────────────────── --}}
            <div class="flex h-full flex-col items-center justify-center px-4 text-center">
                <div
                    class="mb-5 flex h-16 w-16 items-center justify-center rounded-2xl bg-primary-50 ring-1 ring-primary-200 dark:bg-gray-800 dark:ring-white/10">
                    <x-filament::icon icon="heroicon-o-chat-bubble-left-right" class="h-8 w-8 text-primary-500" />
                </div>

                <p class="mb-1.5 text-base font-semibold text-gray-900 dark:text-white">
                    Documentación guiada por IA
                </p>
                <p class="mb-1 max-w-sm text-sm leading-relaxed text-gray-500 dark:text-gray-400">
                    El asistente le hará preguntas para documentar correctamente los hechos.
                    No necesita saber de leyes, solo cuente lo que ocurrió.
                </p>
                <p class="mb-7 text-xs text-gray-400 dark:text-gray-500">
                    <kbd
                        class="rounded-md bg-gray-100 px-1.5 py-0.5 font-mono text-[10px] ring-1 ring-gray-200 dark:bg-white/10 dark:ring-white/10">Enter</kbd>
                    para enviar &middot;
                    <kbd
                        class="rounded-md bg-gray-100 px-1.5 py-0.5 font-mono text-[10px] ring-1 ring-gray-200 dark:bg-white/10 dark:ring-white/10">Shift+Enter</kbd>
                    para nueva línea
                </p>
                <br></br>
                <x-filament::button type="button" wire:click="iniciarChatbot" wire:loading.attr="disabled"
                    wire:target="iniciarChatbot" icon="heroicon-m-sparkles">
                    <span wire:loading.remove wire:target="iniciarChatbot">Iniciar conversación</span>
                    <span wire:loading wire:target="iniciarChatbot">Iniciando...</span>
                </x-filament::button>
            </div>
        @else
            {{-- ── Historial de mensajes ──────────────────────────────────── --}}
            @foreach ($conversacion as $msg)
                @if ($msg['rol'] === 'ia')
                    {{-- Burbuja IA (izquierda) --}}
                    <div class="flex items-end gap-2.5">
                        <span
                            class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-primary-50 ring-1 ring-primary-200 dark:bg-gray-800 dark:ring-white/10">
                            <x-filament::icon icon="heroicon-m-sparkles"
                                class="h-3.5 w-3.5 text-primary-600 dark:text-primary-400" />
                        </span>
                        <div class="max-w-[78%] rounded-2xl rounded-bl-sm bg-gray-100 px-4 py-2.5 dark:bg-gray-800">
                            <p class="whitespace-pre-line text-sm leading-relaxed text-gray-800 dark:text-white">
                                {{ $msg['texto'] }}</p>
                        </div>
                    </div>
                @else
                    {{-- Burbuja usuario (derecha) --}}
                    <div class="flex items-end justify-end gap-2.5">
                        <div
                            class="max-w-[78%] rounded-2xl rounded-br-sm bg-primary-600 px-4 py-2.5 dark:bg-primary-500">
                            <p class="whitespace-pre-line text-sm leading-relaxed text-white">{{ $msg['texto'] }}</p>
                        </div>
                        <span
                            class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700">
                            <x-filament::icon icon="heroicon-m-user"
                                class="h-3.5 w-3.5 text-gray-500 dark:text-gray-400" />
                        </span>
                    </div>
                @endif
            @endforeach

            {{-- ── Indicador de escritura (typing dots) ──────────────────── --}}
            @if ($enviando)
                <div class="flex items-end gap-2.5">
                    <span
                        class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-primary-50 ring-1 ring-primary-200 dark:bg-gray-800 dark:ring-white/10">
                        <x-filament::icon icon="heroicon-m-sparkles"
                            class="h-3.5 w-3.5 text-primary-600 dark:text-primary-400" />
                    </span>
                    <div class="rounded-2xl rounded-bl-sm bg-gray-100 px-4 py-3.5 dark:bg-gray-800">
                        <div class="flex items-center gap-1">
                            <span class="h-2 w-2 animate-bounce rounded-full bg-gray-400 dark:bg-gray-500"
                                style="animation-delay: 0ms; animation-duration: 0.9s;"></span>
                            <span class="h-2 w-2 animate-bounce rounded-full bg-gray-400 dark:bg-gray-500"
                                style="animation-delay: 160ms; animation-duration: 0.9s;"></span>
                            <span class="h-2 w-2 animate-bounce rounded-full bg-gray-400 dark:bg-gray-500"
                                style="animation-delay: 320ms; animation-duration: 0.9s;"></span>
                        </div>
                    </div>
                </div>
            @endif
        @endif
    </div>
    {{-- └────────────────────────────────────────────────────────────────────┘ --}}


    {{-- ┌─ BARRA DE ENTRADA ─────────────────────────────────────────────────┐ --}}
    @if ($chatIniciado && !$chatListo)
        <div
            class="flex-shrink-0 border-t border-gray-200 bg-gray-50/80 px-3 py-3 dark:border-white/10 dark:bg-gray-950">
            <div class="flex items-end gap-2">

                {{-- Textarea envuelta con el wrapper nativo de Filament --}}
                <x-filament::input.wrapper class="flex-1">
                    <textarea wire:model.live="mensajeUsuarioActual" wire:loading.attr="disabled" wire:target="enviarMensajeChatbot"
                        rows="1" placeholder="Escriba su respuesta aquí..."
                        class="fi-input block w-full resize-none border-none bg-transparent px-3 py-2 text-sm text-gray-950 outline-none transition placeholder:text-gray-400 focus:ring-0 disabled:cursor-not-allowed disabled:opacity-60 dark:text-white dark:placeholder:text-gray-500"
                        style="max-height: 108px;" x-data="{}"
                        x-on:keydown.enter.prevent="if (!$event.shiftKey) $wire.enviarMensajeChatbot()"
                        x-on:input="$el.style.height = 'auto'; $el.style.height = (Math.min($el.scrollHeight, 108)) + 'px'"></textarea>
                </x-filament::input.wrapper>

                {{-- Botón de envío --}}
                <x-filament::icon-button type="button" wire:click="enviarMensajeChatbot" wire:loading.attr="disabled"
                    wire:target="enviarMensajeChatbot" icon="heroicon-m-paper-airplane" color="primary" size="lg"
                    label="Enviar mensaje" tooltip="Enviar (Enter)" />
            </div>

            <br>
            <p class="mt-1.5 text-center text-xs text-gray-400 dark:text-gray-500">
                <kbd class="rounded-md bg-gray-200 px-1.5 py-0.5 font-mono text-[10px] dark:bg-white/10">Enter</kbd>
                enviar &middot;
                <kbd
                    class="rounded-md bg-gray-200 px-1.5 py-0.5 font-mono text-[10px] dark:bg-white/10">Shift+Enter</kbd>
                nueva línea
            </p>
        </div>
    @elseif ($chatListo)
        {{-- ── Banner de completado ──────────────────────────────────────── --}}
        <div
            class="flex-shrink-0 border-t border-success-200 bg-success-50 px-4 py-3 dark:border-white/10 dark:bg-gray-800">
            <div class="flex items-center gap-2.5">
                <x-filament::icon icon="heroicon-m-check-circle"
                    class="h-5 w-5 flex-shrink-0 text-success-600 dark:text-white" />
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-success-800 dark:text-white">
                        Información documentada correctamente
                    </p>
                    @if (!empty($datosExtraidos['resumen']))
                        <p class="mt-0.5 truncate text-xs text-success-700 dark:text-gray-200">
                            {{ $datosExtraidos['resumen'] }}
                        </p>
                    @endif
                </div>
                <span
                    class="flex-shrink-0 text-xs font-medium text-success-600 dark:text-gray-200 whitespace-nowrap">
                    Continúe al paso siguiente →
                </span>
            </div>
        </div>
    @endif
    {{-- └────────────────────────────────────────────────────────────────────┘ --}}

</div>
