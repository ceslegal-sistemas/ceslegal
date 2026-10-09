@extends('emails.layouts.lupe', [
    'preheader' => "El proceso {$proceso->codigo} se cerró sin sanción",
    'badge' => 'Proceso cerrado',
    'titulo' => 'Cerrado sin sanción',
    'subtitulo' => "Proceso disciplinario {$proceso->codigo}. La constancia oficial va adjunta a este correo.",
    'empresa' => $empresa,
])

@section('contenido')
    <p style="margin:0 0 4px;">Estimado(a) <strong>{{ $trabajador->nombre_completo }}</strong>,</p>
    <p style="margin:0;color:#4b5563;">
        Le informamos que el proceso disciplinario <strong>{{ $proceso->codigo }}</strong> fue cerrado. Tras analizar los hechos y
        sus descargos, la empresa decidió <strong>no aplicarle ninguna sanción</strong>.
    </p>

    @include('emails.partials.aviso', [
        'titulo' => 'Resultado',
        'tono' => 'verde',
        'texto' => 'Proceso cerrado <strong>sin sanción</strong>. Su derecho de defensa fue respetado.',
    ])

    @include('emails.partials.datos', ['filas' => [
        'Empresa' => $empresa->razon_social,
        'Código del proceso' => $proceso->codigo,
        'Su cargo' => $trabajador->cargo,
    ]])

    <p style="margin:18px 0 6px;font-weight:700;color:#1f2937;">En el documento adjunto encontrará</p>
    <ul style="margin:0;padding-left:20px;color:#4b5563;font-size:14px;line-height:1.8;">
        <li>Los hechos que se analizaron</li>
        <li>El análisis de sus descargos</li>
        <li>La decisión de no aplicar sanción y sus razones</li>
        <li>La constancia de que se respetó su derecho de defensa</li>
    </ul>

    @include('emails.partials.aviso', [
        'titulo' => 'Contacto',
        'tono' => 'azul',
        'texto' => 'Si tiene preguntas, comuníquese con el <strong>Área de Recursos Humanos</strong> de ' . e($empresa->razon_social) . '.',
    ])

    <p style="margin:18px 0 0;font-size:11px;line-height:1.6;color:#9ca3af;">Correo automático. Por favor no responda; use los canales oficiales de la empresa.</p>

    @if (isset($trackingToken))
        <img src="{{ route('email.tracking.pixel', ['token' => $trackingToken]) }}" width="1" height="1"
             style="display:block !important; width:1px !important; height:1px !important; border:0 !important; margin:0 !important; padding:0 !important;" alt="" />
    @endif
@endsection
