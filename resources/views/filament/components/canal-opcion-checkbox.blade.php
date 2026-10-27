{{--
    Una opción del selector de canal (correo/WhatsApp) usada dentro de un
    CheckboxList con ->allowHtml() (ver ProcesoDisciplinarioResource::
    campoCanalNotificacion()). @include duplicado a propósito: mismo patrón
    ya usado en los demás Placeholder de este recurso (ej. aviso_sin_rit) -
    cada fragmento HTML que Filament inyecta por separado necesita su propio
    <style>, no se puede asumir que ya esté cargado en la página.
--}}
@include('filament.components.lupe-hero-styles')
<span class="canal-opcion" data-canal="{{ $canal }}">
    <span class="canal-opcion-ico">
        @svg($canal === 'whatsapp' ? 'heroicon-o-chat-bubble-left-right' : 'heroicon-o-envelope')
    </span>
    <span class="canal-opcion-texto">
        <span class="canal-opcion-titulo">
            {{ $titulo }}
            @if($pendiente ?? false)
                <span class="canal-opcion-pendiente">Próximamente</span>
            @endif
        </span>
        <span class="canal-opcion-detalle">{{ $detalle }}</span>
    </span>
    <span class="canal-opcion-check">Seleccionado</span>
</span>
