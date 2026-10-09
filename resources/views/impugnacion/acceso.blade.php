<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Impugnar decisión - LUPE Legal</title>

    <link rel="icon" type="image/png" href="{{ asset('images/lupe-favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/lupe-favicon.png') }}">

    {{-- Vista previa al compartir: sin datos del trabajador ni del proceso. --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="LUPE Legal">
    <meta property="og:title" content="LUPE Legal - Presentar una impugnación">
    <meta property="og:description" content="Ingrese de forma segura para presentar su impugnación.">
    <meta property="og:image" content="{{ asset('images/lupe-og-image.png') }}">

    {{-- CSS compilado del proyecto (mismo que descargos y socialización del RIT). --}}
    @vite(['resources/css/app.css'])

    <style>
        input, textarea, select { font-size: 16px !important; }
    </style>

    @livewireStyles
    <script src="https://cdn.lordicon.com/lordicon.js"></script>
</head>
<body class="bg-gray-100 antialiased">
    @livewire('formulario-impugnacion', ['token' => $token])
    @livewireScripts
</body>
</html>
