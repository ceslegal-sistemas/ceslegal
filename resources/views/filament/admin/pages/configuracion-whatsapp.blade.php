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
        {{-- Pedido del usuario (2026-10-20, con 2 capturas: el bug seguía
             igual de un día a otro): el selector de plantilla ("Plantilla
             del OTP"/"Plantilla de alerta a RRHH") abría su lista de
             opciones, pero quedaba cortada en seco justo en el borde
             inferior de la tarjeta - casi no se veían las opciones. Causa
             raíz real: Filament usa Choices.js para el Select buscable, que
             NO saca su panel de opciones del flujo normal (no usa
             x-teleport a <body>) - lo renderiza como descendiente del
             propio campo. El overflow:hidden que se le puso a .fi-section
             (para recortar las esquinas cuadradas de la franja de
             encabezado y, en las tarjetas "hero", para contener los orbes)
             recortaba TAMBIÉN ese panel de opciones, por más z-index que
             tuviera - overflow:hidden corta en el borde de la caja sin
             importar el z-index. Se quita overflow:hidden de .fi-section
             por completo y, en su lugar, se redondean directamente las
             esquinas superiores de la franja de encabezado (mismo radio
             que la tarjeta) para que no se vea un rectángulo cuadrado
             asomando por fuera de la esquina redondeada - sin necesitar
             overflow:hidden para lograrlo. --}}
        .rit-form-wrap .fi-section{border:none !important;border-radius:1rem !important;box-shadow:none !important;--tw-ring-shadow:0 0 0 0 transparent !important}
        html:not(.dark) .rit-form-wrap .fi-section{box-shadow:0 2px 12px rgba(0,0,0,.06) !important}
        .rit-form-wrap .fi-section + .fi-section{margin-top:1.5rem}

        .rit-form-wrap .fi-section-header{background:rgba(0,0,0,.03) !important;border-bottom:1px solid rgba(0,0,0,.07) !important;padding:.75rem 1.125rem !important;border-top-left-radius:1rem !important;border-top-right-radius:1rem !important}
        html.dark .rit-form-wrap .fi-section-header{background:rgba(255,255,255,.04) !important;border-bottom-color:rgba(255,255,255,.08) !important}

        .rit-form-wrap .fi-section-header-heading{font-size:.65rem !important;font-weight:700 !important;letter-spacing:.12em !important;text-transform:uppercase !important;color:#64748b !important;line-height:1.4 !important}
        .rit-form-wrap .fi-section-header-description{margin-top:.35rem !important}
        .rit-form-wrap .fi-section-header-icon{display:none !important}

        .rit-form-wrap .fi-section-content-ctn{border-top:none !important}
        .rit-form-wrap .fi-section-content{padding:1.25rem 1.5rem !important}

        {{-- Pedido del usuario (2026-10-20): tamaños desproporcionados entre
             tarjetas de una misma fila - CSS Grid estira todas las columnas
             a la MISMA altura por defecto (align-items:stretch), así que
             "Estado del servicio" (1 solo toggle) quedaba con un hueco vacío
             enorme para igualar la altura de "Credenciales" (4 campos). Cada
             tarjeta toma ahora su alto natural. --}}
        .rit-form-wrap .rit-grid-top{align-items:start !important}

        .rit-viewer{border-radius:1rem;overflow:hidden;margin-top:1.5rem;box-shadow:0 2px 12px rgba(0,0,0,.06)}
        html.dark .rit-viewer{box-shadow:none;border:1px solid rgba(255,255,255,.09)}
        .rit-viewer-header{display:flex;align-items:center;justify-content:space-between;padding:.75rem 1.125rem;border-bottom:1px solid rgba(0,0,0,.07);background:rgba(0,0,0,.03)}
        html.dark .rit-viewer-header{background:rgba(255,255,255,.04);border-bottom-color:rgba(255,255,255,.08)}
        .rit-viewer-label{font-size:.65rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#64748b}
        .rit-viewer-body{padding:1.25rem 1.5rem}

        {{-- Pedido del usuario (2026-10-19): algunas tarjetas con el mismo
             estilo "card hero" (degradado + orbes) de Mi Reglamento Interno,
             aplicado a las 2 secciones que son las FUNCIONES reales de esta
             página (OTP, Avisos a RRHH) - no se pueden meter los <div> reales
             de .rit-orb-b/.rit-orb-g dentro de un <section> de Filament (solo
             renderiza los campos del formulario), así que los orbes se logran
             con pseudo-elementos ::before/::after en vez de divs reales -
             mismo degradado/colores que .rit-hero, solo el mecanismo cambia. --}}
        {{-- overflow:visible a propósito (no "hidden"): esta sección también
             tiene el Select de plantilla adentro, así que no puede recortar
             sus descendientes. Los orbes se acercan al borde (ya no
             sobresalen con offsets negativos grandes) para que el desenfoque
             se note como un brillo suave en la esquina sin necesitar recorte. --}}
        .rit-form-wrap .rit-hero-section.fi-section{
            position:relative;overflow:visible;
            background:linear-gradient(150deg,#1a0f0c 0%,#241319 55%,#170d0a 100%) !important;
        }
        html:not(.dark) .rit-form-wrap .rit-hero-section.fi-section{
            background:linear-gradient(150deg,#fff1f2 0%,#fff7ed 55%,#ffffff 100%) !important;
            border:1px solid rgba(225,29,72,.16) !important;
            box-shadow:0 6px 26px rgba(225,29,72,.1) !important;
        }
        .rit-form-wrap .rit-hero-section::before{
            content:'';position:absolute;width:160px;height:160px;top:6px;right:6px;border-radius:50%;
            background:radial-gradient(circle,rgba(225,29,72,.45),transparent 70%);filter:blur(20px);pointer-events:none;z-index:0;
        }
        html:not(.dark) .rit-form-wrap .rit-hero-section::before{background:radial-gradient(circle,rgba(225,29,72,.35),transparent 70%) !important}
        .rit-form-wrap .rit-hero-section::after{
            content:'';position:absolute;width:120px;height:120px;bottom:6px;left:6px;border-radius:50%;
            background:radial-gradient(circle,rgba(201,168,76,.2),transparent 70%);filter:blur(18px);pointer-events:none;z-index:0;
        }
        html:not(.dark) .rit-form-wrap .rit-hero-section::after{background:radial-gradient(circle,rgba(201,168,76,.35),transparent 70%) !important}
        .rit-form-wrap .rit-hero-section .fi-section-header,
        .rit-form-wrap .rit-hero-section .fi-section-content-ctn{position:relative;z-index:1;background:transparent !important}
        .rit-form-wrap .rit-hero-section .fi-section-header{border-bottom-color:rgba(255,255,255,.1) !important}
        html:not(.dark) .rit-form-wrap .rit-hero-section .fi-section-header{border-bottom-color:rgba(225,29,72,.14) !important}
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
