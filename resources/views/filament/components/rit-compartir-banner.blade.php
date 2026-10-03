{{--
    Extraido de mi-reglamento-interno.blade.php - banner para compartir el
    link fijo de socializacion del RIT (copiar/WhatsApp/correo/poster).
    Reutilizado tambien en la tarjeta del Dashboard (ver
    dashboard-socializacion-rit-notice.blade.php).

    Recibe:
    - $empresa
    - $posterUrl (puede ser null si el RIT no esta listo todavia)
    - $reglamento (para saber si ya se declaro la fecha de publicacion)
    - $declararFechaUrl (opcional) - si se pasa, "declarar/cambiar fecha" es
      un link normal a Mi Reglamento Interno en vez de abrir el modal en el
      sitio (necesario en el Dashboard, que es una página distinta y no
      tiene registrada la acción declararFechaPublicacion).

    Pedido del usuario (2026-09-29): los botones de compartir quedan
    OCULTOS hasta declarar la fecha de publicación - no tiene sentido
    repartir el link antes de saber desde cuándo cuenta el plazo legal de
    objeción para los trabajadores.
--}}
@php
    $fechaPublicacion = $reglamento?->fecha_publicacion_socializacion ?? null;
    $declararFechaUrl = $declararFechaUrl ?? null;
    // Pedido de Andrés Sarmiento (reunión 2026-10-03): después de los 15
    // días hábiles de objeción, el panel admin debe dejar explícito que el
    // MISMO link ya no es "publicación" sino "socialización" del RIT - el
    // lado del trabajador ya distingue esto (ver $fase en SocializacionRit),
    // esto es el aviso correspondiente del lado empresa.
    $faseActual = $reglamento?->faseSocializacionActual();
@endphp
<div class="rit-hero" style="padding:1.25rem 1.5rem;">
  <div class="rit-orb-b"></div><div class="rit-orb-g"></div><div class="rit-overlay"></div>
  @php
    // Mensaje de WhatsApp/Correo distinto según la fase (pedido del usuario,
    // 2026-10-03): en Fase 2 ya no basta con "conocer" el Reglamento, hay
    // que completar la socialización (video + quiz + confirmación).
    $mensajeCompartir = $faseActual === 'socializacion'
        ? 'Completa la socialización del Reglamento Interno de Trabajo'
        : 'Conoce el Reglamento Interno de Trabajo';
  @endphp
  <div style="position:relative;z-index:2" x-data="{
      copiado: false,
      url: {{ \Illuminate\Support\Js::from($empresa->urlSocializacionRit()) }},
      mensaje: {{ \Illuminate\Support\Js::from($mensajeCompartir) }},
      tituloCompartir: {{ \Illuminate\Support\Js::from('Reglamento Interno de Trabajo - ' . $empresa->razon_social) }},
      // Web Share API (pedido del usuario, 2026-10-03): en celular abre la
      // hoja nativa de compartir (WhatsApp, Mensajes, Correo, lo que tenga
      // instalado); en computador abre el panel nativo de Windows/macOS.
      // Respaldo a los botones de WhatsApp/Correo de siempre SOLO si el
      // navegador no lo soporta (ej. Firefox de escritorio).
      soportaCompartir: typeof navigator.share === 'function',
      compartir() {
          navigator.share({ title: this.tituloCompartir, text: this.mensaje, url: this.url }).catch(() => {});
      }
    }">
    @if($faseActual === 'socializacion')
      <span class="rit-badge rit-badge-sub">Fase 2 · Socialización en curso</span>
    @elseif($fechaPublicacion)
      <span class="rit-badge rit-badge-ia">Fase 1 · Publicación en curso</span>
    @else
      <span class="rit-badge rit-badge-ia">Socializa el RIT</span>
    @endif
    <h1 class="rit-title">Comparte el Reglamento con tus trabajadores</h1>

    @if($fechaPublicacion)
      @if($faseActual === 'socializacion')
        <p class="rit-sub" style="color:#166534;font-weight:600">
          Ya pasaron los 15 días hábiles de objeción. Con este mismo link, tus trabajadores ahora deben completar la socialización
          completa (video, quiz y confirmación) - ya no basta con el aviso inicial de publicación.
        </p>
      @else
        <p class="rit-sub">Este link es fijo: siempre lleva a la versión vigente del Reglamento, sin importar cuántas veces lo actualices.</p>
      @endif
      <p style="margin:.35rem 0 0;font-size:.75rem;color:#57534e">
        {{ $fechaPublicacion->isFuture() ? 'Se publicará el' : 'Publicado el' }} {{ $fechaPublicacion->format('d/m/Y') }}
        @if($reglamento?->fechaLimiteObjecion())
          · {{ $faseActual === 'socializacion' ? 'el plazo de objeción venció el' : 'sus trabajadores pueden objetarlo hasta el' }} {{ $reglamento->fechaLimiteObjecion()->format('d/m/Y') }} (15 días hábiles)
        @endif
        ·
        @if($declararFechaUrl)
          <a href="{{ $declararFechaUrl }}" style="color:#be123c;font-weight:600;text-decoration:none">Cambiar fecha</a>
        @else
          <button type="button" wire:click="mountAction('declararFechaPublicacion')" style="background:none;border:none;padding:0;color:#be123c;font-weight:600;cursor:pointer;font-size:.75rem">Cambiar fecha</button>
        @endif
      </p>
      {{-- Link en su propia fila a todo el ancho (pedido del usuario,
           2026-10-03: "que el campo del link sea completo"), botones de
           acción debajo en vez de apretados junto al input. --}}
      <div style="margin-top:.75rem">
        <input type="text" readonly x-bind:value="url" onclick="this.select()" class="rit-link-input" style="width:100%">
      </div>
      <div style="margin-top:.5rem;display:flex;gap:.5rem;flex-wrap:wrap;">
        <button type="button" class="rit-btn rit-btn-secondary" x-on:click="navigator.clipboard.writeText(url); copiado = true; setTimeout(() => copiado = false, 2000)">
          <span x-text="copiado ? 'Copiado' : 'Copiar'"></span>
        </button>
        {{-- Compartir nativo (pedido del usuario, 2026-10-03): un solo botón
             en vez de WhatsApp/Correo separados - en celular abre la hoja
             nativa del sistema (WhatsApp, Mensajes, Correo, lo que tenga
             instalado); en Windows/Mac abre el panel nativo de compartir.
             Respaldo a los botones de siempre SOLO si el navegador no
             soporta navigator.share (ej. Firefox de escritorio). --}}
        <button type="button" x-show="soportaCompartir" x-on:click="compartir()" class="rit-btn rit-btn-secondary">
          <svg style="width:15px;height:15px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 100 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186l9.566-5.314m-9.566 7.5l9.566 5.314m0 0a2.25 2.25 0 103.935 2.186 2.25 2.25 0 00-3.935-2.186zm0-12.814a2.25 2.25 0 103.933-2.185 2.25 2.25 0 00-3.933 2.185z"/></svg>
          Compartir
        </button>
        <a class="rit-btn rit-btn-secondary" x-show="!soportaCompartir" x-bind:href="'https://wa.me/?text=' + encodeURIComponent(mensaje + ': ' + url)" target="_blank" rel="noopener">WhatsApp</a>
        <a class="rit-btn rit-btn-secondary" x-show="!soportaCompartir" x-bind:href="'mailto:?subject=' + encodeURIComponent('Reglamento Interno de Trabajo') + '&body=' + encodeURIComponent(mensaje + ' aquí: ' + url)">Correo</a>
        @if($posterUrl)
          {{-- Antes decía "Poster QR" (pedido del usuario, 2026-10-03: "puede
               confundir, el cliente no puede ni saber para qué sirve") - el
               nuevo texto explica la acción (imprimir) y el contenido (QR)
               en vez de un nombre técnico sin contexto. --}}
          <a class="rit-btn rit-btn-secondary" href="{{ $posterUrl }}" target="_blank" rel="noopener" title="Descarga un cartel listo para imprimir y pegar en cartelera, con un código QR que lleva directo a este link">
            <svg style="width:15px;height:15px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z"/></svg>
            Imprimir cartel con QR
          </a>
        @endif
      </div>
    @else
      <p class="rit-sub">Antes de compartir el link, indíquenos desde cuándo quedará disponible el Reglamento para sus trabajadores (por ejemplo, el día que va a compartir este link o a pegar las carteleras) - con eso calculamos automáticamente el plazo de 15 días hábiles que tendrán para objetarlo, sin que usted tenga que llevar la cuenta.</p>
      <div style="margin-top:.75rem">
        @if($declararFechaUrl)
          <a href="{{ $declararFechaUrl }}" class="rit-btn rit-btn-primary">Declarar fecha de publicación</a>
        @else
          <button type="button" wire:click="mountAction('declararFechaPublicacion')" class="rit-btn rit-btn-primary">Declarar fecha de publicación</button>
        @endif
      </div>
    @endif
  </div>
</div>
