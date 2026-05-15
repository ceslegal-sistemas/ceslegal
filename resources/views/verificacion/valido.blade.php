<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación de Documento · CES Legal</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">

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
                        }
                    }
                }
            }
        }
    </script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Inter', system-ui, sans-serif; }
        .mono { font-family: 'Courier New', monospace; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen antialiased">

    {{-- ── Navbar ── --}}
    <header class="bg-white border-b border-gray-200 sticky top-0 z-10">
        <div class="max-w-3xl mx-auto px-4 h-14 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <img src="/images/ces-legal-logo.png" alt="CES Legal" class="h-8 w-auto">
            </div>
            <span class="text-xs text-gray-400">Plataforma de Gestión Disciplinaria</span>
        </div>
    </header>

    <main class="max-w-3xl mx-auto px-4 py-8 space-y-4">

        {{-- ── Sello de verificación ── --}}
        <div class="bg-green-50 border border-green-200 rounded-2xl p-5 flex items-start gap-4">
            <div class="w-12 h-12 bg-green-500 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
            </div>
            <div>
                <p class="text-base font-bold text-green-800 leading-tight">Documento Verificado</p>
                <p class="text-sm text-green-700 mt-1 leading-relaxed">
                    Este documento fue generado por la plataforma CES Legal y su autenticidad ha sido confirmada.
                    La identidad del participante fue validada mediante OTP y verificación facial biométrica.
                </p>
            </div>
        </div>

        {{-- ── Aviso CES Legal como proveedor ── --}}
        <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-xs text-amber-800 leading-relaxed">
            <span class="font-semibold">Nota:</span>
            CES Legal actúa exclusivamente como proveedora del servicio tecnológico de gestión disciplinaria.
            La decisión disciplinaria es responsabilidad exclusiva de
            <span class="font-semibold">{{ $empresa->razon_social ?? 'el empleador' }}</span>.
            Verificación válida conforme a la
            <span class="font-semibold">Ley 527 de 1999</span> y el
            <span class="font-semibold">Decreto 2364 de 2012</span>.
        </div>

        {{-- ── Datos del proceso ── --}}
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
            <div class="px-5 py-3 border-b border-gray-100 bg-gray-50">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Proceso Disciplinario</p>
            </div>
            <div class="p-5 grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div>
                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">N.° de proceso</p>
                    <span class="inline-block px-2.5 py-0.5 bg-primary-100 text-primary-700 text-xs font-bold rounded-full">
                        {{ $proceso->codigo ?? '—' }}
                    </span>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">Empresa</p>
                    <p class="text-sm font-semibold text-gray-800">{{ $empresa->razon_social ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">NIT</p>
                    <p class="text-sm font-semibold text-gray-800 mono">{{ $empresa->nit ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">Fecha de diligencia</p>
                    <p class="text-sm font-semibold text-gray-800">
                        {{ $diligencia->fecha_diligencia
                            ? $diligencia->fecha_diligencia->timezone('America/Bogota')->format('d/m/Y')
                            : '—' }}
                    </p>
                </div>
            </div>
        </div>

        {{-- ── Datos del trabajador ── --}}
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
            <div class="px-5 py-3 border-b border-gray-100 bg-gray-50">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Participante</p>
            </div>
            <div class="p-5 grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div class="col-span-2 sm:col-span-2">
                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">Nombre completo</p>
                    <p class="text-sm font-semibold text-gray-800">{{ $trabajador->nombre_completo ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">Documento</p>
                    <p class="text-sm font-semibold text-gray-800 mono">
                        {{ $trabajador->tipo_documento ?? 'C.C.' }} {{ $trabajador->numero_documento ?? '—' }}
                    </p>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">Cargo</p>
                    <p class="text-sm font-semibold text-gray-800">{{ $trabajador->cargo ?? '—' }}</p>
                </div>
                <div class="col-span-2">
                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">IP de acceso</p>
                    <p class="text-sm font-semibold text-gray-800 mono">{{ $diligencia->ip_acceso ?? '—' }}</p>
                </div>
            </div>
        </div>

        {{-- ── Fotos de verificación ── --}}
        @if($fotoInicioUrl || $fotoFinUrl)
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
            <div class="px-5 py-3 border-b border-gray-100 bg-gray-50">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Fotos de Verificación Biométrica</p>
            </div>
            <div class="p-5 grid grid-cols-2 gap-4">
                @if($fotoInicioUrl)
                <div>
                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-2">Inicio de la diligencia</p>
                    <img src="{{ $fotoInicioUrl }}"
                         alt="Foto verificación inicio"
                         class="w-full rounded-xl border border-gray-200 object-cover"
                         style="max-height: 220px; object-fit: cover;"
                         onerror="this.parentElement.innerHTML='<p class=\'text-xs text-gray-400 italic\'>Imagen no disponible</p>'">
                    @if($diligencia->foto_inicio_en)
                    <p class="text-xs text-gray-400 mt-1.5 text-center">
                        {{ $diligencia->foto_inicio_en->timezone('America/Bogota')->format('d/m/Y h:i A') }}
                    </p>
                    @endif
                </div>
                @endif

                @if($fotoFinUrl)
                <div>
                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-2">Cierre de la diligencia</p>
                    <img src="{{ $fotoFinUrl }}"
                         alt="Foto verificación cierre"
                         class="w-full rounded-xl border border-gray-200 object-cover"
                         style="max-height: 220px; object-fit: cover;"
                         onerror="this.parentElement.innerHTML='<p class=\'text-xs text-gray-400 italic\'>Imagen no disponible</p>'">
                    @if($diligencia->foto_fin_en)
                    <p class="text-xs text-gray-400 mt-1.5 text-center">
                        {{ $diligencia->foto_fin_en->timezone('America/Bogota')->format('d/m/Y h:i A') }}
                    </p>
                    @endif
                </div>
                @endif
            </div>
        </div>
        @endif

        {{-- ── Cadena de autenticación ── --}}
        @if(!empty($autenticaciones))
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
            <div class="px-5 py-3 border-b border-gray-100 bg-gray-50">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Cadena de Autenticación Digital</p>
            </div>
            <div class="p-5 space-y-2">
                @foreach($autenticaciones as $auth)
                <div class="flex items-start gap-3 p-3 bg-green-50 border border-green-100 rounded-xl">
                    <div class="w-6 h-6 bg-green-500 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                        <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-green-800">{{ $auth['tipo'] }}</p>
                        <p class="text-xs text-green-700 mt-0.5">{{ $auth['detalle'] }}</p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- ── Integridad del documento ── --}}
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
            <div class="px-5 py-3 border-b border-gray-100 bg-gray-50">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Integridad del Documento</p>
            </div>
            <div class="p-5 space-y-3">
                <div class="bg-gray-50 border border-gray-100 rounded-xl p-3">
                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1.5">Token de verificación</p>
                    <p class="text-xs text-gray-700 mono break-all leading-relaxed">{{ $token }}</p>
                </div>
                @if($diligencia->verificacion_hash)
                <div class="bg-gray-50 border border-gray-100 rounded-xl p-3">
                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1.5">Hash SHA-256 del documento</p>
                    <p class="text-xs text-gray-700 mono break-all leading-relaxed">{{ $diligencia->verificacion_hash }}</p>
                </div>
                @endif
                <div class="bg-gray-50 border border-gray-100 rounded-xl p-3">
                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1.5">Documento generado el</p>
                    <p class="text-sm font-semibold text-gray-800">
                        {{ $diligencia->verificacion_generada_en
                            ? $diligencia->verificacion_generada_en->timezone('America/Bogota')->format('d/m/Y \a \l\a\s h:i:s A')
                            : '—' }}
                    </p>
                </div>
            </div>
        </div>

    </main>

    {{-- ── Footer ── --}}
    <footer class="max-w-3xl mx-auto px-4 pb-10 pt-4 text-center">
        <p class="text-xs text-gray-400 leading-relaxed">
            Verificación provista por
            <a href="https://www.ceslegal.co" target="_blank" class="text-primary-600 hover:underline font-medium">CES Legal</a>
            · Plataforma de Gestión Disciplinaria Laboral<br>
            Ley 527/1999 (Comercio Electrónico) · Decreto 2364/2012 (Firma Electrónica)
        </p>
    </footer>

</body>
</html>
