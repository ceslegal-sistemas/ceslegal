<x-filament-panels::page>

    @include('filament.components.lupe-hero-styles')

    {{-- Encabezado de marca (2026-10-19, Fase 1 de WhatsApp) - mismo
         patrón ya usado en Textos Configurables: esta pantalla maneja
         credenciales de API y configuración crítica de cumplimiento con
         Meta, no algo que deba verse como una caja genérica más. --}}
    <div class="rit-hero" style="padding:1.25rem 1.5rem;margin-bottom:1.25rem;">
        <div class="rit-orb-b"></div>
        <div class="rit-orb-g"></div>
        <div class="rit-overlay"></div>
        <div style="position:relative;z-index:2">
            <span class="rit-badge rit-badge-ia">
                <lord-icon src="https://cdn.lordicon.com/wpsdctqb.json" trigger="hover" stroke="bold"
                    colors="primary:#fecdd3,secondary:#fecdd3" data-pt-icon
                    data-pt-dark="primary:#fecdd3,secondary:#fecdd3"
                    data-pt-light="primary:#be123c,secondary:#be123c"
                    style="width:14px;height:14px;flex-shrink:0"></lord-icon>
                WhatsApp Business
            </span>
            <h1 class="rit-title" style="margin-top:.5rem;">Configuración de WhatsApp Business</h1>
            <p class="rit-sub" style="margin-top:.4rem;">
                Credenciales de Meta, webhook y la plantilla del código de verificación (OTP).
            </p>

            @php
                $calidad = $estadoConexion['calidad'] ?? $this->getCalidadActual();
                $badgeCalidad = fn ($c) => strtoupper((string) $c) === 'RED' ? 'rit-badge-danger' : (strtoupper((string) $c) === 'YELLOW' ? 'rit-badge-warning' : 'rit-badge-sub');
            @endphp
            @if ($calidad)
                <div style="margin-top:.75rem;">
                    <span class="rit-badge {{ $badgeCalidad($calidad) }}">
                        Calidad del número: {{ $calidad }}
                    </span>
                </div>
            @endif
        </div>
    </div>

    {{-- Account Health (pedido del usuario, 2026-10-19: "como un chatwoot")
         - no solo "conecta sí/no", el estado real que Meta reporta sobre
         el número: calidad, límite de mensajería, nombre verificado y
         estado del código. Se actualiza al pulsar "Verificar conexión" o
         cuando llega un account_update por el webhook. --}}
    @if ($estadoConexion !== null)
        <div class="rit-hero" style="padding:1.25rem 1.5rem;margin-bottom:1.25rem;">
            <div class="rit-orb-b"></div>
            <div class="rit-orb-g"></div>
            <div class="rit-overlay"></div>
            <div style="position:relative;z-index:2">
                <span class="rit-badge {{ $estadoConexion['ok'] ? 'rit-badge-sub' : 'rit-badge-danger' }}">
                    {{ $estadoConexion['ok'] ? 'Conexión verificada' : 'Error de conexión' }}
                </span>

                @if (!$estadoConexion['ok'])
                    <p class="rit-sub" style="margin-top:.6rem;">{{ $estadoConexion['mensaje'] }}</p>
                @else
                    <div style="margin-top:.9rem;display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:.75rem;">
                        @foreach ([
                            'Número' => $estadoConexion['numero'] ?? null,
                            'Nombre verificado' => $estadoConexion['nombre_verificado'] ?? null,
                            'Cuenta de WhatsApp Business' => $estadoConexion['nombre_waba'] ?? null,
                            'Límite de mensajería' => $estadoConexion['limite_mensajeria'] ?? null,
                            'Verificación del código' => $estadoConexion['verificacion_codigo'] ?? null,
                            'Tipo de plataforma' => $estadoConexion['tipo_plataforma'] ?? null,
                        ] as $etiqueta => $valor)
                            @if ($valor)
                                <div>
                                    <p style="font-size:.625rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#94a3b8;margin:0 0 .15rem;">{{ $etiqueta }}</p>
                                    <p style="font-size:.875rem;font-weight:600;margin:0;">{{ $valor }}</p>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Formulario --}}
    <form wire:submit="guardar">
        {{ $this->form }}

        <div class="mt-6 flex flex-wrap gap-3">
            <x-filament::button type="submit" icon="heroicon-m-check">
                Guardar configuración
            </x-filament::button>

            <x-filament::button
                type="button"
                wire:click="verificarConexion"
                wire:loading.attr="disabled"
                color="gray"
                icon="heroicon-m-signal"
            >
                <span wire:loading.remove wire:target="verificarConexion">Verificar conexión</span>
                <span wire:loading wire:target="verificarConexion">Verificando...</span>
            </x-filament::button>
        </div>
    </form>

    {{-- Guía rápida --}}
    <x-filament::section class="mt-6">
        <x-slot name="heading">Guía rápida - cómo obtener las credenciales</x-slot>
        <x-slot name="description">Pasos para configurar WhatsApp Business con Meta Cloud API</x-slot>

        <ol class="list-decimal list-inside space-y-2 text-sm text-gray-600 dark:text-gray-400">
            <li>
                Acceda a <strong class="text-gray-800 dark:text-gray-200">developers.facebook.com</strong>
                y cree (o seleccione) una app de tipo <em>Business</em>.
            </li>
            <li>
                En la app, agregue el producto <strong class="text-gray-800 dark:text-gray-200">WhatsApp</strong>
                y vincule una cuenta de WhatsApp Business.
            </li>
            <li>
                En <em>WhatsApp → API Setup</em>, copie el
                <strong class="text-gray-800 dark:text-gray-200">Phone Number ID</strong>
                y el <strong class="text-gray-800 dark:text-gray-200">WhatsApp Business Account ID</strong>.
            </li>
            <li>
                Genere un <strong class="text-gray-800 dark:text-gray-200">System User Token</strong> permanente
                en <em>Meta Business Suite → Usuarios del sistema</em> y asígnele permisos de
                <code class="bg-gray-100 dark:bg-gray-800 px-1 rounded">whatsapp_business_messaging</code>.
            </li>
            <li>
                Configure el <strong class="text-gray-800 dark:text-gray-200">Webhook</strong> con la URL
                mostrada arriba y el token de verificación definido en este formulario.
                Suscríbase al evento <code class="bg-gray-100 dark:bg-gray-800 px-1 rounded">messages</code>.
            </li>
        </ol>
    </x-filament::section>

</x-filament-panels::page>
