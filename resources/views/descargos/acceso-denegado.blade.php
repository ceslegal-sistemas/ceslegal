<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Denegado - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full mx-4">
        <div class="bg-white rounded-lg shadow-lg p-8">
            <div class="text-center">
                <svg class="mx-auto h-16 w-16 text-yellow-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <h1 class="text-2xl font-bold text-gray-900 mb-2">Acceso Restringido</h1>
                <p class="text-gray-600 mb-4">{{ $mensaje }}</p>
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                    <p class="text-sm text-blue-800">
                        <strong>Fecha programada:</strong><br>
                        {{ $fechaPermitida->format('l, d \d\e F \d\e Y') }}
                    </p>
                </div>
                <p class="text-sm text-gray-500">
                    Por favor, acceda nuevamente el día indicado. Guarde este enlace para futuras referencias.
                </p>
            </div>
        </div>
    </div>
</body>
</html>
