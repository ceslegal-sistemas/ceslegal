{{--
    Visor del objeto jurídico redactado por IA - mismo lenguaje visual
    (.rit-viewer/.rit-text) que "Texto del reglamento vigente" en Mi
    Reglamento Interno, a pedido explícito del usuario ("mismo reskin").
    $texto ya viene en HTML (RichEditor/redacción de la IA), por eso no pasa
    por nl2br()/e() como el texto plano del RIT.
--}}
@include('filament.components.documento-viewer-styles')

<div class="rit-viewer" style="margin-top:0">
    <div class="rit-viewer-header">
        <span class="rit-viewer-label">Objeto Jurídico Redactado</span>
        @if($texto)
            <span style="font-size:.75rem;color:#64748b">{{ number_format(strlen(strip_tags($texto))) }} caracteres</span>
        @endif
    </div>
    <div class="rit-viewer-body">
        @if($texto)
            <div class="rit-text">{!! $texto !!}</div>
        @else
            <div class="rit-empty">
                <div class="rit-empty-icon">
                    <svg style="width:26px;height:26px;color:#fb7185" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                </div>
                <p class="rit-empty-title">Aún sin redactar</p>
                <p class="rit-empty-sub">Use "Regenerar Borrador" desde el listado para que la IA lo redacte.</p>
            </div>
        @endif
    </div>
</div>
