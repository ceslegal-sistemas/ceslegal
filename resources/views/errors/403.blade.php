{{--
    Página de error 403 propia - Laravel no trae ninguna por defecto y
    Filament tampoco, así que cualquier 403 (falta de permiso, rol
    equivocado, etc.) caía en la página genérica y fea del framework.
    Hallazgo real del usuario: un cliente que llegaba con una sesión
    vencida a una URL vieja se quedaba sin ninguna salida.

    Refactorizada (2026-09-16) para compartir el cascarón (_shell.blade.php)
    con las demás páginas de error (404/405/419/500) - mismo comportamiento,
    ya no duplica el bloque de estilos/theme-switcher.
--}}
@include('errors._shell', [
    'codigo'       => '403',
    'tituloPagina' => 'Acceso no permitido - LUPE Legal',
    'badgeClase'   => 'rit-badge-danger',
    'badgeIconUrl' => asset('lordicons/wired-outline-1140-warning-triangle-hover-enlarge.json'),
    'badgeTexto'   => 'Acceso no permitido',
    'titulo'       => 'No tiene permiso para ver esta página',
    'mensaje'      => 'Puede que la sesión haya cambiado desde la última vez que la visitó, o que esta página no esté disponible para su usuario. Vuelva al inicio para seguir navegando con normalidad.',
])
