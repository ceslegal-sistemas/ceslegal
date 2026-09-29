<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Reglamento Interno - {{ $empresa->razon_social }}</title>

    <link rel="icon" type="image/png" href="{{ asset('images/lupe-favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/lupe-favicon.png') }}">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="LUPE Legal">
    <meta property="og:title" content="Reglamento Interno de Trabajo - {{ $empresa->razon_social }}">
    <meta property="og:description" content="Conoce y acepta el Reglamento Interno de Trabajo de tu empresa.">
    <meta property="og:image" content="{{ asset('images/lupe-og-image.png') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Reglamento Interno de Trabajo - {{ $empresa->razon_social }}">
    <meta name="twitter:description" content="Conoce y acepta el Reglamento Interno de Trabajo de tu empresa.">
    <meta name="twitter:image" content="{{ asset('images/lupe-og-image.png') }}">

    {{-- CSS compilado (2026-09-28): antes cargaba Tailwind vía
         <script src="https://cdn.tailwindcss.com"> - ese CDN NO debe usarse
         en producción (lo advierte el propio Tailwind): si el script de ese
         dominio externo no carga en el navegador del visitante (adblocker,
         VPN, red corporativa), la página entera pierde TODO el estilo - el
         HTML se renderiza bien pero cero CSS llega. Bug real reportado por
         el usuario (2026-09-28). Los colores personalizados (primary/
         success/warning/danger) ahora viven en resources/css/app.css,
         compilados con el resto del proyecto - mismos valores exactos. --}}
    @vite(['resources/css/app.css'])

    <style>
        /* Evitar zoom en inputs en iOS */
        input, textarea, select {
            font-size: 16px !important;
        }
    </style>

    @livewireStyles
    <script src="https://cdn.lordicon.com/lordicon.js"></script>
</head>
<body class="bg-gray-100 antialiased">
    @livewire('socializacion-rit', ['empresa' => $empresa, 'token' => $token])
    <script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.14/dist/face-api.js"></script>
    @livewireScripts
</body>
</html>
