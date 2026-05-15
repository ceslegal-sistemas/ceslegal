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
                        success: {
                            50:  '#f0fdf4',
                            100: '#dcfce7',
                            200: '#bbf7d0',
                            500: '#22c55e',
                            600: '#16a34a',
                            700: '#15803d',
                            800: '#166534',
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
        .mono {
            font-family: 'SF Mono', 'Fira Code', 'Courier New', monospace;
        }
        .auth-line {
            width: 2px;
            background: #bbf7d0;
            flex: 1;
            margin: 3px 0;
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
                    trigger="loop"
                    delay="3000"
                    stroke="bold"
                    colors="primary:#9ca3af,secondary:#d1d5db"
                    style="width:15px;height:15px;">
                </lord-icon>
                Portal de Verificación
            </div>
        </div>
    </header>

    <main class="max-w-2xl mx-auto px-4 sm:px-6 py-6 space-y-4">

        {{-- ── Sello de verificación ── --}}
        <div class="bg-success-50 border border-success-200 rounded-2xl p-5">
            <div class="flex items-start gap-4">
                <div class="w-14 h-14 bg-success-100 border border-success-200 rounded-full flex items-center justify-center flex-shrink-0">
                    <lord-icon
                        src="https://cdn.lordicon.com/wloilxuq.json"
                        trigger="loop"
                        delay="1500"
                        stroke="bold"
                        colors="primary:#15803d,secondary:#16a34a,tertiary:#bbf7d0"
                        style="width:34px;height:34px;">
                    </lord-icon>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-bold text-success-800 uppercase tracking-wider mb-1">Documento Verificado</p>
                    <p class="text-sm text-success-700 leading-relaxed">
                        Este documento fue generado por la plataforma CES Legal y su autenticidad ha sido confirmada.
                        La identidad del participante fue validada mediante OTP y verificación facial biométrica.
                    </p>
                </div>
            </div>
        </div>

        {{-- ── Aviso legal ── --}}
        <div class="bg-warning-50 border border-warning-200 rounded-xl px-4 py-3 flex gap-3 items-start">
            <lord-icon
                src="https://cdn.lordicon.com/msoeawqm.json"
                trigger="loop"
                delay="4000"
                stroke="bold"
                colors="primary:#d97706,secondary:#f59e0b"
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
                    trigger="loop"
                    delay="2000"
                    stroke="bold"
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
                    trigger="loop"
                    delay="2000"
                    stroke="bold"
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
                    trigger="loop"
                    delay="2000"
                    stroke="bold"
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
                        <img src="{{ $fotoInicioUrl }}"
                             alt="Foto inicio"
                             class="w-full h-full object-cover"
                             onerror="this.closest('div').innerHTML='<p style=\'display:flex;align-items:center;justify-content:center;height:100%;font-size:0.75rem;color:#9ca3af;font-style:italic;\'>Imagen no disponible</p>'">
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
                        <img src="{{ $fotoFinUrl }}"
                             alt="Foto cierre"
                             class="w-full h-full object-cover"
                             onerror="this.closest('div').innerHTML='<p style=\'display:flex;align-items:center;justify-content:center;height:100%;font-size:0.75rem;color:#9ca3af;font-style:italic;\'>Imagen no disponible</p>'">
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
                    trigger="loop"
                    delay="2000"
                    stroke="bold"
                    colors="primary:#4f46e5,secondary:#6366f1,tertiary:#c7d2fe"
                    style="width:20px;height:20px;flex-shrink:0;">
                </lord-icon>
                <span class="section-head-label">Cadena de Autenticación Digital</span>
            </div>
            <div class="p-5">
                @foreach($autenticaciones as $auth)
                <div class="flex items-stretch gap-3">
                    {{-- Conector vertical --}}
                    <div class="flex flex-col items-center flex-shrink-0" style="width:24px;">
                        <div class="w-6 h-6 rounded-full bg-success-100 border border-success-300 flex items-center justify-center flex-shrink-0" style="margin-top:2px;">
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
                    {{-- Contenido --}}
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
                    trigger="loop"
                    delay="2000"
                    stroke="bold"
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

</body>
</html>
