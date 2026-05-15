<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación de Documento — CES Legal</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f0f4f8;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 32px 16px 48px;
            color: #1a202c;
        }

        /* ── Header ── */
        .header {
            width: 100%;
            max-width: 680px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 28px;
        }
        .header-brand {
            font-size: 18px;
            font-weight: 800;
            color: #1a202c;
            letter-spacing: -0.02em;
        }
        .header-brand span { color: #4f46e5; }
        .header-url {
            font-size: 12px;
            color: #718096;
        }

        /* ── Sello de verificación ── */
        .sello {
            width: 100%;
            max-width: 680px;
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            border: 2px solid #6ee7b7;
            border-radius: 16px;
            padding: 24px 28px;
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
        }
        .sello-icon {
            width: 56px;
            height: 56px;
            background: #10b981;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .sello-icon svg { width: 30px; height: 30px; }
        .sello-title {
            font-size: 20px;
            font-weight: 800;
            color: #065f46;
            line-height: 1.2;
            margin-bottom: 4px;
        }
        .sello-sub {
            font-size: 13px;
            color: #047857;
            line-height: 1.5;
        }

        /* ── Tarjeta principal ── */
        .card {
            width: 100%;
            max-width: 680px;
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            margin-bottom: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        }
        .card-header {
            padding: 14px 20px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #64748b;
        }
        .card-body { padding: 20px; }

        /* ── Grid de datos ── */
        .data-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        @media (max-width: 520px) {
            .data-grid { grid-template-columns: 1fr; }
        }
        .data-item {}
        .data-label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #94a3b8;
            margin-bottom: 3px;
        }
        .data-value {
            font-size: 14px;
            font-weight: 600;
            color: #1a202c;
            line-height: 1.4;
        }
        .data-value.mono {
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }

        /* ── Badge proceso ── */
        .badge-proceso {
            display: inline-block;
            padding: 3px 10px;
            background: #ede9fe;
            color: #4f46e5;
            border-radius: 100px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        /* ── Lista de autenticaciones ── */
        .auth-list { display: flex; flex-direction: column; gap: 10px; }
        .auth-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 14px;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 10px;
        }
        .auth-icon { font-size: 18px; flex-shrink: 0; margin-top: 1px; }
        .auth-tipo {
            font-size: 13px;
            font-weight: 700;
            color: #14532d;
            margin-bottom: 2px;
        }
        .auth-detalle {
            font-size: 12px;
            color: #166534;
        }
        .auth-check {
            margin-left: auto;
            flex-shrink: 0;
            width: 22px;
            height: 22px;
            background: #10b981;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .auth-check svg { width: 13px; height: 13px; }

        /* ── Hash / token ── */
        .hash-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 14px 16px;
        }
        .hash-label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #94a3b8;
            margin-bottom: 6px;
        }
        .hash-value {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            color: #475569;
            word-break: break-all;
            line-height: 1.6;
        }

        /* ── Footer ── */
        .footer {
            width: 100%;
            max-width: 680px;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
            margin-top: 8px;
            line-height: 1.7;
        }
        .footer a { color: #6366f1; text-decoration: none; }

        /* ── Aviso legal ── */
        .aviso-legal {
            width: 100%;
            max-width: 680px;
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 12px;
            padding: 14px 18px;
            font-size: 12px;
            color: #92400e;
            line-height: 1.6;
            margin-bottom: 16px;
        }
        .aviso-legal strong { color: #78350f; }
    </style>
</head>
<body>

    <!-- Header -->
    <div class="header">
        <div class="header-brand">CES<span>Legal</span></div>
        <div class="header-url">www.ceslegal.co</div>
    </div>

    <!-- Sello verde de verificación -->
    <div class="sello">
        <div class="sello-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
        </div>
        <div>
            <div class="sello-title">Documento Verificado</div>
            <div class="sello-sub">
                Este documento fue generado por la plataforma CES Legal y su autenticidad ha sido confirmada.<br>
                La identidad del participante fue validada mediante OTP y verificación facial biométrica.
            </div>
        </div>
    </div>

    <!-- Aviso legal -->
    <div class="aviso-legal">
        <strong>Nota:</strong> CES Legal actúa exclusivamente como proveedor tecnológico del servicio de gestión disciplinaria.
        La decisión disciplinaria es responsabilidad exclusiva de <strong>{{ $empresa->razon_social ?? 'el empleador' }}</strong>.
        Verificación válida conforme a la <strong>Ley 527 de 1999</strong> y el <strong>Decreto 2364 de 2012</strong>.
    </div>

    <!-- Datos del proceso -->
    <div class="card">
        <div class="card-header">Proceso Disciplinario</div>
        <div class="card-body">
            <div class="data-grid">
                <div class="data-item">
                    <div class="data-label">N.° de proceso</div>
                    <div class="data-value">
                        <span class="badge-proceso">{{ $proceso->codigo ?? '—' }}</span>
                    </div>
                </div>
                <div class="data-item">
                    <div class="data-label">Empresa</div>
                    <div class="data-value">{{ $empresa->razon_social ?? '—' }}</div>
                </div>
                <div class="data-item">
                    <div class="data-label">NIT</div>
                    <div class="data-value mono">{{ $empresa->nit ?? '—' }}</div>
                </div>
                <div class="data-item">
                    <div class="data-label">Fecha de la diligencia</div>
                    <div class="data-value">
                        {{ $diligencia->fecha_diligencia
                            ? $diligencia->fecha_diligencia->timezone('America/Bogota')->format('d/m/Y')
                            : '—' }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Datos del trabajador -->
    <div class="card">
        <div class="card-header">Participante</div>
        <div class="card-body">
            <div class="data-grid">
                <div class="data-item">
                    <div class="data-label">Nombre completo</div>
                    <div class="data-value">{{ $trabajador->nombre_completo ?? '—' }}</div>
                </div>
                <div class="data-item">
                    <div class="data-label">Documento</div>
                    <div class="data-value mono">
                        {{ $trabajador->tipo_documento ?? 'C.C.' }} {{ $trabajador->numero_documento ?? '—' }}
                    </div>
                </div>
                <div class="data-item">
                    <div class="data-label">Cargo</div>
                    <div class="data-value">{{ $trabajador->cargo ?? '—' }}</div>
                </div>
                <div class="data-item">
                    <div class="data-label">IP de acceso</div>
                    <div class="data-value mono">{{ $diligencia->ip_acceso ?? '—' }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cadena de autenticación -->
    @if(!empty($autenticaciones))
    <div class="card">
        <div class="card-header">Cadena de Autenticación Digital</div>
        <div class="card-body">
            <div class="auth-list">
                @foreach($autenticaciones as $auth)
                <div class="auth-item">
                    <div class="auth-icon">{{ $auth['icono'] }}</div>
                    <div style="flex:1;">
                        <div class="auth-tipo">{{ $auth['tipo'] }}</div>
                        <div class="auth-detalle">{{ $auth['detalle'] }}</div>
                    </div>
                    <div class="auth-check">
                        <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <!-- Hash de integridad -->
    <div class="card">
        <div class="card-header">Integridad del Documento</div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:12px;">
            <div class="hash-box">
                <div class="hash-label">Token de verificación</div>
                <div class="hash-value">{{ $token }}</div>
            </div>
            @if($diligencia->verificacion_hash)
            <div class="hash-box">
                <div class="hash-label">Hash SHA-256 del documento</div>
                <div class="hash-value">{{ $diligencia->verificacion_hash }}</div>
            </div>
            @endif
            <div class="hash-box">
                <div class="hash-label">Documento generado el</div>
                <div class="hash-value" style="font-family:inherit;font-size:13px;">
                    {{ $diligencia->verificacion_generada_en
                        ? $diligencia->verificacion_generada_en->timezone('America/Bogota')->format('d/m/Y \a \l\a\s h:i:s A')
                        : '—' }}
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        Verificación provista por <a href="https://www.ceslegal.co" target="_blank">CES Legal</a> —
        Plataforma de Gestión Disciplinaria Laboral<br>
        Conforme a Ley 527/1999 (Comercio Electrónico) · Decreto 2364/2012 (Firma Electrónica)
    </div>

</body>
</html>
