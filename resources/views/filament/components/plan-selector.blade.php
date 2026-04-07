@php
    $planesData = [
        'basico' => [
            'mensual' => $planes['basico']['precio_mensual_cop'],
            'anual'   => $planes['basico']['precio_anual_cop'],
            'trial'   => $planes['basico']['trial_dias'],
        ],
        'pro' => [
            'mensual' => $planes['pro']['precio_mensual_cop'],
            'anual'   => $planes['pro']['precio_anual_cop'],
            'trial'   => 0,
        ],
        'firma' => [
            'mensual' => $planes['firma']['precio_mensual_cop'],
            'anual'   => $planes['firma']['precio_anual_cop'],
            'trial'   => 0,
        ],
    ];
@endphp

{{-- Lordicon script (carga una sola vez) --}}
<script>
    if (!window._liLoaded) {
        window._liLoaded = true;
        var s = document.createElement('script');
        s.src = 'https://cdn.lordicon.com/lordicon.js';
        document.head.appendChild(s);
    }
</script>

<div
    x-data="{
        ciclo: 'mensual',
        plan: 'basico',
        planes: @js($planesData),
        init() {
            this.$watch('plan',  v => $wire.set('data.plan_suscripcion',  v));
            this.$watch('ciclo', v => $wire.set('data.ciclo_facturacion', v));
        },
        fmt(n) {
            return '$' + n.toLocaleString('es-CO');
        },
        precio(key) {
            return this.ciclo === 'anual' ? this.planes[key].anual : this.planes[key].mensual;
        },
        periodo() {
            return this.ciclo === 'anual' ? 'año' : 'mes';
        }
    }"
    x-init="init()"
>
    {{-- ── Toggle Mensual / Anual ──────────────────────────────────────── --}}
    <div class="flex items-center justify-center gap-3 mb-7">
        <span
            class="text-sm transition-colors select-none"
            :class="ciclo === 'mensual'
                ? 'font-semibold text-gray-900 dark:text-white'
                : 'text-gray-400 dark:text-gray-500'"
        >Mensual</span>

        <button
            type="button"
            @click="ciclo = ciclo === 'mensual' ? 'anual' : 'mensual'"
            class="relative inline-flex h-6 w-11 flex-shrink-0 items-center rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
            :class="ciclo === 'anual' ? 'bg-primary-600' : 'bg-gray-300 dark:bg-gray-600'"
            role="switch"
            :aria-checked="ciclo === 'anual'"
            aria-label="Cambiar ciclo de facturación"
        >
            <span
                class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition-transform duration-200"
                :class="ciclo === 'anual' ? 'translate-x-5' : 'translate-x-0'"
            ></span>
        </button>

        <span
            class="text-sm transition-colors select-none"
            :class="ciclo === 'anual'
                ? 'font-semibold text-gray-900 dark:text-white'
                : 'text-gray-400 dark:text-gray-500'"
        >
            Anual
            <span class="ml-1.5 inline-flex items-center rounded-md px-1.5 py-0.5 text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300 ring-1 ring-inset ring-green-600/20 dark:ring-green-500/30">
                15&nbsp;% dto.
            </span>
        </span>
    </div>

    {{-- ── Cards ──────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">

        {{-- ── BÁSICO ────────────────────────────────────────────────── --}}
        @php
            $basicFeatures = [
                ['label' => 'Procesos disciplinarios', 'ok' => true],
                ['label' => 'Terminación de contrato',  'ok' => true],
                ['label' => 'Suspensiones y llamados',  'ok' => false],
                ['label' => 'Contratos y liquidaciones','ok' => false],
                ['label' => 'RIT incluido',             'ok' => false],
                ['label' => 'Múltiples empresas',       'ok' => false],
            ];
        @endphp
        <div
            @click="plan = 'basico'"
            class="relative flex flex-col rounded-xl border-2 p-5 cursor-pointer transition-all duration-200"
            :class="plan === 'basico'
                ? 'border-primary-500 bg-primary-50 dark:bg-primary-950/40 shadow-lg'
                : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/80 hover:border-gray-300 dark:hover:border-gray-600'"
        >
            {{-- Check seleccionado --}}
            <div
                x-show="plan === 'basico'"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 scale-75"
                x-transition:enter-end="opacity-100 scale-100"
                class="absolute right-3 top-3 flex h-5 w-5 items-center justify-center rounded-full bg-primary-500"
            >
                <svg class="h-3 w-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
            </div>

            {{-- Lordicon --}}
            <lord-icon
                src="https://cdn.lordicon.com/hmpomorl.json"
                trigger="loop" delay="2000"
                colors="primary:#6366f1,secondary:#a5b4fc"
                style="width:40px;height:40px;display:block"
            ></lord-icon>

            <h3 class="mt-3 text-base font-bold text-gray-900 dark:text-white">Básico</h3>

            {{-- Precio --}}
            <div class="mt-2 min-h-[3.25rem]">
                <p class="text-sm font-semibold text-green-600 dark:text-green-400">7 días gratis</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    luego
                    <span class="font-medium text-gray-700 dark:text-gray-300" x-text="fmt(precio('basico'))"></span>/<span x-text="periodo()"></span>
                </p>
            </div>

            {{-- Features --}}
            <ul class="mt-4 flex-1 space-y-2 text-xs">
                @foreach($basicFeatures as $feat)
                <li class="flex items-center gap-2 {{ $feat['ok'] ? 'text-gray-700 dark:text-gray-300' : 'text-gray-400 dark:text-gray-600' }}">
                    @if($feat['ok'])
                    <svg class="h-4 w-4 flex-shrink-0 text-green-500 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                    @else
                    <svg class="h-4 w-4 flex-shrink-0 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    @endif
                    {{ $feat['label'] }}
                </li>
                @endforeach
            </ul>

            {{-- Botón --}}
            <button
                type="button"
                @click.stop="plan = 'basico'"
                class="mt-5 w-full rounded-lg py-2 px-4 text-sm font-semibold transition-colors duration-150"
                :class="plan === 'basico'
                    ? 'bg-primary-600 text-white'
                    : 'border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700/60'"
            >
                <span x-text="plan === 'basico' ? 'Plan seleccionado' : 'Seleccionar plan'"></span>
            </button>
        </div>

        {{-- ── PRO ───────────────────────────────────────────────────── --}}
        @php
            $proFeatures = [
                ['label' => 'Procesos disciplinarios', 'ok' => true],
                ['label' => 'Terminación de contrato',  'ok' => true],
                ['label' => 'Suspensiones y llamados',  'ok' => true],
                ['label' => 'Contratos y liquidaciones','ok' => true],
                ['label' => 'RIT incluido',             'ok' => true],
                ['label' => 'Múltiples empresas',       'ok' => false],
            ];
        @endphp
        <div
            @click="plan = 'pro'"
            class="relative flex flex-col rounded-xl border-2 p-5 cursor-pointer transition-all duration-200"
            :class="plan === 'pro'
                ? 'border-primary-500 bg-primary-50 dark:bg-primary-950/40 shadow-lg'
                : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/80 hover:border-gray-300 dark:hover:border-gray-600'"
        >
            {{-- Badge recomendado --}}
            <div class="absolute -top-3 left-1/2 -translate-x-1/2">
                <span class="inline-flex items-center rounded-full bg-primary-600 px-2.5 py-0.5 text-xs font-semibold text-white shadow-sm whitespace-nowrap">
                    Recomendado
                </span>
            </div>

            {{-- Check seleccionado --}}
            <div
                x-show="plan === 'pro'"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 scale-75"
                x-transition:enter-end="opacity-100 scale-100"
                class="absolute right-3 top-3 flex h-5 w-5 items-center justify-center rounded-full bg-primary-500"
            >
                <svg class="h-3 w-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
            </div>

            {{-- Lordicon --}}
            <lord-icon
                src="https://cdn.lordicon.com/lupuorrc.json"
                trigger="loop" delay="2000"
                colors="primary:#7c3aed,secondary:#c4b5fd"
                style="width:40px;height:40px;display:block"
            ></lord-icon>

            <h3 class="mt-3 text-base font-bold text-gray-900 dark:text-white">Pro</h3>

            {{-- Precio --}}
            <div class="mt-2 min-h-[3.25rem]">
                <p class="text-xl font-bold text-gray-900 dark:text-white">
                    <span x-text="fmt(precio('pro'))"></span>
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    por <span x-text="periodo()"></span>
                    <span x-show="ciclo === 'anual'" class="text-green-600 dark:text-green-400 font-medium">&nbsp;· 15 % dto.</span>
                </p>
            </div>

            {{-- Features --}}
            <ul class="mt-4 flex-1 space-y-2 text-xs">
                @foreach($proFeatures as $feat)
                <li class="flex items-center gap-2 {{ $feat['ok'] ? 'text-gray-700 dark:text-gray-300' : 'text-gray-400 dark:text-gray-600' }}">
                    @if($feat['ok'])
                    <svg class="h-4 w-4 flex-shrink-0 text-green-500 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                    @else
                    <svg class="h-4 w-4 flex-shrink-0 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    @endif
                    {{ $feat['label'] }}
                </li>
                @endforeach
            </ul>

            {{-- Botón --}}
            <button
                type="button"
                @click.stop="plan = 'pro'"
                class="mt-5 w-full rounded-lg py-2 px-4 text-sm font-semibold transition-colors duration-150"
                :class="plan === 'pro'
                    ? 'bg-primary-600 text-white'
                    : 'border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700/60'"
            >
                <span x-text="plan === 'pro' ? 'Plan seleccionado' : 'Seleccionar plan'"></span>
            </button>
        </div>

        {{-- ── FIRMA ─────────────────────────────────────────────────── --}}
        @php
            $firmaFeatures = [
                ['label' => 'Procesos disciplinarios', 'ok' => true],
                ['label' => 'Terminación de contrato',  'ok' => true],
                ['label' => 'Suspensiones y llamados',  'ok' => true],
                ['label' => 'Contratos y liquidaciones','ok' => true],
                ['label' => 'RIT incluido',             'ok' => true],
                ['label' => 'Múltiples empresas',       'ok' => true],
            ];
        @endphp
        <div
            @click="plan = 'firma'"
            class="relative flex flex-col rounded-xl border-2 p-5 cursor-pointer transition-all duration-200"
            :class="plan === 'firma'
                ? 'border-primary-500 bg-primary-50 dark:bg-primary-950/40 shadow-lg'
                : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/80 hover:border-gray-300 dark:hover:border-gray-600'"
        >
            {{-- Check seleccionado --}}
            <div
                x-show="plan === 'firma'"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 scale-75"
                x-transition:enter-end="opacity-100 scale-100"
                class="absolute right-3 top-3 flex h-5 w-5 items-center justify-center rounded-full bg-primary-500"
            >
                <svg class="h-3 w-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
            </div>

            {{-- Lordicon --}}
            <lord-icon
                src="https://cdn.lordicon.com/osuxyevn.json"
                trigger="loop" delay="2000"
                colors="primary:#059669,secondary:#6ee7b7"
                style="width:40px;height:40px;display:block"
            ></lord-icon>

            <h3 class="mt-3 text-base font-bold text-gray-900 dark:text-white">Firma</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Para bufetes y contadores</p>

            {{-- Precio --}}
            <div class="mt-2 min-h-[3.25rem]">
                <p class="text-xl font-bold text-gray-900 dark:text-white">
                    <span x-text="fmt(precio('firma'))"></span>
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    por <span x-text="periodo()"></span>
                    <span x-show="ciclo === 'anual'" class="text-green-600 dark:text-green-400 font-medium">&nbsp;· 15 % dto.</span>
                </p>
            </div>

            {{-- Features --}}
            <ul class="mt-4 flex-1 space-y-2 text-xs">
                @foreach($firmaFeatures as $feat)
                <li class="flex items-center gap-2 {{ $feat['ok'] ? 'text-gray-700 dark:text-gray-300' : 'text-gray-400 dark:text-gray-600' }}">
                    @if($feat['ok'])
                    <svg class="h-4 w-4 flex-shrink-0 text-green-500 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                    @else
                    <svg class="h-4 w-4 flex-shrink-0 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    @endif
                    {{ $feat['label'] }}
                </li>
                @endforeach
            </ul>

            {{-- Botón --}}
            <button
                type="button"
                @click.stop="plan = 'firma'"
                class="mt-5 w-full rounded-lg py-2 px-4 text-sm font-semibold transition-colors duration-150"
                :class="plan === 'firma'
                    ? 'bg-primary-600 text-white'
                    : 'border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700/60'"
            >
                <span x-text="plan === 'firma' ? 'Plan seleccionado' : 'Seleccionar plan'"></span>
            </button>
        </div>

    </div>{{-- /grid --}}

    {{-- Nota pasarela --}}
    <p class="mt-5 text-center text-xs text-gray-400 dark:text-gray-500">
        Pagos procesados de forma segura a través de PayU Colombia. Acepta PSE, tarjetas débito/crédito y efectivo.
    </p>

</div>
