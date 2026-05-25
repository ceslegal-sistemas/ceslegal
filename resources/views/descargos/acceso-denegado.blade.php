<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Restringido - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    @if($esTemprano ?? false)
        {{-- Auto-refresh cada 60s mientras el trabajador espera la hora --}}
        <meta http-equiv="refresh" content="60">
    @endif
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full mx-4">
        <div class="bg-white rounded-lg shadow-lg p-8">
            <div class="text-center">

                @if($esTemprano ?? false)
                    {{-- Ícono reloj — esperando la hora --}}
                    <svg class="mx-auto h-16 w-16 text-amber-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <h1 class="text-2xl font-bold text-gray-900 mb-2">Aún no es la hora</h1>
                    <p class="text-gray-600 mb-5">{{ $mensaje }}</p>

                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-4 text-left space-y-1">
                        <p class="text-sm text-amber-900">
                            <span class="font-semibold">Fecha:</span>
                            {{ $fechaPermitida->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY') }}
                        </p>
                        @if($horaPermitida ?? false)
                        <p class="text-sm text-amber-900">
                            <span class="font-semibold">Hora:</span>
                            {{ $horaPermitida }} (hora de Colombia)
                        </p>
                        @endif
                    </div>

                    <p class="text-xs text-gray-400">
                        Esta página se actualizará automáticamente. Cuando llegue la hora podrá acceder.
                    </p>
                @else
                    {{-- Ícono candado — día incorrecto --}}
                    <svg class="mx-auto h-16 w-16 text-yellow-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    <h1 class="text-2xl font-bold text-gray-900 mb-2">Acceso Restringido</h1>
                    <p class="text-gray-600 mb-4">{{ $mensaje }}</p>

                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4 text-left space-y-1">
                        <p class="text-sm text-blue-800">
                            <span class="font-semibold">Fecha programada:</span>
                            {{ $fechaPermitida->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY') }}
                        </p>
                        @if($horaPermitida ?? false)
                        <p class="text-sm text-blue-800">
                            <span class="font-semibold">Hora:</span>
                            {{ $horaPermitida }} (hora de Colombia)
                        </p>
                        @endif
                    </div>

                    <p class="text-sm text-gray-500">
                        Guarde este enlace y acceda nuevamente en la fecha y hora indicadas.
                    </p>
                @endif

            </div>
        </div>
    </div>
</body>
</html>
