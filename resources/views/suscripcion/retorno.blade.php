<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resultado del Pago — CES Legal</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 dark:bg-gray-900 flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-8 text-center space-y-6">

            @if($aprobado)
                {{-- Éxito --}}
                <div class="flex justify-center">
                    <div class="w-16 h-16 rounded-full bg-green-100 dark:bg-green-900 flex items-center justify-center">
                        <svg class="w-8 h-8 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">¡Pago Exitoso!</h1>
                    <p class="mt-2 text-gray-600 dark:text-gray-400">{{ $mensaje }}</p>
                </div>
                @isset($tx['reference'])
                    <p class="text-xs text-gray-400 dark:text-gray-500">Referencia: {{ $tx['reference'] }}</p>
                @endisset
                <a href="/admin"
                   class="inline-flex items-center justify-center w-full px-6 py-3 rounded-xl bg-primary-600 text-white font-semibold text-sm hover:bg-primary-500 transition-colors shadow-sm">
                    Ir al panel
                </a>

            @elseif($rechazado)
                {{-- Error --}}
                <div class="flex justify-center">
                    <div class="w-16 h-16 rounded-full bg-red-100 dark:bg-red-900 flex items-center justify-center">
                        <svg class="w-8 h-8 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </div>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Pago no completado</h1>
                    <p class="mt-2 text-gray-600 dark:text-gray-400">{{ $mensaje }}</p>
                </div>
                <div class="flex flex-col gap-3">
                    <a href="{{ route('filament.admin.auth.register') }}"
                       class="inline-flex items-center justify-center w-full px-6 py-3 rounded-xl bg-primary-600 text-white font-semibold text-sm hover:bg-primary-500 transition-colors shadow-sm">
                        Volver a intentar
                    </a>
                    <a href="/admin"
                       class="inline-flex items-center justify-center w-full px-6 py-3 rounded-xl border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 font-semibold text-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        Ir al panel de todas formas
                    </a>
                </div>

            @else
                {{-- Sin datos --}}
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Sin información</h1>
                    <p class="mt-2 text-gray-600 dark:text-gray-400">No se recibió información de la transacción.</p>
                </div>
                <a href="/admin"
                   class="inline-flex items-center justify-center w-full px-6 py-3 rounded-xl bg-gray-600 text-white font-semibold text-sm hover:bg-gray-500 transition-colors">
                    Ir al panel
                </a>
            @endif

        </div>
    </div>
</body>
</html>
