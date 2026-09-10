{{--
    Burbuja de Chatwoot (SDK oficial) embebida en el panel 'empresa' -
    identifica al usuario logueado ante Chatwoot con su user_id como
    custom_attribute, que es lo que el workflow de n8n usa para consultar
    /internal/asistente-panel/contexto (ver AsistentePanelContextoController).
--}}
@php
    $websiteToken = config('services.chatwoot.website_token');
    $baseUrl = config('services.chatwoot.base_url');
    $usuario = auth()->user();
@endphp
@if($websiteToken && $usuario)
<script>
  window.chatwootSettings = {"position":"right","type":"expanded_bubble","launcherTitle":""};
  (function(d,t) {
    var BASE_URL={{ \Illuminate\Support\Js::from($baseUrl) }};
    var g=d.createElement(t),s=d.getElementsByTagName(t)[0];
    g.src=BASE_URL+"/packs/js/sdk.js";
    g.async = true;
    s.parentNode.insertBefore(g,s);
    g.onload=function(){
      window.chatwootSDK.run({
        websiteToken: {{ \Illuminate\Support\Js::from($websiteToken) }},
        baseUrl: BASE_URL
      })
    }
  })(document,"script");

  window.addEventListener('chatwoot:ready', function () {
    window.$chatwoot.setUser({{ \Illuminate\Support\Js::from((string) $usuario->id) }}, {
      name: {{ \Illuminate\Support\Js::from($usuario->name) }},
      email: {{ \Illuminate\Support\Js::from($usuario->email) }},
      custom_attributes: { user_id: {{ \Illuminate\Support\Js::from($usuario->id) }} }
    });
  });
</script>
@endif
