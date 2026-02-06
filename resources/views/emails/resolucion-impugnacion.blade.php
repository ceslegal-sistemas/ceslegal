<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resolución de Impugnación</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background-color: #7c3aed;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }

        .content {
            background-color: #f9fafb;
            padding: 30px;
            border: 1px solid #e5e7eb;
        }

        .info-box {
            background-color: white;
            padding: 15px;
            margin: 15px 0;
            border-left: 4px solid #7c3aed;
            border-radius: 4px;
        }

        .decision-box {
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            text-align: center;
        }

        .decision-confirma {
            background-color: #fee2e2;
            border: 2px solid #dc2626;
        }

        .decision-revoca {
            background-color: #d1fae5;
            border: 2px solid #059669;
        }

        .decision-modifica {
            background-color: #fef3c7;
            border: 2px solid #f59e0b;
        }

        .important {
            background-color: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }

        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 12px;
        }

        h2 {
            color: #1f2937;
            margin-top: 0;
        }

        .highlight {
            font-weight: bold;
            color: #7c3aed;
        }

        .decision-title {
            font-size: 1.25rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1 style="margin: 0;">Resolución de Impugnación</h1>
    </div>

    <div class="content">
        <p>Estimado(a) <strong>{{ $trabajador->nombre_completo }}</strong>,</p>

        <p>Por medio del presente, le comunicamos la decisión tomada respecto a la impugnación presentada
            en el proceso disciplinario <strong>{{ $proceso->codigo }}</strong>.</p>

        <div class="info-box">
            <h2>Información del Proceso</h2>
            <p><strong>Empresa:</strong> {{ $empresa->razon_social }}</p>
            <p><strong>Código del Proceso:</strong> {{ $proceso->codigo }}</p>
            <p><strong>Su cargo:</strong> {{ $trabajador->cargo }}</p>
            @if($impugnacion->fecha_impugnacion)
                <p><strong>Fecha de impugnación:</strong>
                    {{ \Carbon\Carbon::parse($impugnacion->fecha_impugnacion)->locale('es')->isoFormat('D [de] MMMM [de] YYYY') }}
                </p>
            @endif
        </div>

        {{-- Decisión tomada --}}
        @php
            $decisionClass = match($decision) {
                'confirma_sancion' => 'decision-confirma',
                'revoca_sancion' => 'decision-revoca',
                'modifica_sancion' => 'decision-modifica',
                default => 'decision-confirma'
            };

            $decisionTexto = match($decision) {
                'confirma_sancion' => 'SANCIÓN CONFIRMADA',
                'revoca_sancion' => 'SANCIÓN REVOCADA',
                'modifica_sancion' => 'SANCIÓN MODIFICADA',
                default => 'DECISIÓN EMITIDA'
            };
        @endphp

        <div class="decision-box {{ $decisionClass }}">
            <p class="decision-title">{{ $decisionTexto }}</p>
            @if($decision === 'confirma_sancion')
                <p>Después de analizar cuidadosamente su impugnación, se ha decidido <strong>CONFIRMAR</strong> la sanción originalmente impuesta.</p>
            @elseif($decision === 'revoca_sancion')
                <p>Después de analizar cuidadosamente su impugnación, se ha decidido <strong>REVOCAR</strong> la sanción originalmente impuesta.</p>
            @elseif($decision === 'modifica_sancion')
                <p>Después de analizar cuidadosamente su impugnación, se ha decidido <strong>MODIFICAR</strong> la sanción originalmente impuesta.</p>
                @if(isset($nuevaSancion))
                    <p><strong>Nueva sanción:</strong> {{ $nuevaSancion }}</p>
                @endif
            @endif
        </div>

        @if(isset($fundamento) && !empty($fundamento))
            <div class="info-box">
                <h2>Fundamento de la Decisión</h2>
                <p>{{ $fundamento }}</p>
            </div>
        @endif

        <div class="info-box">
            <h2>Documento Adjunto</h2>
            <p>Encontrará adjunto a este correo el documento oficial con la resolución completa de su impugnación.</p>
            <p><strong>Por favor, lea cuidadosamente el documento adjunto.</strong></p>
        </div>

        @if($decision === 'confirma_sancion' || $decision === 'modifica_sancion')
            <div class="important">
                <h2>Información Importante</h2>
                <p>Esta decisión es definitiva y pone fin al proceso disciplinario.</p>
                @if($decision === 'modifica_sancion' && isset($nuevaSancion))
                    <p>La nueva sanción aplicable es: <strong>{{ $nuevaSancion }}</strong></p>
                @endif
            </div>
        @else
            <div class="important">
                <h2>Información Importante</h2>
                <p>Al revocar la sanción, el proceso disciplinario queda cerrado sin efectos en su expediente laboral.</p>
            </div>
        @endif

        <p>Si tiene alguna pregunta o requiere información adicional, por favor comuníquese con el área de Recursos
            Humanos de {{ $empresa->razon_social }}.</p>

        <p>Atentamente,</p>
        <p><strong>{{ $empresa->razon_social }}</strong><br>
            Área de Recursos Humanos</p>
    </div>

    <div class="footer">
        <p>Este es un correo electrónico automático generado por el sistema de gestión de procesos disciplinarios.</p>
        <p>Por favor, no responda a este correo. Para comunicarse, utilice los canales oficiales de la empresa.</p>
    </div>

    {{-- Pixel de seguimiento de apertura (invisible) --}}
    @if(isset($trackingToken))
    <img src="{{ route('email.tracking.pixel', ['token' => $trackingToken]) }}"
         width="1" height="1"
         style="display:block !important; width:1px !important; height:1px !important; border:0 !important; margin:0 !important; padding:0 !important;"
         alt="" />
    @endif
</body>

</html>
