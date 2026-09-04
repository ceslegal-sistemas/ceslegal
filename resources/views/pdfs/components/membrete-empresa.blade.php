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
{{-- Tercera ronda: las dos primeras posicionaban el logo y el pie con
     coordenadas dentro del área de contenido (top/bottom positivos), pero
     dompdf calcula `position: fixed` respecto al cuadro de contenido, no al
     borde físico de la página - por eso chocaban con el texto cuando este
     llegaba hasta arriba o abajo (visto en PDFs reales: el logo tapaba el
     saludo, el pie se montaba sobre el último párrafo). Cada plantilla que
     incluye este partial reservó una franja extra de 1.4cm arriba y 1.2cm
     abajo en su `@page { margin: ... }` (ver comentario ahí) - acá el logo
     y el pie se ubican con offset NEGATIVO para caer exactamente dentro de
     esa franja reservada, nunca dentro del área de texto. --}}
<div style="position: fixed; top: -1.2cm; left: 1.2cm; z-index: 10;">
    <img src="{{ $logoBase64 }}" style="height: 0.9cm; width: auto;">
</div>

<div style="position: fixed; top: 40%; left: 32.5%; width: 35%; opacity: 0.05; z-index: 1;">
    <img src="{{ $logoBase64 }}" style="width: 100%; height: auto;">
</div>

@if($tieneContacto)
<div class="membrete-pie" style="position: fixed; bottom: -0.85cm; left: 0; width: 100%; text-align: center; font-size: 7.5pt; color: #555555;">
    {{ collect([$empresa->direccion, $empresa->telefono, $empresa->email_contacto])->filter()->implode(' · ') }}
</div>
@endif
@endif
