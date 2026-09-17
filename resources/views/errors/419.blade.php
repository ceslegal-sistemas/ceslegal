@include('errors._shell', [
    'codigo'       => '419',
    'tituloPagina' => 'Sesión expirada - LUPE Legal',
    'badgeClase'   => 'rit-badge-info',
    'badgeIconUrl' => asset('lordicons/wired-outline-1140-warning-triangle-hover-enlarge.json'),
    'badgeTexto'   => 'Sesión expirada',
    'titulo'       => 'Su sesión expiró por inactividad',
    'mensaje'      => 'Por seguridad, cerramos la sesión tras un tiempo sin actividad. Vuelva a iniciar sesión para continuar.',
])
