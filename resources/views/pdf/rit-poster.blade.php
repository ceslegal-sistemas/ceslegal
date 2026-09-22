<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 0; size: a4 portrait; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Helvetica', 'Arial', sans-serif;
            color: #ffffff;
            background: {{ $colorAcento }};
        }

        .pagina { width: 100%; padding: 50px 45px; text-align: center; }

        .logo-empresa { display: block; margin: 0 auto 20px; }
        .logo-empresa img {
            max-height: 90px;
            max-width: 260px;
            display: block;
            margin: 0 auto;
            padding: 10px 16px;
            background: #ffffff;
            border-radius: 14px;
        }

        .empresa-nombre {
            display: block;
            font-size: 20px;
            font-weight: 700;
            letter-spacing: .01em;
            margin: 0 0 34px;
        }

        .titulo {
            font-size: 40px;
            font-weight: 700;
            line-height: 1.2;
            margin: 0 0 10px;
        }

        .kicker {
            display: block;
            font-size: 12.5px;
            font-weight: 700;
            letter-spacing: .16em;
            text-transform: uppercase;
            opacity: .75;
            margin: 0 0 40px;
        }

        .tarjeta {
            background: #ffffff;
            border-radius: 22px;
            padding: 34px 30px 26px;
            margin: 0 auto 34px;
        }

        .qr-marco {
            position: relative;
            display: block;
            width: 280px;
            height: 280px;
            padding: 24px;
            margin: 0 auto 8px;
        }
        .qr-marco img { width: 280px; height: 280px; display: block; }
        .esquina {
            position: absolute;
            width: 28px;
            height: 28px;
            border-color: {{ $colorAcento }};
            border-style: solid;
            border-width: 0;
        }
        .esquina-tl { top: 0; left: 0; border-top-width: 5px; border-left-width: 5px; border-radius: 6px 0 0 0; }
        .esquina-tr { top: 0; right: 0; border-top-width: 5px; border-right-width: 5px; border-radius: 0 6px 0 0; }
        .esquina-bl { bottom: 0; left: 0; border-bottom-width: 5px; border-left-width: 5px; border-radius: 0 0 0 6px; }
        .esquina-br { bottom: 0; right: 0; border-bottom-width: 5px; border-right-width: 5px; border-radius: 0 0 6px 0; }

        .pasos { width: 100%; border-collapse: collapse; }
        .pasos td {
            width: 33.33%;
            text-align: center;
            vertical-align: top;
            padding: 0 8px;
        }
        .paso-icono {
            display: block;
            width: 46px;
            height: 46px;
            border-radius: 50%;
            background: #f4f4f5;
            margin: 0 auto 9px;
            padding: 11px;
        }
        .paso-icono img { width: 24px; height: 24px; display: block; margin: 0 auto; }
        .paso-texto { font-size: 12px; color: #3f3f46; line-height: 1.4; margin: 0; font-weight: 600; }

        .nota {
            font-size: 12px;
            opacity: .8;
            max-width: 420px;
            margin: 0 auto;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="pagina">
        @if($logoBase64)
            <div class="logo-empresa"><img src="{{ $logoBase64 }}" alt="{{ $empresa->razon_social }}"></div>
        @else
            <span class="empresa-nombre">{{ $empresa->razon_social }}</span>
        @endif

        <h1 class="titulo">Escanea y conoce<br>tus derechos y deberes</h1>
        <span class="kicker">Reglamento Interno de Trabajo</span>

        <div class="tarjeta">
            <div class="qr-marco">
                <div class="esquina esquina-tl"></div>
                <div class="esquina esquina-tr"></div>
                <div class="esquina esquina-bl"></div>
                <div class="esquina esquina-br"></div>
                <img src="data:image/svg+xml;base64,{{ $qrBase64 }}" alt="Código QR del Reglamento Interno">
            </div>

            <table class="pasos">
                <tr>
                    <td>
                        <div class="paso-icono"><img src="{{ $iconoCamara }}" alt=""></div>
                        <p class="paso-texto">Abre la cámara</p>
                    </td>
                    <td>
                        <div class="paso-icono"><img src="{{ $iconoScan }}" alt=""></div>
                        <p class="paso-texto">Apunta al código</p>
                    </td>
                    <td>
                        <div class="paso-icono"><img src="{{ $iconoCheck }}" alt=""></div>
                        <p class="paso-texto">Lee y acepta</p>
                    </td>
                </tr>
            </table>
        </div>

        <p class="nota">Este código siempre lleva a la versión vigente del Reglamento, sin importar cuántas veces se actualice.</p>
    </div>
</body>
</html>
