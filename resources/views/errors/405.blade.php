{{--
    MethodNotAllowedHttpException (405) - bug real reportado por el usuario:
    GET /empresa/logout desde Safari en iPhone (navegación "atrás"/pestaña
    vieja en vez del botón real de logout, que envía un POST). El cliente no
    necesita distinguir esto de un 404 - mismo mensaje que 404.blade.php.
--}}
@include('errors._shell', [
    'codigo'       => '405',
    'tituloPagina' => 'Página no disponible - LUPE Legal',
    'badgeClase'   => 'rit-badge-none',
    'badgeIconUrl' => asset('lordicons/wired-outline-1140-warning-triangle-hover-enlarge.json'),
    'badgeTexto'   => 'Página no encontrada',
    'titulo'       => 'Esta página no está disponible',
    'mensaje'      => 'Puede que el enlace esté desactualizado o que la página se haya movido. Vuelva al inicio para seguir navegando con normalidad.',
])
