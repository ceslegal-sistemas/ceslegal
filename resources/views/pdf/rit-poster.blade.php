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
        .band-top { padding: 26px 20px; }
        .band-top .marca { font-size: 22px; font-weight: 700; letter-spacing: .08em; margin: 0; }
        .band-bottom { padding: 16px 20px; position: fixed; bottom: 0; left: 0; }
        .band-bottom p { font-size: 11px; margin: 0; color: #fecdd3; }

        .contenido { padding: 46px 55px 30px; text-align: center; }

        .kicker {
            display: block;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: #be123c;
            margin: 0 0 18px;
        }

        .titulo {
            font-size: 38px;
            font-weight: 700;
            line-height: 1.22;
            margin: 0 0 16px;
            color: #1c1917;
        }

        .empresa {
            display: block;
            font-size: 15px;
            font-weight: 600;
            color: #78716c;
            margin: 0 0 30px;
        }

        .qr-marco {
            position: relative;
            display: block;
            width: 300px;
            height: 300px;
            padding: 26px;
            margin: 0 auto 34px;
        }
        .qr-marco img { width: 300px; height: 300px; display: block; }
        .esquina {
            position: absolute;
            width: 30px;
            height: 30px;
            border-color: #be123c;
            border-style: solid;
            border-width: 0;
        }
        .esquina-tl { top: 0; left: 0; border-top-width: 5px; border-left-width: 5px; border-radius: 6px 0 0 0; }
        .esquina-tr { top: 0; right: 0; border-top-width: 5px; border-right-width: 5px; border-radius: 0 6px 0 0; }
        .esquina-bl { bottom: 0; left: 0; border-bottom-width: 5px; border-left-width: 5px; border-radius: 0 0 0 6px; }
        .esquina-br { bottom: 0; right: 0; border-bottom-width: 5px; border-right-width: 5px; border-radius: 0 0 6px 0; }

        .pasos { width: 100%; border-collapse: collapse; margin: 0 0 30px; }
        .pasos td {
            width: 33.33%;
            text-align: center;
            vertical-align: top;
            padding: 0 10px;
            font-size: 12.5px;
            color: #44403c;
            line-height: 1.45;
        }
        .paso-num {
            display: inline-block;
            width: 26px;
            height: 26px;
            line-height: 26px;
            border-radius: 50%;
            background: #be123c;
            color: #ffffff;
            font-weight: 700;
            font-size: 13px;
            margin-bottom: 8px;
        }

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
        <p class="marca">LUPE LEGAL</p>
    </div>

    <div class="contenido">
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
                    <span class="paso-num">1</span><br>
                    Abre la cámara de tu celular
                </td>
                <td>
                    <span class="paso-num">2</span><br>
                    Apunta al código QR
                </td>
                <td>
                    <span class="paso-num">3</span><br>
                    Lee y acepta el Reglamento
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
