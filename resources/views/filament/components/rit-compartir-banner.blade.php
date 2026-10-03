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
  <div style="position:relative;z-index:2" x-data="{ copiado: false, url: {{ \Illuminate\Support\Js::from($empresa->urlSocializacionRit()) }} }">
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
      <div style="margin-top:.75rem;display:flex;gap:.5rem;flex-wrap:wrap;">
        <input type="text" readonly x-bind:value="url" onclick="this.select()" class="rit-link-input">
        <button type="button" class="rit-btn rit-btn-secondary" x-on:click="navigator.clipboard.writeText(url); copiado = true; setTimeout(() => copiado = false, 2000)">
          <span x-text="copiado ? 'Copiado' : 'Copiar'"></span>
        </button>
        <a class="rit-btn rit-btn-secondary" x-bind:href="'https://wa.me/?text=' + encodeURIComponent('Conoce el Reglamento Interno de Trabajo: ' + url)" target="_blank" rel="noopener">WhatsApp</a>
        <a class="rit-btn rit-btn-secondary" x-bind:href="'mailto:?subject=' + encodeURIComponent('Reglamento Interno de Trabajo') + '&body=' + encodeURIComponent('Conoce el Reglamento Interno de Trabajo aquí: ' + url)">Correo</a>
        @if($posterUrl)
          <a class="rit-btn rit-btn-secondary" href="{{ $posterUrl }}" target="_blank" rel="noopener">Poster QR</a>
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
