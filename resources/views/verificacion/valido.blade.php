@php
    $heroId = 'hv_' . substr(md5('verificacion'), 0, 8);

    /* Embers — golden-orange sparks rising from bottom (light mode) */
    $emberColors = ['200,60,5', '230,90,10', '255,130,20', '180,45,0', '240,110,15', '210,70,5'];
    $embers = [];
    for ($i = 0; $i < 28; $i++) {
        $c  = $emberColors[array_rand($emberColors)];
        $sz = round(mt_rand(15, 45) / 10, 1);
        $embers[] = [
            'x'     => mt_rand(2, 98),
            'sz'    => $sz,
            'c'     => $c,
            'g'     => (int)(($sz * mt_rand(25, 50)) / 10),
            'dur'   => round(mt_rand(35, 75) / 10, 1),
            'del'   => round(mt_rand(0, 90) / 10, 1),
            'drift' => mt_rand(-40, 40),
        ];
    }
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación de Autenticidad · CES Legal</title>
    <link rel="icon" href="/images/ces-legal-logo.png" type="image/png">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50:  '#eef2ff',
                            100: '#e0e7ff',
                            200: '#c7d2fe',
                            300: '#a5b4fc',
                            400: '#818cf8',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                            800: '#3730a3',
                            900: '#312e81',
                        },
                        warning: {
                            50:  '#fffbeb',
                            100: '#fef3c7',
                            500: '#f59e0b',
                            600: '#d97706',
                            700: '#b45309',
                            800: '#92400e',
                        },
                    }
                }
            }
        }
    </script>

    <script src="https://cdn.lordicon.com/lordicon.js"></script>

    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }

        /* ══ Hero keyframes ══════════════════════════════════════════════ */
        @keyframes bv-up  { from { opacity:0; transform:translateY(20px) } to { opacity:1; transform:translateY(0) } }
        @keyframes bv-pop { from { opacity:0; transform:scale(.55) }        to { opacity:1; transform:scale(1) } }
        @keyframes bv-glow {
            0%,100% { box-shadow:0 0 0  0   rgba(201,168,76,.4) }
            65%     { box-shadow:0 0 0 14px  rgba(201,168,76,0) }
        }
        @keyframes bv-float-blue {
            0%   { transform:translate(0,0) scale(1) }
            25%  { transform:translate(-22px,18px) scale(1.08) }
            55%  { transform:translate(14px,-14px) scale(.94) }
            80%  { transform:translate(-8px,22px) scale(1.04) }
            100% { transform:translate(0,0) scale(1) }
        }
        @keyframes bv-float-gold {
            0%   { transform:translate(0,0) scale(1) }
            30%  { transform:translate(18px,-22px) scale(1.12) }
            60%  { transform:translate(-12px,10px) scale(.88) }
            85%  { transform:translate(8px,-14px) scale(1.06) }
            100% { transform:translate(0,0) scale(1) }
        }
        @keyframes bv-ember-rise {
            0%   { transform:translateY(0) translateX(0) scale(1); opacity:0; }
            8%   { opacity:.92; }
            80%  { opacity:.45; }
            100% { transform:translateY(-320px) translateX(var(--drift,20px)) scale(.2); opacity:0; }
        }

        .bv-a1 { animation:bv-up .6s cubic-bezier(.16,1,.3,1) both }
        .bv-a2 { animation:bv-up .6s .12s cubic-bezier(.16,1,.3,1) both }

        .bv-icon-ring {
            animation: bv-pop .7s .08s cubic-bezier(.34,1.56,.64,1) both,
                       bv-glow 3s 1.2s ease-in-out infinite;
        }

        /* ══ Hero container — light mode ════════════════════════════════ */
        .bv-hero {
            position: relative;
            overflow: hidden;
            border-radius: 1.125rem;
            padding: 2rem 1.25rem 1.75rem;
            text-align: center;
            background: #ffffff;
            border: 1px solid rgba(0,0,0,.06);
            box-shadow: 0 4px 24px rgba(0,0,0,.06);
        }
        @media(min-width:540px) {
            .bv-hero { border-radius:1.375rem; padding:2.5rem 2rem 2.25rem; }
        }

        .bv-hero-overlay {
            position: absolute; inset: 0; pointer-events: none; z-index: 1;
            background: radial-gradient(ellipse 72% 80% at 50% 45%,
                rgba(255,255,255,.68) 0%, rgba(255,255,255,.35) 55%, transparent 100%);
        }
        .bv-fire-base {
            position: absolute; inset: 0; pointer-events: none; z-index: 0;
            background: radial-gradient(ellipse 85% 55% at 50% 100%,
                rgba(255,110,20,.22) 0%, rgba(255,160,40,.10) 50%, transparent 100%);
        }
        .bv-hero-orb-blue { animation: bv-float-blue 11s ease-in-out infinite; }
        .bv-hero-orb-gold { animation: bv-float-gold 14s ease-in-out infinite; }
        .bv-ember {
            position: absolute; border-radius: 50%; pointer-events: none;
            bottom: -4px; will-change: transform, opacity;
            animation: bv-ember-rise var(--dur,5s) var(--del,0s) ease-in infinite;
        }

        /* ══ Rule divider ════════════════════════════════════════════════ */
        .bv-rule {
            display: flex;
            align-items: center;
            gap: .75rem;
        }
        .bv-rule-line {
            flex: 1;
            height: 1px;
            background: #e2e8f0;
        }
        .bv-rule-label {
            font-size: .625rem;
            font-weight: 700;
            letter-spacing: .14em;
            text-transform: uppercase;
            white-space: nowrap;
            color: #94a3b8;
        }

        /* ══ Hint box (aviso legal) ══════════════════════════════════════ */
        .bv-hint {
            display: flex;
            align-items: flex-start;
            gap: .75rem;
            padding: .875rem 1.125rem;
            border-radius: 1rem;
            background: rgba(146,113,13,.06);
            border: 1px solid rgba(146,113,13,.18);
            font-size: .8125rem;
            color: #475569;
            line-height: 1.6;
        }
        .bv-hint strong { color: #92710d; font-weight: 600; }

        /* ══ Section cards ═══════════════════════════════════════════════ */
        .section-card {
            background: rgba(255,255,255,.85);
            border-radius: 1rem;
            border: 1px solid rgba(0,0,0,.08);
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,.06);
            position: relative;
            transition: transform .35s cubic-bezier(.16,1,.3,1), box-shadow .35s ease;
        }
        /* Top accent bar — slides in on hover */
        .section-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 2.5px;
            background: var(--card-accent, #4f46e5);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform .28s ease;
            z-index: 1;
        }
        .section-card:hover::before { transform: scaleX(1); }
        /* Shimmer / glare on hover */
        .section-card::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: inherit;
            background: radial-gradient(circle at var(--mx,50%) var(--my,50%),
                rgba(201,168,76,.10) 0%, transparent 62%);
            opacity: 0;
            transition: opacity .3s;
            pointer-events: none;
            z-index: 0;
        }
        .section-card:hover::after { opacity: 1; }

        .section-head {
            border-bottom: 1px solid rgba(0,0,0,.06);
            padding: 0.625rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .section-head-label {
            font-size: .575rem;
            font-weight: 700;
            color: #4f46e5;
            text-transform: uppercase;
            letter-spacing: .12em;
            opacity: .85;
        }

        /* ══ Field tokens ════════════════════════════════════════════════ */
        .field-label {
            font-size: 0.6875rem;
            font-weight: 500;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 0.2rem;
        }
        .field-value {
            font-size: 0.875rem;
            font-weight: 600;
            color: #0f172a;
        }
        .mono { font-family: 'SF Mono','Fira Code','Courier New',monospace; }
        .auth-line { width:2px; background:#bbf7d0; flex:1; margin:3px 0; }

        @media(prefers-reduced-motion:reduce) {
            .bv-a1,.bv-a2,.bv-icon-ring,.bv-hero-orb-blue,.bv-hero-orb-gold,.bv-ember,
            .section-card { animation:none; transition:none; opacity:.9; transform:none; }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen antialiased">

    {{-- ── Header ── --}}
    <header class="bg-white border-b border-gray-200 sticky top-0 z-10">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 h-14 flex items-center justify-between">
            <img src="/images/ces-legal-logo.png" alt="CES Legal" class="h-8 w-auto">
            <div class="flex items-center gap-1.5 text-xs text-gray-400 font-medium">
                <lord-icon
                    src="https://cdn.lordicon.com/fihkmkwt.json"
                    trigger="loop" delay="3000" stroke="bold"
                    colors="primary:#6b7280,secondary:#9ca3af"
                    style="width:15px;height:15px;">
                </lord-icon>
                Portal de Verificación
            </div>
        </div>
    </header>

    <main class="max-w-2xl mx-auto px-4 sm:px-6 py-6 space-y-4">

        {{-- ── Hero ── --}}
        <div class="bv-hero bv-a1" id="{{ $heroId }}">
            <canvas id="{{ $heroId }}_canvas"
                style="position:absolute;inset:0;width:100%;height:100%;pointer-events:none;opacity:.45;"></canvas>

            <div style="position:absolute;inset:0;pointer-events:none;overflow:hidden;">
                <div class="bv-hero-orb-blue"
                    style="position:absolute;width:280px;height:280px;top:-70px;right:-50px;border-radius:50%;
                           background:radial-gradient(circle,rgba(220,80,10,.28),transparent 70%);filter:blur(28px);">
                </div>
                <div class="bv-hero-orb-gold"
                    style="position:absolute;width:200px;height:200px;bottom:-50px;left:-40px;border-radius:50%;
                           background:radial-gradient(circle,rgba(201,140,20,.32),transparent 70%);filter:blur(26px);">
                </div>
                @foreach($embers as $e)
                <div class="bv-ember" style="
                    left:{{ $e['x'] }}%;
                    width:{{ $e['sz'] }}px; height:{{ $e['sz'] }}px;
                    background:rgb({{ $e['c'] }});
                    box-shadow:0 0 {{ $e['g'] }}px {{ $e['g'] }}px rgba({{ $e['c'] }},.6),
                               0 0 {{ $e['g']*2 }}px {{ $e['g']*3 }}px rgba(255,160,30,.22);
                    --drift:{{ $e['drift'] }}px; --dur:{{ $e['dur'] }}s; --del:{{ $e['del'] }}s;">
                </div>
                @endforeach
            </div>

            <div class="bv-fire-base"></div>
            <div class="bv-hero-overlay"></div>

            <div style="position:relative;z-index:2;">
                <div class="bv-icon-ring" style="
                    display:inline-flex;align-items:center;justify-content:center;
                    width:64px;height:64px;border-radius:50%;margin-bottom:1.125rem;
                    background:rgba(201,168,76,.12);border:1.5px solid rgba(146,113,13,.35);">
                    <lord-icon
                        src="https://cdn.lordicon.com/xjsqfzte.json"
                        trigger="loop" delay="500" stroke="bold"
                        colors="primary:#4f46e5,secondary:#6366f1,tertiary:#c7d2fe"
                        style="width:50px;height:50px;">
                    </lord-icon>
                </div>

                <p style="color:#92710d;font-size:.7rem;font-weight:700;letter-spacing:.18em;
                           text-transform:uppercase;margin:0 0 .4rem;
                           text-shadow:0 1px 12px rgba(180,80,10,.2),0 2px 4px rgba(0,0,0,.08);">
                    Verificador de Identidad
                </p>

                <h1 style="color:#0f172a;font-size:1.5rem;font-weight:700;letter-spacing:-.02em;
                            line-height:1.25;margin:0 0 .875rem;
                            text-shadow:0 1px 12px rgba(180,80,10,.2),0 2px 4px rgba(0,0,0,.08);">
                    Documento Verificado
                </h1>

                <p style="color:#334155;font-size:.875rem;font-weight:500;line-height:1.65;
                           margin:0 auto;max-width:420px;">
                    Este documento fue generado por la plataforma CES Legal y su autenticidad ha sido confirmada.
                    La identidad del participante fue validada mediante
                    <span style="color:#92710d;font-weight:600;">OTP</span>
                    y verificación
                    <span style="color:#92710d;font-weight:600;">facial biométrica</span>.
                </p>
            </div>
        </div>

        {{-- ── Aviso legal ── --}}
        <div class="bv-hint">
            <lord-icon
                src="https://cdn.lordicon.com/msoeawqm.json"
                trigger="loop" delay="4000" stroke="bold"
                colors="primary:#92400e,secondary:#b45309"
                style="width:17px;height:17px;flex-shrink:0;margin-top:1px;">
            </lord-icon>
            <p style="margin:0;">
                <strong>Nota:</strong>
                CES Legal actúa exclusivamente como proveedora del servicio tecnológico de gestión disciplinaria.
                La decisión disciplinaria es responsabilidad exclusiva de
                <strong>{{ $empresa->razon_social ?? 'el empleador' }}</strong>.
                Válido conforme a la <strong>Ley 527/1999</strong>
                y el <strong>Decreto 2364/2012</strong>.
            </p>
        </div>

        {{-- ── Divider ── --}}
        <div class="bv-rule">
            <div class="bv-rule-line"></div>
            <span class="bv-rule-label">Información del documento</span>
            <div class="bv-rule-line"></div>
        </div>

        {{-- ── Proceso Disciplinario ── --}}
        <div class="section-card" style="--card-accent:#4f46e5;">
            <div class="section-head">
                <lord-icon
                    src="https://cdn.lordicon.com/hmpomorl.json"
                    trigger="loop" delay="2000" stroke="bold"
                    colors="primary:#4f46e5,secondary:#6366f1,tertiary:#c7d2fe"
                    style="width:20px;height:20px;flex-shrink:0;">
                </lord-icon>
                <span class="section-head-label">Proceso Disciplinario</span>
            </div>
            <div class="p-5 grid grid-cols-2 sm:grid-cols-4 gap-x-4 gap-y-4">
                <div>
                    <p class="field-label">N.° proceso</p>
                    <span class="inline-block px-2.5 py-0.5 bg-primary-50 text-primary-700 text-xs font-bold rounded-full border border-primary-200">
                        {{ $proceso->codigo ?? '—' }}
                    </span>
                </div>
                <div>
                    <p class="field-label">Empresa</p>
                    <p class="field-value">{{ $empresa->razon_social ?? '—' }}</p>
                </div>
                <div>
                    <p class="field-label">NIT</p>
                    <p class="field-value mono">{{ $empresa->nit ?? '—' }}</p>
                </div>
                <div>
                    <p class="field-label">Fecha de diligencia</p>
                    <p class="field-value">
                        {{ $diligencia->fecha_diligencia
                            ? $diligencia->fecha_diligencia->timezone('America/Bogota')->format('d/m/Y')
                            : '—' }}
                    </p>
                </div>
            </div>
        </div>

        {{-- ── Participante ── --}}
        <div class="section-card" style="--card-accent:#6366f1;">
            <div class="section-head">
                <lord-icon
                    src="https://cdn.lordicon.com/bushiqea.json"
                    trigger="loop" delay="2000" stroke="bold"
                    colors="primary:#4f46e5,secondary:#6366f1,tertiary:#c7d2fe"
                    style="width:20px;height:20px;flex-shrink:0;">
                </lord-icon>
                <span class="section-head-label">Participante</span>
            </div>
            <div class="p-5 grid grid-cols-2 sm:grid-cols-4 gap-x-4 gap-y-4">
                <div class="col-span-2">
                    <p class="field-label">Nombre completo</p>
                    <p class="field-value">{{ $trabajador->nombre_completo ?? '—' }}</p>
                </div>
                <div>
                    <p class="field-label">Documento</p>
                    <p class="field-value mono">
                        {{ $trabajador->tipo_documento ?? 'C.C.' }} {{ $trabajador->numero_documento ?? '—' }}
                    </p>
                </div>
                <div>
                    <p class="field-label">Cargo</p>
                    <p class="field-value">{{ $trabajador->cargo ?? '—' }}</p>
                </div>
                <div class="col-span-2">
                    <p class="field-label">IP de acceso</p>
                    <p class="field-value mono">{{ $diligencia->ip_acceso ?? '—' }}</p>
                </div>
            </div>
        </div>

        {{-- ── Fotos de verificación biométrica ── --}}
        @if($fotoInicioUrl || $fotoFinUrl)
        <div class="section-card" style="--card-accent:#a78bfa;">
            <div class="section-head">
                <lord-icon
                    src="https://cdn.lordicon.com/ocylgmkg.json"
                    trigger="loop" delay="2000" stroke="bold"
                    colors="primary:#4f46e5,secondary:#6366f1,tertiary:#c7d2fe"
                    style="width:20px;height:20px;flex-shrink:0;">
                </lord-icon>
                <span class="section-head-label">Verificación Biométrica Facial</span>
            </div>
            <div class="p-5 grid grid-cols-2 gap-4">
                @if($fotoInicioUrl)
                <div>
                    <p class="field-label mb-2">Inicio de la diligencia</p>
                    <div class="rounded-xl overflow-hidden border border-gray-200 bg-gray-50" style="aspect-ratio:4/3;">
                        <img src="{{ $fotoInicioUrl }}" alt="Foto inicio"
                             class="w-full h-full object-cover"
                             onerror="this.closest('div').innerHTML='<p style=\'display:flex;align-items:center;justify-content:center;height:100%;font-size:.75rem;color:#9ca3af;font-style:italic;\'>Imagen no disponible</p>'">
                    </div>
                    @if($diligencia->foto_inicio_en)
                    <p class="text-xs text-gray-400 mt-1.5 text-center mono">
                        {{ $diligencia->foto_inicio_en->timezone('America/Bogota')->format('d/m/Y · h:i A') }}
                    </p>
                    @endif
                </div>
                @endif

                @if($fotoFinUrl)
                <div>
                    <p class="field-label mb-2">Cierre de la diligencia</p>
                    <div class="rounded-xl overflow-hidden border border-gray-200 bg-gray-50" style="aspect-ratio:4/3;">
                        <img src="{{ $fotoFinUrl }}" alt="Foto cierre"
                             class="w-full h-full object-cover"
                             onerror="this.closest('div').innerHTML='<p style=\'display:flex;align-items:center;justify-content:center;height:100%;font-size:.75rem;color:#9ca3af;font-style:italic;\'>Imagen no disponible</p>'">
                    </div>
                    @if($diligencia->foto_fin_en)
                    <p class="text-xs text-gray-400 mt-1.5 text-center mono">
                        {{ $diligencia->foto_fin_en->timezone('America/Bogota')->format('d/m/Y · h:i A') }}
                    </p>
                    @endif
                </div>
                @endif
            </div>
        </div>
        @endif

        {{-- ── Cadena de autenticación ── --}}
        @if(!empty($autenticaciones))
        <div class="section-card" style="--card-accent:#34d399;">
            <div class="section-head">
                <lord-icon
                    src="https://cdn.lordicon.com/kfzfxczd.json"
                    trigger="loop" delay="2000" stroke="bold"
                    colors="primary:#4f46e5,secondary:#6366f1,tertiary:#c7d2fe"
                    style="width:20px;height:20px;flex-shrink:0;">
                </lord-icon>
                <span class="section-head-label">Cadena de Autenticación Digital</span>
            </div>
            <div class="p-5">
                @foreach($autenticaciones as $auth)
                <div class="flex items-stretch gap-3">
                    <div class="flex flex-col items-center flex-shrink-0" style="width:24px;">
                        <div class="w-6 h-6 rounded-full bg-green-100 border border-green-300 flex items-center justify-center flex-shrink-0" style="margin-top:2px;">
                            <lord-icon
                                src="https://cdn.lordicon.com/okqjaags.json"
                                trigger="loop"
                                delay="{{ 1500 + ($loop->index * 600) }}"
                                stroke="bold"
                                colors="primary:#15803d,secondary:#16a34a"
                                style="width:13px;height:13px;">
                            </lord-icon>
                        </div>
                        @if(!$loop->last)
                        <div class="auth-line"></div>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0 {{ !$loop->last ? 'pb-4' : 'pb-1' }}">
                        <p class="text-sm font-semibold" style="color:#0f172a;">{{ $auth['tipo'] }}</p>
                        <p class="text-xs mt-0.5 leading-relaxed" style="color:#64748b;">{{ $auth['detalle'] }}</p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- ── Integridad del documento ── --}}
        <div class="section-card" style="--card-accent:#c9a84c;">
            <div class="section-head">
                <lord-icon
                    src="https://cdn.lordicon.com/fihkmkwt.json"
                    trigger="loop" delay="2000" stroke="bold"
                    colors="primary:#4f46e5,secondary:#6366f1,tertiary:#c7d2fe"
                    style="width:20px;height:20px;flex-shrink:0;">
                </lord-icon>
                <span class="section-head-label">Integridad del Documento</span>
            </div>
            <div class="p-5 space-y-3">
                <div class="rounded-xl p-3" style="background:rgba(0,0,0,.03);border:1px solid rgba(0,0,0,.06);">
                    <p class="field-label">Token de verificación</p>
                    <p class="text-xs mono break-all leading-relaxed mt-1" style="color:#334155;">{{ $token }}</p>
                </div>
                @if($diligencia->verificacion_hash)
                <div class="rounded-xl p-3" style="background:rgba(0,0,0,.03);border:1px solid rgba(0,0,0,.06);">
                    <p class="field-label">Hash SHA-256 del documento</p>
                    <p class="text-xs mono break-all leading-relaxed mt-1" style="color:#334155;">{{ $diligencia->verificacion_hash }}</p>
                </div>
                @endif
                <div class="rounded-xl p-3" style="background:rgba(0,0,0,.03);border:1px solid rgba(0,0,0,.06);">
                    <p class="field-label">Documento generado el</p>
                    <p class="field-value mt-1">
                        {{ $diligencia->verificacion_generada_en
                            ? $diligencia->verificacion_generada_en->timezone('America/Bogota')->format('d/m/Y \a \l\a\s h:i:s A')
                            : '—' }}
                    </p>
                </div>
            </div>
        </div>

    </main>

    {{-- ── Footer ── --}}
    <footer class="max-w-2xl mx-auto px-4 sm:px-6 pb-10 pt-4 text-center">
        <p class="text-xs leading-relaxed" style="color:#94a3b8;">
            Verificación provista por
            <a href="https://www.ceslegal.co" target="_blank" rel="noopener"
               class="text-primary-600 hover:underline font-medium">CES Legal</a>
            · Plataforma de Gestión Disciplinaria Laboral<br>
            Ley 527/1999 (Comercio Electrónico) · Decreto 2364/2012 (Firma Electrónica)
        </p>
    </footer>

    {{-- ── Canvas particle network ── --}}
    <script>
    (function() {
        var ID     = '{{ $heroId }}';
        var hero   = document.getElementById(ID);
        var canvas = document.getElementById(ID + '_canvas');
        if (!canvas || !hero) return;

        var ctx = canvas.getContext('2d'), raf, pts = [],
            mouse = { x: -9999, y: -9999 }, tick = 0;

        function resize() { canvas.width = hero.offsetWidth; canvas.height = hero.offsetHeight; }
        resize();
        window.addEventListener('resize', function() { resize(); pts = []; init(); });

        hero.addEventListener('mousemove', function(e) {
            var r = hero.getBoundingClientRect();
            mouse.x = e.clientX - r.left; mouse.y = e.clientY - r.top;
        });
        hero.addEventListener('mouseleave', function() { mouse.x = -9999; mouse.y = -9999; });

        function init() {
            pts = [];
            for (var i = 0; i < 22; i++) {
                var spd = Math.random() * .45 + .2, ang = Math.random() * Math.PI * 2;
                pts.push({ x: Math.random() * canvas.width, y: Math.random() * canvas.height,
                    vx: Math.cos(ang) * spd, vy: Math.sin(ang) * spd,
                    r: Math.random() * 1.4 + .4, phase: Math.random() * Math.PI * 2 });
            }
        }
        init();

        function draw() {
            tick += .016;
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            var dc = 'rgba(180,80,10,';
            pts.forEach(function(p) {
                var mx = mouse.x - p.x, my = mouse.y - p.y,
                    md = Math.sqrt(mx*mx + my*my);
                if (md < 160 && md > 1) { p.vx += (mx/md)*.016; p.vy += (my/md)*.016; }
                var sp = Math.sqrt(p.vx*p.vx + p.vy*p.vy);
                if (sp > 1.2) { p.vx *= .93; p.vy *= .93; }
                if (sp < .15) { p.vx *= 1.07; p.vy *= 1.07; }
                p.x += p.vx; p.y += p.vy;
                if (p.x < 0 || p.x > canvas.width)  p.vx *= -1;
                if (p.y < 0 || p.y > canvas.height) p.vy *= -1;
                var pa = .25 + .2 * Math.sin(tick * 1.2 + p.phase);
                ctx.beginPath(); ctx.arc(p.x, p.y, p.r, 0, Math.PI*2);
                ctx.fillStyle = dc + pa + ')'; ctx.fill();
            });
            for (var a = 0; a < pts.length; a++)
                for (var b = a+1; b < pts.length; b++) {
                    var dx = pts[a].x - pts[b].x, dy = pts[a].y - pts[b].y,
                        d = Math.sqrt(dx*dx + dy*dy);
                    if (d < 80) {
                        ctx.beginPath(); ctx.moveTo(pts[a].x, pts[a].y); ctx.lineTo(pts[b].x, pts[b].y);
                        ctx.strokeStyle = dc + (.18*(1 - d/110)) + ')';
                        ctx.lineWidth = .55; ctx.stroke();
                    }
                }
            raf = requestAnimationFrame(draw);
        }
        draw();

        if ('IntersectionObserver' in window)
            new IntersectionObserver(function(e) {
                if (!e[0].isIntersecting) cancelAnimationFrame(raf); else draw();
            }).observe(hero);
    })();

    /* ── Section card shimmer (mouse tracking) ── */
    document.querySelectorAll('.section-card').forEach(function(card) {
        card.addEventListener('mousemove', function(e) {
            var r = card.getBoundingClientRect();
            card.style.setProperty('--mx', ((e.clientX - r.left) / r.width * 100) + '%');
            card.style.setProperty('--my', ((e.clientY - r.top)  / r.height * 100) + '%');
        });
    });
    </script>

</body>
</html>
