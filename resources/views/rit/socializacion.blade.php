<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Reglamento Interno - {{ $empresa->razon_social }}</title>

    <link rel="icon" type="image/png" href="{{ asset('images/lupe-favicon.png') }}">

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.lordicon.com/lordicon.js"></script>
    <style>
        input, textarea, select { font-size: 16px !important; }
    </style>

    @livewireStyles
</head>
<body class="bg-gray-100 antialiased">
    @livewire('socializacion-rit', ['empresa' => $empresa])
    @livewireScripts
</body>
</html>
