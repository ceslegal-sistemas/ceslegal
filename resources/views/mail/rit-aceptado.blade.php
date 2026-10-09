@extends('emails.layouts.lupe', [
    'preheader' => 'Quedó registrada su aceptación del Reglamento Interno',
    'badge' => 'Reglamento Interno',
    'titulo' => 'Aceptación registrada',
    'subtitulo' => "Confirmamos que conoce y aceptó el Reglamento Interno de {$nombreEmpresa}.",
    'empresa' => $empresa,
])

@section('contenido')
    <p style="margin:0 0 6px;">Hola, <strong>{{ $nombreTrabajador }}</strong>.</p>
    <p style="margin:0;color:#4b5563;">
        Este correo confirma que quedó registrado(a) como trabajador(a) de <strong>{{ $nombreEmpresa }}</strong> que conoce y
        aceptó el Reglamento Interno de Trabajo vigente.
    </p>

    @include('emails.partials.aviso', [
        'titulo' => 'Todo listo',
        'tono' => 'verde',
        'texto' => '✓ Su registro y aceptación se guardaron correctamente. No necesita hacer nada más.',
    ])

    <p style="margin:0;font-size:13px;color:#6b7280;">Si tiene dudas sobre el Reglamento Interno, comuníquese directamente con su empresa.</p>
@endsection
