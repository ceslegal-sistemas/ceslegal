@extends('emails.layouts.lupe', [
    'preheader' => 'Quedó registrada la confirmación de la publicación del Reglamento',
    'badge' => 'Reglamento Interno',
    'titulo' => 'Publicación confirmada',
    'subtitulo' => "Quedó registrado(a) como informado(a) del Reglamento Interno de {$nombreEmpresa}.",
    'empresa' => $empresa,
])

@section('contenido')
    <p style="margin:0 0 6px;">Hola, <strong>{{ $nombreTrabajador }}</strong>.</p>
    <p style="margin:0;color:#4b5563;">
        Este correo confirma que quedó registrado(a) como informado(a) de la publicación del Reglamento Interno de Trabajo de
        <strong>{{ $nombreEmpresa }}</strong>.
    </p>

    @include('emails.partials.aviso', [
        'titulo' => 'Qué sigue',
        'tono' => 'azul',
        'texto' => 'Si tiene alguna objeción sobre el Reglamento, comuníquela directamente a su empresa dentro de los términos que la ley establece. Próximamente recibirá un nuevo correo para completar la socialización del Reglamento.',
    ])
@endsection
