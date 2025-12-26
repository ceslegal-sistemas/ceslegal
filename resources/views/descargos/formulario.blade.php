<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Descargos - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    @livewireStyles
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto py-8">
        <div class="mb-8 text-center">
            <h1 class="text-3xl font-bold text-gray-900">{{ config('app.name') }}</h1>
            <p class="text-gray-600 mt-2">Sistema de Descargos Disciplinarios</p>
        </div>

        @livewire('formulario-descargos', ['diligencia' => $diligencia])
    </div>

    @livewireScripts
</body>
</html>
