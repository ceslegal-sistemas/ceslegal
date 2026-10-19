<x-filament-panels::page>

    @include('filament.components.lupe-hero-styles')

    {{-- Pedido del usuario (2026-10-19, con capturas de admin.ceslegal.co
         comparando contra Mi Reglamento Interno): el primer intento de
         retemar .fi-section solo tocó fondo/sombra y dejó el borde visible
         + el encabezado nativo de Filament (icono + título normal) - ahí
         seguía viéndose "una caja de Filament", no una tarjeta .rit-viewer.
         Mi Reglamento Interno NO usa borde en sus tarjetas, solo sombra
         suave, y el encabezado es una franja gris clara con una etiqueta
         pequeña en mayúsculas (igual que .rit-viewer-label) - sin icono.
         Esta vez se apunta directo a las clases reales que renderiza
         Filament (.fi-section, .fi-section-header, .fi-section-header-heading,
         .fi-section-content-ctn - confirmadas leyendo el componente en
         vendor/filament/support), no solo a .fi-section por fuera. Los
         iconos de cada Section ya se quitaron en el PHP (ConfiguracionWhatsapp::form()). --}}
    <style>
        .rit-form-wrap .fi-section{border:none !important;border-radius:1rem !important;box-shadow:none !important;--tw-ring-shadow:0 0 0 0 transparent !important;overflow:hidden}
        html:not(.dark) .rit-form-wrap .fi-section{box-shadow:0 2px 12px rgba(0,0,0,.06) !important}
        .rit-form-wrap .fi-section + .fi-section{margin-top:1.5rem}

        .rit-form-wrap .fi-section-header{background:rgba(0,0,0,.03) !important;border-bottom:1px solid rgba(0,0,0,.07) !important;padding:.75rem 1.125rem !important}
        html.dark .rit-form-wrap .fi-section-header{background:rgba(255,255,255,.04) !important;border-bottom-color:rgba(255,255,255,.08) !important}

        .rit-form-wrap .fi-section-header-heading{font-size:.65rem !important;font-weight:700 !important;letter-spacing:.12em !important;text-transform:uppercase !important;color:#64748b !important;line-height:1.4 !important}
        .rit-form-wrap .fi-section-header-description{margin-top:.35rem !important}
        .rit-form-wrap .fi-section-header-icon{display:none !important}

        .rit-form-wrap .fi-section-content-ctn{border-top:none !important}
        .rit-form-wrap .fi-section-content{padding:1.25rem 1.5rem !important}

        .rit-viewer{border-radius:1rem;overflow:hidden;margin-top:1.5rem;box-shadow:0 2px 12px rgba(0,0,0,.06)}
        html.dark .rit-viewer{box-shadow:none;border:1px solid rgba(255,255,255,.09)}
        .rit-viewer-header{display:flex;align-items:center;justify-content:space-between;padding:.75rem 1.125rem;border-bottom:1px solid rgba(0,0,0,.07);background:rgba(0,0,0,.03)}
        html.dark .rit-viewer-header{background:rgba(255,255,255,.04);border-bottom-color:rgba(255,255,255,.08)}
        .rit-viewer-label{font-size:.65rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#64748b}
        .rit-viewer-body{padding:1.25rem 1.5rem}
    </style>

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
    <div class="rit-form-wrap">
        <form wire:submit="guardar">
            {{ $this->form }}

            <div class="mt-6 flex flex-wrap gap-3">
                <button type="submit" class="rit-btn rit-btn-primary">
                    <svg style="width:16px;height:16px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                    </svg>
                    Guardar configuración
                </button>

                <button type="button" class="rit-btn rit-btn-secondary"
                    wire:click="verificarConexion" wire:loading.attr="disabled">
                    <svg wire:loading.remove wire:target="verificarConexion" style="width:16px;height:16px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 011.06 0z"/>
                    </svg>
                    <svg wire:loading wire:target="verificarConexion" style="width:16px;height:16px;animation:spin 1s linear infinite" fill="none" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" opacity="0.25"></circle>
                        <path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <span wire:loading.remove wire:target="verificarConexion">Verificar conexión</span>
                    <span wire:loading wire:target="verificarConexion">Verificando...</span>
                </button>
            </div>
        </form>
    </div>

    {{-- Guía rápida --}}
    <details class="rit-viewer" open>
        <summary class="rit-viewer-header" style="cursor:pointer;">
            <span class="rit-viewer-label">Guía rápida - cómo obtener las credenciales</span>
        </summary>
        <div class="rit-viewer-body">
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
                    mostrada arriba y el token de verificación generado automáticamente.
                    Suscríbase a los eventos <code class="bg-gray-100 dark:bg-gray-800 px-1 rounded">messages</code>
                    y <code class="bg-gray-100 dark:bg-gray-800 px-1 rounded">account_update</code>.
                </li>
            </ol>
        </div>
    </details>

</x-filament-panels::page>
