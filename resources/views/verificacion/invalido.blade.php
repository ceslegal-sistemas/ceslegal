<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documento no encontrado · CES Legal</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            500: '#6366f1',
                            600: '#4f46e5',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Inter', system-ui, sans-serif; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen antialiased flex flex-col">

    <header class="bg-white border-b border-gray-200">
        <div class="max-w-3xl mx-auto px-4 h-14 flex items-center gap-2.5">
            <img src="/images/ces-legal-logo.png" alt="CES Legal" class="h-8 w-auto">
        </div>
    </header>

    <main class="flex-1 flex items-center justify-center p-6">
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-10 max-w-md w-full text-center">
            <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-5">
                <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
            </div>
            <h1 class="text-xl font-bold text-gray-900 mb-2">Documento no encontrado</h1>
            <p class="text-sm text-gray-500 leading-relaxed mb-1">
                El código de verificación no corresponde a ningún documento registrado en la plataforma CES Legal.
            </p>
            <p class="text-sm text-gray-400 leading-relaxed">
                El documento puede haber sido revocado o el enlace puede estar incorrecto.
            </p>
            <div class="mt-6 pt-6 border-t border-gray-100">
                <a href="https://www.ceslegal.co" target="_blank"
                   class="text-sm text-primary-600 hover:text-primary-700 font-medium">
                    www.ceslegal.co
                </a>
            </div>
        </div>
    </main>

</body>
</html>
