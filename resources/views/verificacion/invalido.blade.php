<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documento no encontrado · CES Legal</title>
    <link rel="icon" href="/images/ces-legal-logo.png" type="image/png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Outfit', 'system-ui', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            500: '#6366f1',
                            600: '#4f46e5',
                            950: '#1e1b4b',
                        }
                    }
                }
            }
        }
    </script>

    <style>
        body { font-family: 'Outfit', system-ui, sans-serif; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen antialiased flex flex-col">

    {{-- ── Header ── --}}
    <header class="bg-primary-950 border-b border-primary-900/60">
        <div class="max-w-2xl mx-auto px-4 h-15 py-3 flex items-center justify-between">
            <img src="/images/ces-legal-logo.png" alt="CES Legal" class="h-9 w-auto">
            <div class="flex items-center gap-1.5 bg-white/5 border border-white/10 rounded-full px-3 py-1.5">
                <span class="w-1.5 h-1.5 rounded-full bg-red-400 inline-block"></span>
                <span class="text-xs text-primary-200 font-medium">Portal de Verificación</span>
            </div>
        </div>
    </header>

    <main class="flex-1 flex items-center justify-center px-4 py-12">
        <div class="w-full max-w-md">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                {{-- Status band --}}
                <div class="bg-red-500 h-1.5 w-full"></div>

                <div class="p-10 text-center">
                    <div class="w-16 h-16 bg-red-50 border border-red-100 rounded-full flex items-center justify-center mx-auto mb-5">
                        <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="9"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5"/>
                        </svg>
                    </div>

                    <h1 class="text-xl font-bold text-slate-900 mb-2">Documento no encontrado</h1>
                    <p class="text-sm text-slate-500 leading-relaxed mb-1">
                        El código de verificación no corresponde a ningún documento
                        registrado en la plataforma CES Legal.
                    </p>
                    <p class="text-sm text-slate-400 leading-relaxed">
                        El documento puede haber sido revocado o el enlace puede estar incorrecto.
                    </p>

                    <div class="mt-7 pt-6 border-t border-slate-100">
                        <a href="https://www.ceslegal.co" target="_blank" rel="noopener"
                           class="inline-flex items-center gap-1.5 text-sm text-primary-600 hover:text-primary-700 font-medium hover:underline transition-colors">
                            <img src="/images/ces-legal-logo.png" alt="" class="h-4 w-auto opacity-70">
                            www.ceslegal.co
                        </a>
                    </div>
                </div>
            </div>

            <p class="text-center text-xs text-slate-400 mt-5">
                Plataforma de Gestión Disciplinaria Laboral ·
                <a href="https://www.ceslegal.co" target="_blank" class="hover:underline">CES Legal</a>
            </p>
        </div>
    </main>

</body>
</html>
