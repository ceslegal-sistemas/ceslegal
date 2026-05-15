<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documento no encontrado · CES Legal</title>
    <link rel="icon" href="/images/ces-legal-logo.png" type="image/png">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                        },
                        danger: {
                            50:  '#fef2f2',
                            100: '#fee2e2',
                            200: '#fecaca',
                            500: '#ef4444',
                            600: '#dc2626',
                            700: '#b91c1c',
                        },
                    }
                }
            }
        }
    </script>

    <script src="https://cdn.lordicon.com/lordicon.js"></script>

    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen antialiased flex flex-col">

    {{-- ── Header ── --}}
    <header class="bg-white border-b border-gray-200">
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

    <main class="flex-1 flex items-center justify-center px-4 py-12">
        <div class="w-full max-w-md">
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">

                {{-- Franja de color superior --}}
                <div class="h-1 bg-danger-500"></div>

                <div class="p-8 sm:p-10 text-center">

                    {{-- Ícono de error --}}
                    <div class="w-16 h-16 bg-danger-50 border border-danger-100 rounded-full flex items-center justify-center mx-auto mb-5">
                        <lord-icon
                            src="https://cdn.lordicon.com/tdrtiskw.json"
                            trigger="loop"
                            delay="2000"
                            stroke="bold"
                            colors="primary:#b91c1c,secondary:#dc2626,tertiary:#fecaca"
                            style="width:36px;height:36px;">
                        </lord-icon>
                    </div>

                    <h1 class="text-xl font-bold text-gray-900 mb-2">Documento no encontrado</h1>

                    <p class="text-sm text-gray-500 leading-relaxed mb-1">
                        El código de verificación no corresponde a ningún documento
                        registrado en la plataforma CES Legal.
                    </p>
                    <p class="text-sm text-gray-400 leading-relaxed">
                        El documento puede haber sido revocado o el enlace puede estar incorrecto.
                    </p>

                    <div class="mt-7 pt-5 border-t border-gray-100">
                        <a href="https://www.ceslegal.co" target="_blank" rel="noopener"
                           class="inline-flex items-center gap-2 text-sm text-primary-600 hover:text-primary-700 font-medium hover:underline transition-colors">
                            <img src="/images/ces-legal-logo.png" alt="" class="h-4 w-auto opacity-60">
                            www.ceslegal.co
                        </a>
                    </div>

                </div>
            </div>

            <p class="text-center text-xs text-gray-400 mt-4">
                Plataforma de Gestión Disciplinaria Laboral ·
                <a href="https://www.ceslegal.co" target="_blank" rel="noopener"
                   class="hover:underline">CES Legal</a>
            </p>
        </div>
    </main>

</body>
</html>
