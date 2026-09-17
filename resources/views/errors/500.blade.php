@include('errors._shell', [
    'codigo'       => '500',
    'tituloPagina' => 'Algo salió mal - LUPE Legal',
    'badgeClase'   => 'rit-badge-danger',
    'badgeIconUrl' => asset('lordicons/wired-outline-1140-warning-triangle-hover-enlarge.json'),
    'badgeTexto'   => 'Error inesperado',
    'titulo'       => 'Algo salió mal',
    'mensaje'      => 'Ocurrió un error inesperado. Ya quedó registrado para revisión de nuestro equipo. Intente de nuevo en unos minutos.',
    'mostrarContacto' => true,
])
