@php
    $heroId = 'hv_' . substr(md5('verificacion'), 0, 8);

    /* Fireflies — generated server-side for deterministic layout */
    $ffColors = ['201,168,76', '255,235,120', '255,255,200', '190,215,255', '245,195,255'];
    $fireflies = [];
    for ($i = 0; $i < 24; $i++) {
        $c  = $ffColors[array_rand($ffColors)];
        $sz = round(mt_rand(20, 55) / 10, 1);
        $g  = (int)(($sz * mt_rand(35, 60)) / 10);
        $fireflies[] = [
            'x'   => mt_rand(2, 97),
            'y'   => mt_rand(5, 95),
            'sz'  => $sz,
            'g'   => $g,
            'c'   => $c,
            'tw'  => round(mt_rand(20, 55) / 10, 1),
            'del' => round(mt_rand(0,  55) / 10, 1),
            'dr'  => round(mt_rand(70, 150) / 10, 1),
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

        /* ── Hero keyframes ── */
        @keyframes bv-up  { from { opacity:0; transform:translateY(20px) } to { opacity:1; transform:translateY(0) } }
        @keyframes bv-pop { from { opacity:0; transform:scale(.55) }        to { opacity:1; transform:scale(1) } }
        @keyframes bv-glow {
            0%,100% { box-shadow:0 0 0  0   rgba(201,168,76,.5) }
            65%     { box-shadow:0 0 0 14px  rgba(201,168,76,0) }
        }
        @keyframes bv-twinkle {
            0%,100% { opacity:.04; transform:scale(.3)  }
            45%,55% { opacity:1;   transform:scale(1.3) }
        }
        @keyframes bv-drift {
            0%,100% { transform:translate(0,0) }
            30%     { transform:translate(10px,-16px) }
            65%     { transform:translate(-8px,-10px) }
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

        .bv-a1 { animation:bv-up .6s cubic-bezier(.16,1,.3,1) both }
        .bv-a2 { animation:bv-up .6s .12s cubic-bezier(.16,1,.3,1) both }

        .bv-icon-ring {
            animation: bv-pop .7s .08s cubic-bezier(.34,1.56,.64,1) both,
                       bv-glow 3s 1.2s ease-in-out infinite;
        }

        /* ── Hero container ── */
        .bv-hero {
            position: relative;
            overflow: hidden;
            border-radius: 1.125rem;
            padding: 2rem 1.25rem 1.75rem;
            text-align: center;
            background: linear-gradient(155deg, #060f22 0%, #091830 50%, #060e20 100%);
        }
        @media(min-width:540px) {
            .bv-hero { border-radius:1.375rem; padding:2.5rem 2rem 2.25rem; }
        }

        .bv-hero-overlay {
            position: absolute; inset: 0; pointer-events: none; z-index: 1;
            background: radial-gradient(ellipse 75% 85% at 50% 50%,
                rgba(3,8,20,.84) 0%, rgba(3,8,20,.52) 50%, transparent 100%);
        }
        .bv-hero-orb-blue { animation: bv-float-blue 11s ease-in-out infinite; }
        .bv-hero-orb-gold { animation: bv-float-gold 14s ease-in-out infinite; }

        .bv-firefly {
            position: absolute; border-radius: 50%; pointer-events: none; will-change: transform, opacity;
            animation: bv-twinkle var(--tw) var(--del) ease-in-out infinite,
                       bv-drift   var(--dr) var(--del) ease-in-out infinite;
        }

        /* ── Cards / sections ── */
        .section-card {
            background: white;
            border-radius: 1rem;
            border: 1px solid #e5e7eb;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 1px 2px rgba(0,0,0,0.02);
        }
        .section-head {
            border-left: 3px solid #6366f1;
            background: #f9fafb;
            border-bottom: 1px solid #f3f4f6;
            padding: 0.625rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .section-head-label {
            font-size: 0.6875rem;
            font-weight: 700;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.07em;
        }
        .field-label {
            font-size: 0.6875rem;
            font-weight: 500;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 0.2rem;
        }
        .field-value {
            font-size: 0.875rem;
            font-weight: 600;
            color: #111827;
        }
        .mono { font-family: 'SF Mono','Fira Code','Courier New',monospace; }
        .auth-line { width:2px; background:#bbf7d0; flex:1; margin:3px 0; }

        @media(prefers-reduced-motion:reduce) {
            .bv-a1,.bv-a2,.bv-icon-ring,.bv-hero-orb-blue,.bv-hero-orb-gold,.bv-firefly
            { animation:none; opacity:.6; transform:none; }
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

        {{-- ── Hero: Documento Verificado (estilo bienvenida-proceso) ── --}}
        <div class="bv-hero bv-a1" id="{{ $heroId }}">
            <canvas id="{{ $heroId }}_canvas"
                style="position:absolute;inset:0;width:100%;height:100%;pointer-events:none;opacity:.5;"></canvas>

            {{-- Orbs flotantes --}}
            <div style="position:absolute;inset:0;pointer-events:none;overflow:hidden;">
                <div class="bv-hero-orb-blue"
                    style="position:absolute;width:280px;height:280px;top:-70px;right:-50px;border-radius:50%;
                           background:radial-gradient(circle,rgba(30,58,138,.55),transparent 70%);filter:blur(28px);">
                </div>
                <div class="bv-hero-orb-gold"
                    style="position:absolute;width:200px;height:200px;bottom:-50px;left:-40px;border-radius:50%;
                           background:radial-gradient(circle,rgba(201,168,76,.25),transparent 70%);filter:blur(26px);">
                </div>

                {{-- Fireflies --}}
                @foreach($fireflies as $f)
                <div class="bv-firefly" style="
                    left:{{ $f['x'] }}%; top:{{ $f['y'] }}%;
                    width:{{ $f['sz'] }}px; height:{{ $f['sz'] }}px;
                    background:rgb({{ $f['c'] }});
                    box-shadow:0 0 {{ $f['g'] }}px {{ $f['g']*2 }}px rgba({{ $f['c'] }},.55),
                               0 0 {{ $f['g']*4 }}px {{ $f['g']*3 }}px rgba({{ $f['c'] }},.18);
                    --tw:{{ $f['tw'] }}s; --del:{{ $f['del'] }}s; --dr:{{ $f['dr'] }}s;">
                </div>
                @endforeach
            </div>

            <div class="bv-hero-overlay"></div>

            {{-- Contenido --}}
            <div style="position:relative;z-index:2;">

                {{-- Ícono en anillo dorado --}}
                <div class="bv-icon-ring" style="
                    display:inline-flex;align-items:center;justify-content:center;
                    width:64px;height:64px;border-radius:50%;margin-bottom:1.125rem;
                    background:rgba(201,168,76,.12);border:1.5px solid rgba(201,168,76,.35);">
                    <lord-icon
                        src="https://cdn.lordicon.com/wloilxuq.json"
                        trigger="loop" delay="800" stroke="bold"
                        colors="primary:#c9a84c,secondary:#f0d07a,tertiary:#8a6a1e"
                        style="width:48px;height:48px;">
                    </lord-icon>
                </div>

                {{-- Supratítulo dorado --}}
                <p style="color:#c9a84c;font-size:.7rem;font-weight:700;letter-spacing:.18em;
                           text-transform:uppercase;margin:0 0 .4rem;
                           text-shadow:0 0 18px rgba(201,168,76,.65),0 2px 8px rgba(0,0,0,.7);">
                    Verificador de Identidad
                </p>

                {{-- Título principal --}}
                <h1 style="color:#f1f5f9;font-size:1.5rem;font-weight:700;letter-spacing:-.02em;
                            line-height:1.25;margin:0 0 .875rem;
                            text-shadow:0 2px 24px rgba(0,0,0,.88),0 1px 4px rgba(0,0,0,.65);">
                    Documento Verificado
                </h1>

                {{-- Descripción --}}
                <p style="color:#cbd5e1;font-size:.875rem;font-weight:500;line-height:1.65;
                           margin:0 auto;max-width:420px;
                           text-shadow:0 1px 8px rgba(0,0,0,.5);">
                    Este documento fue generado por la plataforma CES Legal y su autenticidad ha sido confirmada.
                    La identidad del participante fue validada mediante
                    <span style="color:#c9a84c;font-weight:600;">OTP</span>
                    y verificación
                    <span style="color:#c9a84c;font-weight:600;">facial biométrica</span>.
                </p>

            </div>
        </div>

        {{-- ── Aviso legal ── --}}
        <div class="bg-warning-50 border border-warning-200 rounded-xl px-4 py-3 flex gap-3 items-start">
            <lord-icon
                src="https://cdn.lordicon.com/msoeawqm.json"
                trigger="loop" delay="4000" stroke="bold"
                colors="primary:#92400e,secondary:#b45309"
                style="width:17px;height:17px;flex-shrink:0;margin-top:1px;">
            </lord-icon>
            <p class="text-xs text-warning-800 leading-relaxed">
                <span class="font-semibold">Nota:</span>
                CES Legal actúa exclusivamente como proveedora del servicio tecnológico de gestión disciplinaria.
                La decisión disciplinaria es responsabilidad exclusiva de
                <span class="font-semibold">{{ $empresa->razon_social ?? 'el empleador' }}</span>.
                Válido conforme a la <span class="font-semibold">Ley 527/1999</span>
                y el <span class="font-semibold">Decreto 2364/2012</span>.
            </p>
        </div>

        {{-- ── Proceso Disciplinario ── --}}
        <div class="section-card">
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
        <div class="section-card">
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
        <div class="section-card">
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
        <div class="section-card">
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
                        <p class="text-sm font-semibold text-gray-900">{{ $auth['tipo'] }}</p>
                        <p class="text-xs text-gray-500 mt-0.5 leading-relaxed">{{ $auth['detalle'] }}</p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- ── Integridad del documento ── --}}
        <div class="section-card">
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
                <div class="bg-gray-50 border border-gray-100 rounded-xl p-3">
                    <p class="field-label">Token de verificación</p>
                    <p class="text-xs text-gray-700 mono break-all leading-relaxed mt-1">{{ $token }}</p>
                </div>
                @if($diligencia->verificacion_hash)
                <div class="bg-gray-50 border border-gray-100 rounded-xl p-3">
                    <p class="field-label">Hash SHA-256 del documento</p>
                    <p class="text-xs text-gray-700 mono break-all leading-relaxed mt-1">{{ $diligencia->verificacion_hash }}</p>
                </div>
                @endif
                <div class="bg-gray-50 border border-gray-100 rounded-xl p-3">
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
        <p class="text-xs text-gray-400 leading-relaxed">
            Verificación provista por
            <a href="https://www.ceslegal.co" target="_blank" rel="noopener"
               class="text-primary-600 hover:underline font-medium">CES Legal</a>
            · Plataforma de Gestión Disciplinaria Laboral<br>
            Ley 527/1999 (Comercio Electrónico) · Decreto 2364/2012 (Firma Electrónica)
        </p>
    </footer>

    {{-- ── Canvas particle network (mismo del bienvenida-proceso) ── --}}
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
            var dc = 'rgba(201,168,76,';
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
                var pa = .45 + .3 * Math.sin(tick * 1.2 + p.phase);
                ctx.beginPath(); ctx.arc(p.x, p.y, p.r, 0, Math.PI*2);
                ctx.fillStyle = dc + pa + ')'; ctx.fill();
            });
            for (var a = 0; a < pts.length; a++)
                for (var b = a+1; b < pts.length; b++) {
                    var dx = pts[a].x - pts[b].x, dy = pts[a].y - pts[b].y,
                        d = Math.sqrt(dx*dx + dy*dy);
                    if (d < 80) {
                        ctx.beginPath(); ctx.moveTo(pts[a].x, pts[a].y); ctx.lineTo(pts[b].x, pts[b].y);
                        ctx.strokeStyle = dc + (.3*(1 - d/110)) + ')';
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
    </script>

</body>
</html>
