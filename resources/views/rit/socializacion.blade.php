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

    {{-- Tailwind con la misma paleta personalizada que usa descargos/formulario.blade.php -
         sin este config, clases como bg-primary-600 no generan ningún estilo y el botón
         "Buscar"/etc. queda invisible (texto blanco sobre fondo blanco). --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#fecdd3',
                            100: '#fecdd3',
                            200: '#fecdd3',
                            300: '#fb7185',
                            400: '#fb7185',
                            500: '#e11d48',
                            600: '#e11d48',
                            700: '#be123c',
                            800: '#be123c',
                            900: '#be123c',
                        },
                        success: {
                            50: '#f0fdf4',
                            100: '#dcfce7',
                            500: '#22c55e',
                            600: '#16a34a',
                            700: '#15803d',
                        },
                        warning: {
                            50: '#fffbeb',
                            100: '#fef3c7',
                            200: '#fde68a',
                            500: '#f59e0b',
                            600: '#d97706',
                            700: '#b45309',
                            800: '#92400e',
                        },
                        danger: {
                            50: '#fef2f2',
                            100: '#fee2e2',
                            500: '#ef4444',
                            600: '#dc2626',
                            700: '#b91c1c',
                        }
                    }
                }
            }
        }
    </script>

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
    @livewire('socializacion-rit', ['empresa' => $empresa])
    @livewireScripts
</body>
</html>
