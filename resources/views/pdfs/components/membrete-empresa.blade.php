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
{{-- Quinta ronda: la cuarta agregó una línea divisoria bajo el logo y sobre
     el pie, y para no chocar con el texto había ampliado el margen de
     página (1.4cm arriba / 1.2cm abajo extra) - el usuario nunca pidió la
     línea y el margen ampliado le agregaba páginas de más al contrato real
     de 29 cláusulas. Vuelta a los márgenes ORIGINALES de cada plantilla (ver
     `@page` en cada una - ya no se tocan acá): el logo y el pie son chicos y
     usan un offset negativo calibrado para caber DENTRO del margen que ya
     existía (2cm es el más angosto de las 3 plantillas que usan este
     partial), sin línea ni franja de fondo, exactamente igual a un membrete
     de Word normal - el offset negativo saca el elemento del área de
     contenido (donde dompdf mide `position: fixed`) hacia el margen en
     blanco, sin necesitar más espacio del que ya había. --}}
<div style="position: fixed; top: -1.7cm; left: 1.2cm; z-index: 10;">
    <img src="{{ $logoBase64 }}" style="height: 0.8cm; width: auto;">
</div>

<div style="position: fixed; top: 40%; left: 32.5%; width: 35%; opacity: 0.05; z-index: 1;">
    <img src="{{ $logoBase64 }}" style="width: 100%; height: auto;">
</div>

@if($tieneContacto)
<div class="membrete-pie" style="position: fixed; bottom: -1.7cm; left: 0; width: 100%; text-align: center; font-size: 7pt; color: #555555;">
    {{ collect([$empresa->direccion, $empresa->telefono, $empresa->email_contacto])->filter()->implode(' · ') }}
</div>
@endif
@endif
