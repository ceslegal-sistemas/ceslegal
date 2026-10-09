@php
    $limiteTxt = $limite?->copy()->timezone('America/Bogota')->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY');
@endphp
@extends('emails.layouts.lupe', [
    'preheader' => 'Su enlace personal para presentar la impugnación',
    'badge' => 'Su derecho a impugnar',
    'titulo' => 'Presente su impugnación en línea',
    'subtitulo' => "Proceso {$proceso->codigo}. Es personal: no lo comparta con nadie.",
    'cta' => ['label' => 'Presentar mi impugnación', 'url' => $url],
    'empresa' => $empresa,
])

@section('contenido')
    <p style="margin:0 0 4px;">Hola, <strong>{{ $trabajador->nombre_completo }}</strong>.</p>
    <p style="margin:0;color:#4b5563;">
        Le enviamos nuevamente el enlace para que presente su impugnación contra la decisión del proceso
        <strong>{{ $proceso->codigo }}</strong>. Verificará su identidad con un código que llegará a este mismo correo,
        y luego podrá explicar sus motivos y adjuntar pruebas.
    </p>

    @include('emails.partials.datos', ['filas' => [
        'Proceso' => $proceso->codigo,
        'Plazo para impugnar' => $limiteTxt ? "Hasta el {$limiteTxt}" : null,
    ]])

    @include('emails.partials.aviso', [
        'titulo' => 'Importante',
        'tono' => 'ambar',
        'texto' => 'Pasado el plazo ya no será posible presentar la impugnación. Si tiene problemas con el enlace, comuníquese de inmediato con Recursos Humanos de ' . e($empresa->razon_social) . '.',
    ])
@endsection
