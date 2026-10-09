@php
    $badgeTxt = match ($decision) {
        'confirma_sancion' => 'Sanción confirmada',
        'revoca_sancion' => 'Sanción revocada',
        'modifica_sancion' => 'Sanción modificada',
        default => 'Decisión emitida',
    };
    $tonoDecision = match ($decision) {
        'revoca_sancion' => 'verde',
        'modifica_sancion' => 'ambar',
        default => 'rosa',
    };
    $frase = match ($decision) {
        'confirma_sancion' => 'Después de analizar cuidadosamente su impugnación, se decidió <strong>CONFIRMAR</strong> la sanción originalmente impuesta.',
        'revoca_sancion' => 'Después de analizar cuidadosamente su impugnación, se decidió <strong>REVOCAR</strong> la sanción originalmente impuesta.',
        'modifica_sancion' => 'Después de analizar cuidadosamente su impugnación, se decidió <strong>MODIFICAR</strong> la sanción originalmente impuesta.'
            . (isset($nuevaSancion) ? ' La nueva sanción es: <strong>' . e($nuevaSancion) . '</strong>.' : ''),
        default => 'Ya se emitió la decisión sobre su impugnación.',
    };
@endphp
@extends('emails.layouts.lupe', [
    'preheader' => "Respuesta a su impugnación del proceso {$proceso->codigo}",
    'badge' => 'Resolución de impugnación',
    'titulo' => $badgeTxt,
    'subtitulo' => "Proceso disciplinario {$proceso->codigo}. La resolución completa va adjunta a este correo.",
    'empresa' => $empresa,
])

@section('contenido')
    <p style="margin:0 0 4px;">Estimado(a) <strong>{{ $trabajador->nombre_completo }}</strong>,</p>
    <p style="margin:0;color:#4b5563;">
        Le comunicamos la decisión tomada respecto a la impugnación que presentó en el proceso
        <strong>{{ $proceso->codigo }}</strong>.
    </p>

    @include('emails.partials.aviso', ['titulo' => $badgeTxt, 'tono' => $tonoDecision, 'texto' => $frase])

    @include('emails.partials.datos', ['filas' => [
        'Empresa' => $empresa->razon_social,
        'Código del proceso' => $proceso->codigo,
        'Su cargo' => $trabajador->cargo,
        'Fecha de impugnación' => $impugnacion->fecha_impugnacion
            ? \Carbon\Carbon::parse($impugnacion->fecha_impugnacion)->locale('es')->isoFormat('D [de] MMMM [de] YYYY')
            : null,
    ]])

    @if (!empty($fundamento))
        <p style="margin:18px 0 6px;font-weight:700;color:#1f2937;">Fundamento de la decisión</p>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
            <tr>
                <td bgcolor="#f9fafb" style="background-color:#f9fafb;border-radius:14px;padding:16px 18px;font-size:14px;line-height:1.65;color:#374151;white-space:pre-wrap;">{{ $fundamento }}</td>
            </tr>
        </table>
    @endif

    @if ($decision === 'revoca_sancion')
        @include('emails.partials.aviso', [
            'titulo' => 'Información importante',
            'tono' => 'verde',
            'texto' => 'Al revocarse la sanción, el proceso disciplinario queda cerrado sin efectos en su expediente laboral.',
        ])
    @else
        @include('emails.partials.aviso', [
            'titulo' => 'Información importante',
            'tono' => 'ambar',
            'texto' => 'Esta decisión es definitiva y pone fin al proceso disciplinario. Lea con atención el documento adjunto.',
        ])
    @endif

    <p style="margin:18px 0 0;color:#4b5563;font-size:14px;">
        Si tiene preguntas o necesita información adicional, comuníquese con el área de Recursos Humanos de {{ $empresa->razon_social }}.
    </p>
    <p style="margin:22px 0 0;font-size:11px;line-height:1.6;color:#9ca3af;">
        Este es un correo automático. Por favor no responda a este mensaje; use los canales oficiales de la empresa.
    </p>

    @if (isset($trackingToken))
        <img src="{{ route('email.tracking.pixel', ['token' => $trackingToken]) }}" width="1" height="1"
             style="display:block !important; width:1px !important; height:1px !important; border:0 !important; margin:0 !important; padding:0 !important;" alt="" />
    @endif
@endsection
