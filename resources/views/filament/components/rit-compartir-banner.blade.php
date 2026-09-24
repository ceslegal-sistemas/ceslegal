{{-- Extraido de mi-reglamento-interno.blade.php - banner para compartir el
     link fijo de socializacion del RIT (copiar/WhatsApp/correo/poster).
     Reutilizado tambien en la tarjeta del Dashboard (ver
     dashboard-socializacion-rit-notice.blade.php). Recibe $empresa y
     $posterUrl (puede ser null si el RIT no esta listo todavia). --}}
<div class="rit-hero" style="padding:1.25rem 1.5rem;">
  <div class="rit-orb-b"></div><div class="rit-orb-g"></div><div class="rit-overlay"></div>
  <div style="position:relative;z-index:2" x-data="{ copiado: false, url: {{ \Illuminate\Support\Js::from($empresa->urlSocializacionRit()) }} }">
    <span class="rit-badge rit-badge-ia">Socializa el RIT</span>
    <h1 class="rit-title">Comparte el Reglamento con tus trabajadores</h1>
    <p class="rit-sub">Este link es fijo: siempre lleva a la versión vigente del Reglamento, sin importar cuántas veces lo actualices.</p>
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
  </div>
</div>
