<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 0; }
        body {
            margin: 0;
            font-family: 'Helvetica', 'Arial', sans-serif;
            color: #1c1917;
            background: #ffffff;
        }
        .poster {
            box-sizing: border-box;
            width: 100%;
            height: 100vh;
            padding: 60px 50px;
            text-align: center;
        }
        .marca {
            font-size: 14px;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: #be123c;
            margin-bottom: 40px;
        }
        .titulo {
            font-size: 30px;
            font-weight: 700;
            line-height: 1.3;
            margin: 0 0 12px;
        }
        .empresa {
            font-size: 18px;
            color: #57534e;
            margin: 0 0 40px;
        }
        .qr-box {
            display: inline-block;
            padding: 24px;
            border: 3px solid #be123c;
            border-radius: 16px;
        }
        .qr-box img {
            width: 320px;
            height: 320px;
            display: block;
        }
        .instruccion {
            font-size: 16px;
            color: #292524;
            margin: 40px 0 8px;
        }
        .nota {
            font-size: 12px;
            color: #78716c;
            margin: 0;
        }
        .footer {
            margin-top: 60px;
            font-size: 11px;
            color: #a8a29e;
        }
    </style>
</head>
<body>
    <div class="poster">
        <p class="marca">LUPE Legal</p>
        <h1 class="titulo">Escanea para conocer y aceptar<br>el Reglamento Interno de Trabajo</h1>
        <p class="empresa">{{ $empresa->razon_social }}</p>

        <div class="qr-box">
            <img src="data:image/svg+xml;base64,{{ $qrBase64 }}" alt="Código QR del Reglamento Interno">
        </div>

        <p class="instruccion">Abre la cámara de tu celular y apunta al código.</p>
        <p class="nota">Este código siempre lleva a la versión vigente del Reglamento, sin importar cuántas veces se actualice.</p>

        <p class="footer">Generado por LUPE Legal · {{ now()->format('d/m/Y') }}</p>
    </div>
</body>
</html>
