@php
    // El correo se envía desde la cuenta del propio cliente: la identidad es la razón social
    // de la empresa, NUNCA "LUPE Legal" (por eso el layout va en modo $sinMarcaLupe).
    $empresa   = $correo->empresa ?? $correo->trabajador?->empresa ?? $correo->proceso?->empresa;
    $remitente = $empresa?->razon_social
        ?? $empresa?->nombre_completo
        ?? $correo->enviador?->name
        ?? 'La empresa';
    $etiqueta = match ($correo->prioridad) {
        'urgente' => 'Urgente',
        'alta' => 'Importante',
        default => 'Comunicación oficial',
    };
@endphp
@extends('emails.layouts.lupe', [
    'sinMarcaLupe' => true,
    'preheader' => $correo->asunto,
    'badge' => $etiqueta,
    'titulo' => $correo->asunto,
    'empresa' => $empresa,
    'pie' => 'Este mensaje y sus archivos adjuntos son confidenciales y van dirigidos exclusivamente a su destinatario. Si usted no es el destinatario, por favor notifíquelo al remitente y elimine este mensaje.',
])

@section('contenido')
    <p style="margin:0 0 14px;">Estimado(a) <strong>{{ $correo->destinatario_nombre }}</strong>,</p>

    <div style="font-size:15px;line-height:1.7;color:#374151;">
        {!! $correo->cuerpo !!}
    </div>

    <p style="margin:24px 0 0;">Atentamente,<br><strong>{{ $remitente }}</strong></p>

    {{-- Pixel de tracking invisible 1x1 --}}
    <img src="{{ $trackingUrl }}" width="1" height="1" border="0"
         style="display:block;width:1px;height:1px;border:0;outline:none;text-decoration:none;" alt="">
@endsection
