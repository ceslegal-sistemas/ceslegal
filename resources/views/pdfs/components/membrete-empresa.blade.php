@php
    $logoBase64 = null;
    if ($empresa->logo_path) {
        $rutaAbsoluta = \Illuminate\Support\Facades\Storage::disk('local')->path($empresa->logo_path);
        if (is_file($rutaAbsoluta)) {
            $mime = mime_content_type($rutaAbsoluta) ?: 'image/png';
            $logoBase64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($rutaAbsoluta));
        }
    }

    // logo_color_acento ya no se usa visualmente aquí (la franja lateral
    // que lo consumía se retiró tras verificación visual real - ver nota
    // abajo) - se sigue calculando y guardando por si se reintroduce un
    // uso más liviano del color más adelante, decisión del spec original.
    $tieneContacto = $empresa->direccion || $empresa->telefono || $empresa->email_contacto;
@endphp
@if($logoBase64)
{{-- Cuarta ronda: las tres anteriores resolvieron el choque con el texto
     (reservando una franja de margen y usando offset negativo - ver
     comentario del `@page` en cada plantilla), pero el resultado se veía
     como un ícono huérfano flotando solo, sin nada que lo enmarque como un
     membrete real (reportado por el usuario: "muy decepcionante"). Ahora el
     logo y el pie van dentro de una franja de ancho completo con una línea
     divisoria (borde inferior arriba, borde superior abajo) - el mismo
     recurso visual que usa cualquier papel membretado real para separar el
     encabezado/pie del cuerpo del documento, en vez de un elemento suelto
     sin contexto. --}}
<div style="position: fixed; top: -1.3cm; left: 0; width: 100%; height: 1.1cm; border-bottom: 1pt solid #cbd5e1; z-index: 10;">
    <img src="{{ $logoBase64 }}" style="height: 1.05cm; width: auto;">
</div>

<div style="position: fixed; top: 40%; left: 32.5%; width: 35%; opacity: 0.05; z-index: 1;">
    <img src="{{ $logoBase64 }}" style="width: 100%; height: auto;">
</div>

@if($tieneContacto)
<div class="membrete-pie" style="position: fixed; bottom: -1cm; left: 0; width: 100%; border-top: 1pt solid #cbd5e1; padding-top: 0.15cm; text-align: center; font-size: 7.5pt; color: #555555;">
    {{ collect([$empresa->direccion, $empresa->telefono, $empresa->email_contacto])->filter()->implode(' · ') }}
</div>
@endif
@endif
