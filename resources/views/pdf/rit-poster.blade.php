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
            color: #1c1917;
            background: #ffffff;
        }

        .band {
            width: 100%;
            background: #be123c;
            color: #ffffff;
            text-align: center;
        }
        .band-top { padding: 16px 20px; }
        .band-top p { font-size: 11px; font-weight: 700; letter-spacing: .1em; margin: 0; color: #fecdd3; }
        .band-bottom { padding: 16px 20px; position: fixed; bottom: 0; left: 0; }
        .band-bottom p { font-size: 11px; margin: 0; color: #fecdd3; }

        .contenido { padding: 34px 55px 30px; text-align: center; }

        .logo-empresa { display: block; margin: 0 auto 22px; }
        .logo-empresa img { max-height: 64px; max-width: 220px; display: block; margin: 0 auto; }

        .kicker {
            display: block;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: #be123c;
            margin: 0 0 16px;
        }

        .titulo {
            font-size: 34px;
            font-weight: 700;
            line-height: 1.22;
            margin: 0 0 14px;
            color: #1c1917;
        }

        .empresa {
            display: block;
            font-size: 15px;
            font-weight: 600;
            color: #78716c;
            margin: 0 0 26px;
        }

        .qr-marco {
            position: relative;
            display: block;
            width: 280px;
            height: 280px;
            padding: 24px;
            margin: 0 auto 30px;
        }
        .qr-marco img { width: 280px; height: 280px; display: block; }
        .esquina {
            position: absolute;
            width: 28px;
            height: 28px;
            border-color: #be123c;
            border-style: solid;
            border-width: 0;
        }
        .esquina-tl { top: 0; left: 0; border-top-width: 5px; border-left-width: 5px; border-radius: 6px 0 0 0; }
        .esquina-tr { top: 0; right: 0; border-top-width: 5px; border-right-width: 5px; border-radius: 0 6px 0 0; }
        .esquina-bl { bottom: 0; left: 0; border-bottom-width: 5px; border-left-width: 5px; border-radius: 0 0 0 6px; }
        .esquina-br { bottom: 0; right: 0; border-bottom-width: 5px; border-right-width: 5px; border-radius: 0 0 6px 0; }

        .pasos { width: 100%; border-collapse: collapse; margin: 0 0 26px; }
        .pasos td {
            width: 33.33%;
            text-align: center;
            vertical-align: top;
            padding: 0 10px;
        }
        .paso-icono {
            display: block;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: #be123c;
            margin: 0 auto 10px;
            padding: 10px;
        }
        .paso-icono img { width: 24px; height: 24px; display: block; margin: 0 auto; }
        .paso-label { font-size: 11px; font-weight: 700; color: #be123c; text-transform: uppercase; letter-spacing: .06em; margin: 0 0 3px; }
        .paso-texto { font-size: 12px; color: #44403c; line-height: 1.4; margin: 0; }

        .nota {
            font-size: 11.5px;
            color: #a8a29e;
            max-width: 420px;
            margin: 0 auto;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <div class="band band-top">
        <p>LUPE LEGAL</p>
    </div>

    <div class="contenido">
        @if($logoBase64)
            <div class="logo-empresa"><img src="{{ $logoBase64 }}" alt="{{ $empresa->razon_social }}"></div>
        @endif

        <span class="kicker">Reglamento Interno de Trabajo</span>
        <h1 class="titulo">Escanea y conoce<br>tus derechos y deberes</h1>
        <span class="empresa">{{ $empresa->razon_social }}</span>

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
                    <div class="paso-icono">
                        <img src="{{ $iconoCamara }}" alt="">
                    </div>
                    <p class="paso-label">Paso 1</p>
                    <p class="paso-texto">Abre la cámara de tu celular</p>
                </td>
                <td>
                    <div class="paso-icono">
                        <img src="{{ $iconoScan }}" alt="">
                    </div>
                    <p class="paso-label">Paso 2</p>
                    <p class="paso-texto">Apunta al código QR</p>
                </td>
                <td>
                    <div class="paso-icono">
                        <img src="{{ $iconoCheck }}" alt="">
                    </div>
                    <p class="paso-label">Paso 3</p>
                    <p class="paso-texto">Lee y acepta el Reglamento</p>
                </td>
            </tr>
        </table>

        <p class="nota">Este código siempre lleva a la versión vigente del Reglamento, sin importar cuántas veces la empresa lo actualice.</p>
    </div>

    <div class="band band-bottom">
        <p>Generado por LUPE Legal · {{ now()->format('d/m/Y') }}</p>
    </div>
</body>
</html>
