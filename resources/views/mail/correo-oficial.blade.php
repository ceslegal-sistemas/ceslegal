<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $correo->asunto }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; background: #f1f5f9; color: #1e293b; -webkit-text-size-adjust: 100%; }
        .wrapper { max-width: 600px; margin: 32px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,.10); }
        .header { background: linear-gradient(135deg, #1e3a5f 0%, #1e2d5a 100%); padding: 28px 32px; display: flex; align-items: center; justify-content: space-between; }
        .header-brand { font-size: 18px; font-weight: 700; color: #ffffff; letter-spacing: -.01em; }
        .header-brand span { color: #c9a84c; }
        .priority-badge { display: inline-block; font-size: 10px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; padding: 4px 10px; border-radius: 20px; }
        .priority-urgente { background: rgba(239,68,68,.2); border: 1px solid rgba(239,68,68,.4); color: #fca5a5; }
        .priority-alta { background: rgba(245,158,11,.18); border: 1px solid rgba(245,158,11,.38); color: #fcd34d; }
        .body { padding: 36px 40px; }
        .greeting { font-size: 15px; color: #475569; margin-bottom: 8px; }
        .greeting strong { color: #0f172a; }
        .divider { height: 1px; background: #e2e8f0; margin: 24px 0; }
        .content { font-size: 14.5px; line-height: 1.75; color: #334155; }
        .content p { margin-bottom: 12px; }
        .content ul, .content ol { padding-left: 20px; margin-bottom: 12px; }
        .content li { margin-bottom: 4px; }
        .content a { color: #4f46e5; text-decoration: underline; }
        .content strong { font-weight: 600; color: #0f172a; }
        .content h2 { font-size: 16px; font-weight: 700; color: #0f172a; margin: 16px 0 8px; }
        .content h3 { font-size: 14px; font-weight: 600; color: #1e293b; margin: 12px 0 6px; }
        .content blockquote { border-left: 3px solid #e2e8f0; padding: 8px 16px; color: #64748b; background: #f8fafc; border-radius: 0 4px 4px 0; margin: 12px 0; }
        .meta-row { display: flex; align-items: center; gap: 8px; font-size: 12px; color: #94a3b8; margin-top: 8px; }
        .meta-dot { width: 4px; height: 4px; border-radius: 50%; background: #cbd5e1; flex-shrink: 0; }
        .footer { background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 20px 40px; }
        .footer p { font-size: 11px; color: #94a3b8; line-height: 1.6; }
        .footer strong { color: #64748b; }
        @media (max-width: 640px) {
            .wrapper { margin: 0; border-radius: 0; }
            .body { padding: 24px 20px; }
            .footer { padding: 16px 20px; }
            .header { padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="wrapper">

        {{-- Encabezado --}}
        <div class="header">
            <div class="header-brand">CES <span>LEGAL</span></div>
            @if($correo->prioridad === 'urgente')
                <span class="priority-badge priority-urgente">Urgente</span>
            @elseif($correo->prioridad === 'alta')
                <span class="priority-badge priority-alta">Importante</span>
            @endif
        </div>

        {{-- Cuerpo --}}
        <div class="body">
            <p class="greeting">Para: <strong>{{ $correo->destinatario_nombre }}</strong></p>

            <div class="meta-row">
                <span>{{ $correo->asunto }}</span>
                @if($correo->proceso)
                    <div class="meta-dot"></div>
                    <span>Expediente {{ $correo->proceso->codigo ?? 'N/A' }}</span>
                @endif
            </div>

            <div class="divider"></div>

            <div class="content">
                {!! $correo->cuerpo !!}
            </div>

            <div class="divider"></div>

            <p style="font-size:12px;color:#94a3b8;line-height:1.6;">
                Enviado por <strong style="color:#64748b">{{ $correo->enviador?->name ?? 'CES Legal' }}</strong>
                mediante la plataforma CES Legal.
            </p>
        </div>

        {{-- Footer --}}
        <div class="footer">
            <p>
                <strong>CES LEGAL</strong> — Plataforma de gestión jurídica laboral.<br>
                Este correo es de carácter oficial. Si usted no era el destinatario, por favor notifíquelo
                y elimine este mensaje. La información contenida es confidencial.
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
