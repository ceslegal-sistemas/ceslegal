<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación de Autenticidad · CES Legal</title>
    <link rel="icon" href="/images/ces-legal-logo.png" type="image/png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Outfit', 'system-ui', 'sans-serif'],
                        mono: ['"JetBrains Mono"', '"Courier New"', 'monospace'],
                    },
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
                            950: '#1e1b4b',
                        }
                    }
                }
            }
        }
    </script>

    <style>
        body { font-family: 'Outfit', system-ui, sans-serif; }

        @keyframes shield-pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(16,185,129,0.4); }
            50%       { box-shadow: 0 0 0 12px rgba(16,185,129,0); }
        }
        .shield-pulse { animation: shield-pulse 2.8s ease-in-out infinite; }

        .card {
            background: #fff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            box-shadow: 0 1px 4px 0 rgba(0,0,0,0.04);
        }
        .card-head {
            background: linear-gradient(to right, #f8fafc, #f1f5f9);
            border-bottom: 1px solid #e2e8f0;
            padding: 11px 20px;
            display: flex;
            align-items: center;
            gap: 7px;
        }
        .card-head-label {
            font-size: 10.5px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.09em;
        }
        .field-label {
            font-size: 10px;
            font-weight: 600;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 3px;
        }

        /* Terminal-style hash blocks */
        .code-block {
            background: #0f172a;
            border-radius: 10px;
            padding: 12px 14px;
        }
        .code-block-label {
            font-size: 9.5px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #475569;
            margin-bottom: 6px;
            font-family: 'Outfit', sans-serif;
        }
        .code-block-value {
            font-family: 'JetBrains Mono', 'Courier New', monospace;
            font-size: 11px;
            color: #4ade80;
            word-break: break-all;
            line-height: 1.7;
        }

        /* Auth chain connector */
        .auth-line { width: 2px; background: #dcfce7; margin: 2px 0 2px 13px; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen antialiased">

    {{-- ── Header ── --}}
    <header class="bg-primary-950 sticky top-0 z-10 border-b border-primary-900/60">
        <div class="max-w-2xl mx-auto px-4 h-15 py-3 flex items-center justify-between">
            <img src="/images/ces-legal-logo.png" alt="CES Legal" class="h-9 w-auto">
            <div class="flex items-center gap-1.5 bg-white/5 border border-white/10 rounded-full px-3 py-1.5">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse inline-block"></span>
                <span class="text-xs text-primary-200 font-medium">Portal de Verificación</span>
            </div>
        </div>
    </header>

    <main class="max-w-2xl mx-auto px-4 py-7 space-y-4">

        {{-- ── Sello verificado ── --}}
        <div class="relative rounded-2xl overflow-hidden" style="background: linear-gradient(135deg, #059669 0%, #10b981 60%, #34d399 100%);">
            {{-- Subtle grid overlay --}}
            <div class="absolute inset-0 opacity-[0.07]"
                 style="background-image: linear-gradient(white 1px, transparent 1px), linear-gradient(90deg, white 1px, transparent 1px); background-size: 28px 28px;"></div>

            <div class="relative p-6 flex items-center gap-5">
                <div class="shield-pulse flex-shrink-0 w-16 h-16 rounded-full bg-white/20 border border-white/30 flex items-center justify-center backdrop-blur-sm">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-emerald-100/80 text-xs font-semibold uppercase tracking-widest mb-1">Estado del documento</p>
                    <h1 class="text-2xl font-bold text-white tracking-tight leading-none">DOCUMENTO VERIFICADO</h1>
                    <p class="text-emerald-50/80 text-sm mt-2 leading-snug">
                        Autenticidad confirmada por la plataforma CES Legal.<br>
                        Identidad del participante validada mediante OTP y biometría facial.
                    </p>
                </div>
            </div>
        </div>

        {{-- ── Aviso legal ── --}}
        <div class="flex gap-3 items-start bg-amber-50 border border-amber-200 rounded-xl px-4 py-3">
            <svg class="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/>
            </svg>
            <p class="text-xs text-amber-800 leading-relaxed">
                <span class="font-semibold">Nota:</span>
                CES Legal actúa exclusivamente como proveedora del servicio tecnológico de gestión disciplinaria.
                La decisión disciplinaria es responsabilidad exclusiva de
                <span class="font-semibold">{{ $empresa->razon_social ?? 'el empleador' }}</span>.
                Verificación válida conforme a la <span class="font-semibold">Ley 527/1999</span>
                y el <span class="font-semibold">Decreto 2364/2012</span>.
            </p>
        </div>

        {{-- ── Proceso Disciplinario ── --}}
        <div class="card">
            <div class="card-head">
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
                </svg>
                <span class="card-head-label">Proceso Disciplinario</span>
            </div>
            <div class="p-5 grid grid-cols-2 sm:grid-cols-4 gap-x-4 gap-y-4">
                <div>
                    <p class="field-label">N.° proceso</p>
                    <span class="inline-block px-2.5 py-0.5 bg-primary-50 text-primary-700 text-xs font-bold rounded-full border border-primary-100">
                        {{ $proceso->codigo ?? '—' }}
                    </span>
                </div>
                <div class="col-span-1">
                    <p class="field-label">Empresa</p>
                    <p class="text-sm font-semibold text-slate-800">{{ $empresa->razon_social ?? '—' }}</p>
                </div>
                <div>
                    <p class="field-label">NIT</p>
                    <p class="text-sm font-semibold text-slate-800 font-mono">{{ $empresa->nit ?? '—' }}</p>
                </div>
                <div>
                    <p class="field-label">Fecha de diligencia</p>
                    <p class="text-sm font-semibold text-slate-800">
                        {{ $diligencia->fecha_diligencia
                            ? $diligencia->fecha_diligencia->timezone('America/Bogota')->format('d/m/Y')
                            : '—' }}
                    </p>
                </div>
            </div>
        </div>

        {{-- ── Participante ── --}}
        <div class="card">
            <div class="card-head">
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                </svg>
                <span class="card-head-label">Participante</span>
            </div>
            <div class="p-5 grid grid-cols-2 sm:grid-cols-4 gap-x-4 gap-y-4">
                <div class="col-span-2">
                    <p class="field-label">Nombre completo</p>
                    <p class="text-sm font-semibold text-slate-800">{{ $trabajador->nombre_completo ?? '—' }}</p>
                </div>
                <div>
                    <p class="field-label">Documento</p>
                    <p class="text-sm font-semibold text-slate-800 font-mono">
                        {{ $trabajador->tipo_documento ?? 'C.C.' }} {{ $trabajador->numero_documento ?? '—' }}
                    </p>
                </div>
                <div>
                    <p class="field-label">Cargo</p>
                    <p class="text-sm font-semibold text-slate-800">{{ $trabajador->cargo ?? '—' }}</p>
                </div>
                <div class="col-span-2">
                    <p class="field-label">IP de acceso</p>
                    <p class="text-sm font-semibold text-slate-800 font-mono">{{ $diligencia->ip_acceso ?? '—' }}</p>
                </div>
            </div>
        </div>

        {{-- ── Fotos biométricas ── --}}
        @if($fotoInicioUrl || $fotoFinUrl)
        <div class="card">
            <div class="card-head">
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z"/>
                </svg>
                <span class="card-head-label">Verificación Biométrica Facial</span>
            </div>
            <div class="p-5 grid grid-cols-2 gap-4">
                @if($fotoInicioUrl)
                <div class="space-y-2">
                    <p class="field-label">Inicio de la diligencia</p>
                    <div class="rounded-xl overflow-hidden border border-slate-200 bg-slate-50" style="aspect-ratio:4/3;">
                        <img src="{{ $fotoInicioUrl }}"
                             alt="Foto inicio"
                             class="w-full h-full object-cover"
                             onerror="this.closest('div').innerHTML='<p class=\'flex items-center justify-center h-full text-xs text-slate-400 italic\'>Imagen no disponible</p>'">
                    </div>
                    @if($diligencia->foto_inicio_en)
                    <p class="text-xs text-slate-400 text-center font-mono">
                        {{ $diligencia->foto_inicio_en->timezone('America/Bogota')->format('d/m/Y · h:i A') }}
                    </p>
                    @endif
                </div>
                @endif

                @if($fotoFinUrl)
                <div class="space-y-2">
                    <p class="field-label">Cierre de la diligencia</p>
                    <div class="rounded-xl overflow-hidden border border-slate-200 bg-slate-50" style="aspect-ratio:4/3;">
                        <img src="{{ $fotoFinUrl }}"
                             alt="Foto cierre"
                             class="w-full h-full object-cover"
                             onerror="this.closest('div').innerHTML='<p class=\'flex items-center justify-center h-full text-xs text-slate-400 italic\'>Imagen no disponible</p>'">
                    </div>
                    @if($diligencia->foto_fin_en)
                    <p class="text-xs text-slate-400 text-center font-mono">
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
        <div class="card">
            <div class="card-head">
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/>
                </svg>
                <span class="card-head-label">Cadena de Autenticación Digital</span>
            </div>
            <div class="p-5 space-y-0">
                @foreach($autenticaciones as $auth)
                <div class="flex items-stretch gap-3 {{ !$loop->last ? 'mb-0' : '' }}">
                    <div class="flex flex-col items-center flex-shrink-0">
                        <div class="w-7 h-7 bg-emerald-500 rounded-full flex items-center justify-center mt-0.5">
                            <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                        </div>
                        @if(!$loop->last)
                        <div class="auth-line flex-1 my-1"></div>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0 {{ !$loop->last ? 'pb-4' : '' }}">
                        <p class="text-sm font-semibold text-slate-800">{{ $auth['tipo'] }}</p>
                        <p class="text-xs text-slate-500 mt-0.5">{{ $auth['detalle'] }}</p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- ── Integridad del documento ── --}}
        <div class="card">
            <div class="card-head">
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/>
                </svg>
                <span class="card-head-label">Integridad del Documento</span>
            </div>
            <div class="p-5 space-y-3">
                <div class="code-block">
                    <p class="code-block-label">Token de verificación</p>
                    <p class="code-block-value">{{ $token }}</p>
                </div>
                @if($diligencia->verificacion_hash)
                <div class="code-block">
                    <p class="code-block-label">Hash SHA-256 del documento</p>
                    <p class="code-block-value">{{ $diligencia->verificacion_hash }}</p>
                </div>
                @endif
                <div class="bg-slate-50 border border-slate-100 rounded-xl p-3">
                    <p class="field-label">Documento generado el</p>
                    <p class="text-sm font-semibold text-slate-800">
                        {{ $diligencia->verificacion_generada_en
                            ? $diligencia->verificacion_generada_en->timezone('America/Bogota')->format('d/m/Y \a \l\a\s h:i:s A')
                            : '—' }}
                    </p>
                </div>
            </div>
        </div>

    </main>

    {{-- ── Footer ── --}}
    <footer class="max-w-2xl mx-auto px-4 pt-4 pb-10 text-center">
        <img src="/images/ces-legal-logo.png" alt="CES Legal" class="h-7 w-auto mx-auto mb-3 opacity-40">
        <p class="text-xs text-slate-400 leading-relaxed">
            Verificación provista por
            <a href="https://www.ceslegal.co" target="_blank" rel="noopener" class="text-primary-600 hover:underline font-medium">CES Legal</a>
            · Plataforma de Gestión Disciplinaria Laboral<br>
            Ley 527/1999 (Comercio Electrónico) · Decreto 2364/2012 (Firma Electrónica)
        </p>
    </footer>

</body>
</html>
