<?php

namespace App\Providers\Filament;

use AlexSyvolap\FilamentConfetti\FilamentConfettiPlugin;
use Awcodes\LightSwitch\LightSwitchPlugin;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use DiogoGPinto\AuthUIEnhancer\AuthUIEnhancerPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Hardikkhorasiya09\ChangePassword\ChangePasswordPlugin;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Moataz01\FilamentNotificationSound\FilamentNotificationSoundPlugin;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;

class AdminPanelProvider extends PanelProvider
{
    public function boot(): void
    {
        // Lordicon — iconos animados usados en modales y tarjetas
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn(): string => '<script src="https://cdn.lordicon.com/lordicon.js"></script>',
        );

        // Incluir Driver.js para tours de onboarding
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn(): string => '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/driver.css"/>',
        );

        // Oculta el stepper nativo de Filament SOLO donde exista la clase
        // `rit-hide-wizard-steps` (la añade la página CreateReglamentoInterno, que
        // usa su propio encabezado de paso). El CSS va en el <head> via render hook,
        // por lo que no agrega un segundo nodo raíz al componente Livewire (un
        // <style> inline en la vista de la página rompe el wizard).
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn(): string => '<style>.rit-hide-wizard-steps .fi-fo-wizard-header,.ces-hide-wizard-steps .fi-fo-wizard-header{display:none}</style>',
        );

        // ── Rebrand CES Legal ────────────────────────────────────────────────
        // Gradiente de marca (rojo→naranja), Space Grotesk en títulos y el ítem
        // de sidebar activo en gradiente. Inyectado por render hook → no requiere
        // build de npm (clave para el despliegue en Hostinger).
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn(): string => <<<'HTML'
            <style>
            @import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&display=swap');
            :root{ --ces-grad: linear-gradient(135deg,#E11D48 0%,#F97316 100%); }
            .fi-header-heading,.fi-section-header-heading,.fi-modal-heading,
            .fi-ta-header-heading,.fi-simple-header-heading{
                font-family:'Space Grotesk',ui-sans-serif,system-ui,sans-serif;
                letter-spacing:-0.01em;
            }
            /* Sidebar: el ítem activo usa el gradiente de marca */
            .fi-sidebar-item-active > .fi-sidebar-item-button{ background-image:var(--ces-grad); }
            .fi-sidebar-item-active > .fi-sidebar-item-button .fi-sidebar-item-label,
            .fi-sidebar-item-active > .fi-sidebar-item-button .fi-sidebar-item-icon{ color:#fff !important; }
            .fi-sidebar-item-active > .fi-sidebar-item-button:hover{ filter:brightness(1.04); }
            /* Tarjetas de indicadores del dashboard — polish de marca */
            .fi-wi-stats-overview-stat{
                border-radius:16px;
                transition:transform .15s ease, box-shadow .15s ease;
            }
            .fi-wi-stats-overview-stat:hover{
                transform:translateY(-2px);
                box-shadow:0 10px 28px rgba(28,25,23,0.07);
            }

            /* Login con fondo cálido (stone) */
            .fi-simple-layout{ background:#FAFAF9; }
            html.dark .fi-simple-layout{ background:#0C0A09; }

            /* ── Login split-screen CES Legal ── */
            .ces-auth-root{ position:fixed; inset:0; display:flex; background:#FAFAF9; z-index:10; }
            html.dark .ces-auth-root{ background:#0C0A09; }
            .ces-auth-brand{
                width:42%; max-width:640px; flex-shrink:0; color:#fff;
                background-image:var(--ces-grad);
                display:flex; flex-direction:column; justify-content:space-between;
                padding:72px 64px;
            }
            .ces-auth-logo{ font-family:'Space Grotesk',sans-serif; font-weight:700; font-size:64px; line-height:1; }
            .ces-auth-sub{ font-weight:600; letter-spacing:5px; font-size:16px; opacity:.92; margin-top:6px; }
            .ces-auth-tag{ font-family:'Space Grotesk',sans-serif; font-weight:600; font-size:30px; line-height:1.25; margin:0 0 14px; }
            .ces-auth-cap{ font-size:15px; line-height:1.55; opacity:.85; margin:0; max-width:34ch; }
            .ces-auth-main{ flex:1; display:flex; overflow:auto; padding:48px 40px; }
            .ces-auth-card{ width:100%; max-width:400px; margin:auto; }
            .ces-auth-card--wide{ max-width:640px; }
            .ces-auth-logo-img{ height:44px; width:auto; margin-bottom:28px; display:block; }
            .ces-auth-title{ font-family:'Space Grotesk',sans-serif; font-weight:700; font-size:30px; color:#1C1917; margin:0 0 6px; }
            html.dark .ces-auth-title{ color:#E7E5E4; }
            .ces-auth-lead{ color:#78716C; font-size:15px; margin:0 0 26px; }
            .ces-auth-foot{ margin-top:18px; font-size:13.5px; color:#78716C; }
            @media (max-width:900px){ .ces-auth-brand{ display:none; } .ces-auth-main{ padding:24px; } }
            </style>
            HTML,
        );

        // ── Skeleton de carga (shimmer de marca) ─────────────────────────────
        // Sistema global de placeholders de carga: una base gris neutra con un
        // brillo rojo→naranja recorriendo. Disponible en TODO el panel vía render
        // hook (sin build de npm). Lo usan: el overlay de navegación (BODY_END),
        // el componente <x-ces-skeleton> y los estados async de los componentes.
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn(): string => <<<'HTML'
            <style>
            @keyframes ces-sk-shimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}
            .ces-sk{position:relative;overflow:hidden;background-color:#e7e5e4;border-radius:.5rem;
                background-image:linear-gradient(90deg,transparent 0%,rgba(225,29,72,.12) 45%,rgba(249,115,22,.16) 55%,transparent 100%);
                background-size:200% 100%;background-repeat:no-repeat;animation:ces-sk-shimmer 1.5s ease-in-out infinite}
            html.dark .ces-sk{background-color:rgba(255,255,255,.07);
                background-image:linear-gradient(90deg,transparent 0%,rgba(251,113,133,.14) 45%,rgba(249,115,22,.16) 55%,transparent 100%)}
            .ces-sk-line{height:.7rem;border-radius:.375rem}
            .ces-sk-line.sm{height:.5rem}
            .ces-sk-title{height:1.25rem;border-radius:.45rem}
            .ces-sk-btn{height:2.25rem;width:9rem;border-radius:.65rem}
            .ces-sk-circle{border-radius:50%}
            .ces-sk-card{border-radius:1rem;height:6.5rem}
            .ces-sk-w-25{width:25%}.ces-sk-w-30{width:30%}.ces-sk-w-40{width:40%}.ces-sk-w-55{width:55%}.ces-sk-w-70{width:70%}.ces-sk-w-85{width:85%}
            @media(prefers-reduced-motion:reduce){.ces-sk{animation:none}}
            /* Overlay de navegación entre páginas */
            .ces-sk-overlay{position:fixed;z-index:30;bottom:0;overflow:hidden;background:#fafaf9;padding:1.5rem 2rem;animation:ces-sk-fade .12s ease both}
            html.dark .ces-sk-overlay{background:#0c0a09}
            @keyframes ces-sk-fade{from{opacity:0}to{opacity:1}}
            .ces-sk-stats{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:1rem;margin:0 0 1.5rem}
            .ces-sk-panel{border-radius:1rem;border:1px solid rgba(0,0,0,.06);padding:1.25rem 1.5rem;background:#fff}
            html.dark .ces-sk-panel{background:rgba(255,255,255,.02);border-color:rgba(255,255,255,.06)}
            .ces-sk-row{display:flex;gap:1rem;align-items:center;padding:.8rem 0;border-bottom:1px solid rgba(0,0,0,.05)}
            html.dark .ces-sk-row{border-color:rgba(255,255,255,.05)}
            .ces-sk-row:last-child{border-bottom:0}
            /* Scaffolds conscientes del componente (tabla / formulario / dashboard) */
            .ces-sk-hd{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1.5rem}
            .ces-sk-hd-actions{display:flex;gap:.6rem;flex-shrink:0}
            .ces-sk-tb{display:flex;gap:.6rem;margin-bottom:1rem;align-items:center}
            .ces-sk-grid2{display:grid;grid-template-columns:1fr 1fr;gap:1.25rem}
            @media(max-width:720px){.ces-sk-grid2{grid-template-columns:1fr}}
            .ces-sk-input{height:2.6rem;border-radius:.6rem;margin-top:.5rem}
            .ces-sk-foot{display:flex;gap:.6rem;justify-content:flex-end;margin-top:1.5rem}
            .ces-sk-chart{height:16rem;border-radius:1rem}
            /* Skeleton de TABLA (reemplaza el spinner de deferLoading) */
            .ces-tsk-box{width:100%}
            .ces-tsk-row{display:flex;align-items:center;gap:1.25rem;padding:.85rem 1.25rem;border-bottom:1px solid rgba(0,0,0,.05)}
            html.dark .ces-tsk-row{border-color:rgba(255,255,255,.05)}
            .ces-tsk-row.head{padding-top:.6rem;padding-bottom:.6rem}
            .ces-tsk-row:last-child{border-bottom:0}
            /* Skeleton de STATS OVERVIEW (placeholder del widget lazy) */
            .ces-ssk-grid{display:grid;gap:1.5rem}
            .ces-ssk-grid.c1{grid-template-columns:1fr}
            @media(min-width:768px){.ces-ssk-grid.c2{grid-template-columns:repeat(2,1fr)}.ces-ssk-grid.c3{grid-template-columns:repeat(3,1fr)}.ces-ssk-grid.c4{grid-template-columns:repeat(2,1fr)}}
            @media(min-width:1280px){.ces-ssk-grid.c4{grid-template-columns:repeat(4,1fr)}}
            .ces-ssk-card{border:1px solid rgba(0,0,0,.07);border-radius:.75rem;padding:1rem 1.25rem;background:#fff;display:flex;flex-direction:column;gap:.7rem}
            html.dark .ces-ssk-card{background:rgba(255,255,255,.02);border-color:rgba(255,255,255,.06)}
            /* Loader de marca "Cargando" (páginas custom, no-resources) */
            .ces-pl{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.5rem;min-height:55vh;text-align:center}
            .ces-pl lord-icon{width:150px;height:150px}
            .ces-pl-txt{font-size:1rem;font-weight:600;letter-spacing:.02em;color:#78716c}
            html.dark .ces-pl-txt{color:#a8a29e}
            .ces-pl-txt::after{content:'';animation:ces-pl-dots 1.4s steps(4,end) infinite;}
            @keyframes ces-pl-dots{0%{content:''}25%{content:'.'}50%{content:'..'}75%{content:'...'}100%{content:''}}
            </style>
            HTML,
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn(): string => '<script src="https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/driver.js.iife.js"></script><script src="' . asset('js/tour-descargos.js') . '"></script>',
        );

        // Fix: Mac browsers fire native HTML form validation before Livewire intercepts,
        // throwing "An invalid form control with name='' is not focusable" for hidden
        // inputs created by Tom Select (native:false) and conditional repeaters.
        // Three-layer fix (global — applies to all admin pages):
        //   1. novalidate on <form> applied IMMEDIATELY on script parse (BODY_END =
        //      DOM already loaded, DOMContentLoaded never re-fires)
        //   2. MutationObserver to re-apply after Livewire DOM morphing removes the attr
        //   3. Capture-phase invalid listener as absolute final safety net
        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn(): string => <<<'HTML'
            <script>
            (function () {
                function patchForms() {
                    document.querySelectorAll('form').forEach(function (f) {
                        f.setAttribute('novalidate', '');
                    });
                }
                // Run immediately — DOM is already parsed at BODY_END
                patchForms();
                // Re-apply after Livewire updates (morphing can reset attributes)
                document.addEventListener('livewire:update', patchForms);
                document.addEventListener('livewire:updated', patchForms);
                // MutationObserver catches any DOM restructuring Livewire does
                if (window.MutationObserver) {
                    new MutationObserver(function (mutations) {
                        for (var i = 0; i < mutations.length; i++) {
                            if (mutations[i].addedNodes.length) { patchForms(); break; }
                        }
                    }).observe(document.body, { childList: true, subtree: true });
                }
                // Absolute safety net: suppress browser invalid events in capture phase
                document.addEventListener('invalid', function (e) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                }, true);
            })();
            </script>
            HTML,
        );

        // ── Skeleton de navegación entre páginas ─────────────────────────────
        // Al hacer click en un enlace interno (sidebar, tablas, breadcrumbs…) se
        // muestra de inmediato un skeleton de página sobre el área de contenido.
        // En MPA el overlay desaparece solo cuando el navegador reemplaza el
        // documento por la página nueva; si en el futuro se activa ->spa(), los
        // listeners livewire:navigating/navigated lo manejan también. No toca los
        // nodos de Livewire (es un overlay fixed e independiente).
        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn(): string => <<<'HTML'
            <script>
            (function () {
                var overlay = null;
                function sk(extra, style){ return '<div class="ces-sk ' + (extra || '') + '"' + (style ? ' style="' + style + '"' : '') + '></div>'; }
                // Encabezado de página: título + botones de acción (donde van los botones).
                function header(withCreate){
                    var actions = withCreate
                        ? sk('ces-sk-btn', 'width:6.5rem;height:2.1rem') + sk('ces-sk-btn', 'width:10rem;height:2.1rem')
                        : sk('ces-sk-btn', 'width:6rem;height:2.1rem');
                    return '<div class="ces-sk-hd">' + sk('ces-sk-title ces-sk-w-30')
                        + '<div class="ces-sk-hd-actions">' + actions + '</div></div>';
                }
                // Lista/tabla: header + toolbar (búsqueda + filtros) + filas con acción por fila.
                function tableScaffold(){
                    var s = header(true);
                    s += '<div class="ces-sk-tb">' + sk('', 'flex:1;height:2.4rem;border-radius:.6rem')
                        + sk('ces-sk-btn', 'width:6rem;height:2.4rem') + sk('ces-sk-btn', 'width:6rem;height:2.4rem') + '</div>';
                    s += '<div class="ces-sk-panel">';
                    s += '<div class="ces-sk-row">' + sk('ces-sk-line ces-sk-w-25') + sk('ces-sk-line ces-sk-w-25')
                        + sk('ces-sk-line ces-sk-w-25') + sk('ces-sk-line', 'width:3rem;margin-left:auto') + '</div>';
                    for (var i = 0; i < 8; i++) {
                        s += '<div class="ces-sk-row">' + sk('ces-sk-circle', 'width:2.1rem;height:2.1rem;flex:0 0 auto')
                            + sk('ces-sk-line ces-sk-w-25') + sk('ces-sk-line ces-sk-w-25') + sk('ces-sk-line ces-sk-w-25')
                            + sk('ces-sk-btn', 'width:4.5rem;height:1.8rem;margin-left:auto') + '</div>';
                    }
                    return s + '</div>';
                }
                // Formulario / wizard: header + campos en 2 columnas + textarea + botones Guardar/Cancelar.
                function formScaffold(){
                    var s = header(false);
                    s += '<div class="ces-sk-panel">' + sk('ces-sk-line ces-sk-w-25', 'height:1rem;margin-bottom:1.25rem')
                        + '<div class="ces-sk-grid2">';
                    for (var i = 0; i < 6; i++) {
                        s += '<div>' + sk('ces-sk-line ces-sk-w-40 sm') + sk('ces-sk-input') + '</div>';
                    }
                    s += '</div>';
                    s += '<div style="margin-top:1.25rem">' + sk('ces-sk-line ces-sk-w-25 sm') + sk('', 'height:6rem;border-radius:.6rem;margin-top:.5rem') + '</div>';
                    s += '</div>';
                    s += '<div class="ces-sk-foot">' + sk('ces-sk-btn', 'width:6rem') + sk('ces-sk-btn', 'width:9rem') + '</div>';
                    return s;
                }
                // Dashboard: header + tarjetas de stats + 2 paneles de gráfico.
                function dashScaffold(){
                    var s = header(false) + '<div class="ces-sk-stats">';
                    for (var i = 0; i < 4; i++) { s += sk('ces-sk-card'); }
                    s += '</div><div class="ces-sk-grid2">' + sk('ces-sk-chart') + sk('ces-sk-chart') + '</div>';
                    return s;
                }
                // Páginas custom (no-resources): se muestran con el loader de marca
                // (Lordicon "Cargando") en vez de un skeleton de tabla/formulario.
                var PAGE_SLUGS = ['auditar-r-i-t', 'mi-reglamento-interno', 'estadisticas-informes',
                    'configuracion-whatsapp', 'exportar-informes-juridicos', 'cambiar-password'];
                function pageLoader(){
                    return '<div class="ces-pl">'
                        + '<lord-icon src="https://cdn.lordicon.com/fikcyfpp.json" trigger="loop" delay="500" '
                        + 'colors="primary:#e11d48,secondary:#f97316"></lord-icon>'
                        + '<div class="ces-pl-txt">Cargando</div></div>';
                }
                function scaffold(v){
                    return v === 'form' ? formScaffold()
                        : v === 'dash' ? dashScaffold()
                        : v === 'page' ? pageLoader()
                        : tableScaffold();
                }
                // Elige el scaffold según a dónde apunta el enlace (create/edit→form,
                // página custom→loader Lordicon, dashboard→dash, resto→tabla).
                function variantFor(a){
                    var p = (a.pathname || '').toLowerCase();
                    var t = (a.textContent || '').toLowerCase().trim();
                    if (/\/create(\/|$)/.test(p) || /\/edit(\/|$)/.test(p) || /\/\d+\/edit/.test(p)
                        || /\b(crear|nuevo|nueva|editar|registrar)\b/.test(t)) return 'form';
                    for (var i = 0; i < PAGE_SLUGS.length; i++) {
                        if (p.indexOf('/' + PAGE_SLUGS[i]) !== -1) return 'page';
                    }
                    if (/\/admin\/?$/.test(p) || /\/dashboard\/?$/.test(p)
                        || /\b(panel|inicio|dashboard|tablero|escritorio)\b/.test(t)) return 'dash';
                    return 'table';
                }
                function show(v){
                    if (overlay) return;
                    var main = document.querySelector('.fi-main') || document.querySelector('.fi-main-ctn') || document.querySelector('main');
                    if (!main) return;
                    var r = main.getBoundingClientRect();
                    overlay = document.createElement('div');
                    overlay.className = 'ces-sk-overlay';
                    overlay.style.top = Math.max(0, r.top) + 'px';
                    overlay.style.left = r.left + 'px';
                    overlay.style.width = r.width + 'px';
                    var w = Math.min(r.width - 64, 1100);
                    overlay.innerHTML = '<div style="max-width:' + w + 'px;margin:0 auto">' + scaffold(v) + '</div>';
                    document.body.appendChild(overlay);
                }
                function hide(){ if (overlay) { overlay.remove(); overlay = null; } }
                document.addEventListener('click', function (e) {
                    var a = e.target.closest ? e.target.closest('a[href]') : null;
                    if (!a) return;
                    if (a.target === '_blank' || a.hasAttribute('download')) return;
                    if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
                    var href = a.getAttribute('href') || '';
                    if (!href || href.charAt(0) === '#') return;
                    if (href.indexOf('javascript:') === 0 || href.indexOf('mailto:') === 0 || href.indexOf('tel:') === 0) return;
                    if (a.hostname && a.hostname !== location.hostname) return;
                    if (a.href === location.href) return;
                    // Solo páginas custom (no-resources) usan el overlay (loader Lordicon).
                    // Los resources se cubren con su propio skeleton: tabla (deferLoading)
                    // y stats (lazy). Así no se ve el genérico encima del skeleton real.
                    var v = variantFor(a);
                    if (v !== 'page') return;
                    show(v);
                }, true);
                // SPA hook (por si se activa ->spa() más adelante): solo limpia al terminar.
                document.addEventListener('livewire:navigated', hide);
                // bfcache: al volver con el botón atrás, el documento se restaura — limpia el overlay
                window.addEventListener('pageshow', hide);
            })();
            </script>
            HTML,
        );

        // ── Skeleton de TABLA (reemplaza el spinner de deferLoading) ──────────
        // Cuando una tabla usa ->deferLoading(), Filament muestra un spinner
        // centrado (contenedor .h-32) mientras carga. Lo cambiamos por un skeleton
        // con forma de tabla (cabecera + filas con barras shimmer). Fail-safe: si
        // el DOM cambia y no engancha, simplemente queda el spinner por defecto.
        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn(): string => <<<'HTML'
            <script>
            (function () {
                var COLS = 5, BODY_ROWS = 7;
                function bar(style){ return '<div class="ces-sk" style="' + style + '"></div>'; }
                function row(head) {
                    var s = '<div class="ces-tsk-row' + (head ? ' head' : '') + '">';
                    s += bar('flex:0 0 1.1rem;height:1.1rem;border-radius:.25rem');               // checkbox
                    if (!head) s += bar('flex:0 0 2rem;height:2rem;border-radius:50%');            // avatar
                    for (var i = 0; i < COLS; i++) {
                        var w = head ? '4.5rem' : (42 + ((i * 19) % 46)) + '%';
                        s += bar('flex:1;height:.7rem;border-radius:.375rem;max-width:' + (head ? '5.5rem' : w));
                    }
                    s += bar('flex:0 0 1.6rem;height:1.3rem;border-radius:.35rem');                // acciones
                    return s + '</div>';
                }
                function skeleton() {
                    var html = '<div class="ces-tsk-box">' + row(true);
                    for (var r = 0; r < BODY_ROWS; r++) html += row(false);
                    return html + '</div>';
                }
                function enhance(scope) {
                    // El loading-indicator de Filament es un <svg class="animate-spin">.
                    // El placeholder de deferLoading lo monta centrado en un <div class="... h-32 ...">.
                    var spinners = (scope || document).querySelectorAll('svg.animate-spin');
                    for (var i = 0; i < spinners.length; i++) {
                        var box = spinners[i].parentElement;
                        if (!box || box.dataset.cesTsk) continue;
                        // Solo el placeholder de deferLoading (contenedor h-32), no los
                        // spinners de filtros/reorden/paginación.
                        if ((box.className || '').indexOf('h-32') === -1) continue;
                        box.dataset.cesTsk = '1';
                        box.className = 'ces-tsk-box';
                        box.innerHTML = skeleton();
                    }
                }
                document.addEventListener('DOMContentLoaded', function(){ enhance(); });
                document.addEventListener('livewire:navigated', function(){ enhance(); });
                if (window.MutationObserver) {
                    new MutationObserver(function (muts) {
                        for (var i = 0; i < muts.length; i++) {
                            if (muts[i].addedNodes.length) { enhance(); break; }
                        }
                    }).observe(document.body, { childList: true, subtree: true });
                }
                enhance();
            })();
            </script>
            HTML,
        );

        // Carga diferida en TODAS las tablas del panel → muestran el skeleton de
        // tabla mientras cargan (y mejora el primer pintado). Una tabla concreta
        // puede desactivarlo con ->deferLoading(false).
        \Filament\Tables\Table::configureUsing(function (\Filament\Tables\Table $table): void {
            $table->deferLoading();
        });
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->brandName('CES Legal')
            ->brandLogo(asset('images/ces-legal-logo.png'))
            ->brandLogoHeight('2.2rem')
            ->favicon(asset('images/ces-legal-favicon.png'))
            ->font('Inter')
            ->id('admin')
            ->path('admin')
            ->login(\App\Filament\Admin\Pages\Auth\Login::class)
            ->registration(\App\Filament\Admin\Pages\Auth\Register::class)
            ->passwordReset()
            // Rebrand CES Legal — rojo de marca + neutros cálidos (stone) + semánticos
            ->colors([
                'primary' => Color::hex('#E11D48'),
                'gray'    => Color::Stone,
                'danger'  => Color::Red,
                'warning' => Color::Amber,
                'success' => Color::Green,
                'info'    => Color::Blue,
            ])
            // ->theme() removido — se usa el tema por defecto de Filament que incluye todos los estilos fi-*
            ->sidebarCollapsibleOnDesktop()
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->navigationGroups([
                NavigationGroup::make('Gestión Laboral'),
                NavigationGroup::make('Gestión de Contratos'),
                NavigationGroup::make('Gestión Jurídica'),
                NavigationGroup::make('Configuración Informes')
                    ->collapsible()
                    ->collapsed(),
                NavigationGroup::make('Administración'),
            ])
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\\Filament\\Admin\\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\\Filament\\Admin\\Pages')
            ->pages([
                \App\Filament\Admin\Pages\Dashboard::class,
            ])
            ->userMenuItems([
                'cambiar-password' => \Filament\Navigation\MenuItem::make()
                    ->label('Cambiar Contraseña')
                    ->url(fn() => \App\Filament\Admin\Pages\CambiarPassword::getUrl())
                    ->icon('heroicon-o-key'),
            ])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\\Filament\\Admin\\Widgets')
            ->widgets([
                // Widgets personalizados se cargan desde el Dashboard
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->plugins(array_filter([
                FilamentShieldPlugin::make(),
                LightSwitchPlugin::make(),
                FilamentNotificationSoundPlugin::make()
                    ->volume(1.0) // Volume (0.0 to 1.0)
                    ->showAnimation(true) // Show animation on notification badge
                    ->enabled(true),
                // Confeti (fireworks) tras registrarse, al aterrizar en auditar/generar RIT.
                // Cosmético: solo se registra si el paquete está instalado en vendor/,
                // para que un composer install pendiente no tumbe todo el panel.
                class_exists(FilamentConfettiPlugin::class)
                    ? FilamentConfettiPlugin::make()
                    : null,
            ]))
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
