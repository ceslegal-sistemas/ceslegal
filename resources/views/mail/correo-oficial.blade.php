<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $correo->asunto }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, 'Helvetica Neue', Helvetica, sans-serif; background: #f4f4f4; color: #222222; -webkit-text-size-adjust: 100%; }
        .wrapper { max-width: 620px; margin: 24px auto; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 6px; overflow: hidden; }
        .topbar { padding: 20px 32px 0; }
        .priority-badge { display: inline-block; font-size: 11px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; padding: 4px 12px; border-radius: 4px; margin-bottom: 4px; }
        .priority-urgente { background: #fee2e2; border: 1px solid #fca5a5; color: #b91c1c; }
        .priority-alta { background: #fef3c7; border: 1px solid #fcd34d; color: #92400e; }
        .body { padding: 24px 32px 8px; }
        .greeting { font-size: 15px; color: #222222; margin-bottom: 16px; }
        .content { font-size: 15px; line-height: 1.7; color: #222222; }
        .content p { margin-bottom: 12px; }
        .content ul, .content ol { padding-left: 22px; margin-bottom: 12px; }
        .content li { margin-bottom: 4px; }
        .content a { color: #1a73e8; text-decoration: underline; }
        .content strong { font-weight: 700; }
        .content h2 { font-size: 17px; font-weight: 700; margin: 16px 0 8px; }
        .content h3 { font-size: 15px; font-weight: 700; margin: 12px 0 6px; }
        .content blockquote { border-left: 3px solid #e5e7eb; padding: 8px 16px; color: #555555; background: #f8f9fa; margin: 12px 0; }
        .signature { margin-top: 24px; font-size: 15px; line-height: 1.6; color: #222222; }
        .signature strong { font-weight: 700; }
        .footer { border-top: 1px solid #e5e7eb; padding: 16px 32px; margin-top: 16px; }
        .footer p { font-size: 12px; color: #888888; line-height: 1.6; }
        @media (max-width: 640px) {
            .wrapper { margin: 0; border-radius: 0; border-left: 0; border-right: 0; }
            .body { padding: 20px; }
            .topbar { padding: 16px 20px 0; }
            .footer { padding: 16px 20px; }
        }
    </style>
</head>
<body>
    @php
        // El correo se envía desde la cuenta del propio cliente: la identidad es la
        // razón social de la empresa, NUNCA "LUPE".
        $empresa   = $correo->empresa ?? $correo->trabajador?->empresa ?? $correo->proceso?->empresa;
        $remitente = $empresa?->razon_social
            ?? $empresa?->nombre_completo
            ?? $correo->enviador?->name
            ?? 'La empresa';
    @endphp
    <div class="wrapper">

        {{-- Distintivo de prioridad (solo urgente/alta) --}}
        @if(in_array($correo->prioridad, ['urgente', 'alta']))
            <div class="topbar">
                @if($correo->prioridad === 'urgente')
                    <span class="priority-badge priority-urgente">Urgente</span>
                @else
                    <span class="priority-badge priority-alta">Importante</span>
                @endif
            </div>
        @endif

        {{-- Cuerpo --}}
        <div class="body">
            <p class="greeting">Estimado(a) <strong>{{ $correo->destinatario_nombre }}</strong>,</p>

            <div class="content">
                {!! $correo->cuerpo !!}
            </div>

            <div class="signature">
                <p>Atentamente,</p>
                <p><strong>{{ $remitente }}</strong></p>
            </div>
        </div>

        {{-- Footer --}}
        <div class="footer">
            <p>
                Este mensaje y sus archivos adjuntos son confidenciales y van dirigidos exclusivamente
                a su destinatario. Si usted no es el destinatario, por favor notifíquelo al remitente y
                elimine este mensaje.
            </p>
        </div>

    </div>

    {{-- Pixel de tracking invisible 1x1 --}}
    <img src="{{ $trackingUrl }}"
         width="1" height="1" border="0"
         style="display:block;width:1px;height:1px;border:0;outline:none;text-decoration:none;"
         alt="">
</body>
</html>
