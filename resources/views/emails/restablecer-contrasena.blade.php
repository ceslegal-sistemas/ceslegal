@extends('emails.layouts.lupe', [
    'preheader' => "Cree su contraseña para ingresar a {$nombreEmpresa}",
    'badge' => 'Bienvenido(a)',
    'titulo' => 'Configure su contraseña',
    'subtitulo' => "Su cuenta de acceso de {$nombreEmpresa} ya está lista.",
    'cta' => ['label' => 'Configurar contraseña', 'url' => $url],
])

@section('contenido')
    <p style="margin:0 0 4px;">Estimado(a) <strong>{{ $nombre }}</strong>,</p>
    <p style="margin:0;color:#4b5563;">
        Se creó su cuenta para gestionar los procesos disciplinarios de <strong>{{ $nombreEmpresa }}</strong> en la plataforma LUPE Legal.
        Para ingresar por primera vez, configure su contraseña con el botón de arriba.
    </p>

    @include('emails.partials.aviso', [
        'titulo' => 'Importante',
        'tono' => 'ambar',
        'texto' => 'Este enlace es válido por <strong>' . e($minutosExpiracion) . ' minutos</strong>. Si usted no solicitó esta cuenta, puede ignorar este correo.',
    ])
@endsection
