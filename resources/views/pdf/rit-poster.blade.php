<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 2cm 2.3cm; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Helvetica', 'Arial', sans-serif;
            color: #2A2A2A;
            background: #ffffff;
        }

        .portada-icono { text-align: center; margin-bottom: 6pt; }
        .portada-icono img { width: 40px; height: 40px; }

        .titulo-doc {
            text-align: center;
            font-size: 15pt;
            font-weight: bold;
            margin: 0 0 18pt;
        }

        .logo-empresa { text-align: center; margin-bottom: 10pt; }
        .logo-empresa img { max-height: 60px; max-width: 220px; }
        .empresa-nombre { text-align: center; font-size: 11pt; font-weight: bold; color: #555; margin: 0 0 20pt; }

        .titulo-principal {
            text-align: center;
            font-size: 22pt;
            font-weight: bold;
            line-height: 1.3;
            margin: 0 0 26pt;
        }

        .caja-qr {
            background: #E4F1F1;
            border-radius: 4pt;
            padding: 22pt;
            text-align: center;
            margin: 0 0 18pt;
        }
        .caja-qr img { width: 220px; height: 220px; }

        .bloque-bullets {
            border: 1px solid #D8E2E1;
            border-radius: 4pt;
            padding: 12pt 16pt;
            margin-bottom: 20pt;
        }
        .bloque-bullets table { width: 100%; border-collapse: collapse; }
        .bloque-bullets td { padding: 4pt 0; font-size: 10.5pt; vertical-align: middle; }
        .bloque-bullets .paso-icono-td { width: 26px; }
        .bloque-bullets .paso-icono-td img { width: 20px; height: 20px; }
        .bloque-bullets .paso-num { font-weight: bold; color: #1B5E63; }

        .caja--nota {
            background: #F5FAFA;
            border-radius: 4pt;
            padding: 9pt 12pt;
            font-size: 9.5pt;
            color: #555;
            text-align: center;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <div class="portada-icono"><img src="{{ $iconoPortada }}" alt=""></div>
    <p class="titulo-doc">REGLAMENTO INTERNO DE TRABAJO</p>

    @if($logoBase64)
        <div class="logo-empresa"><img src="{{ $logoBase64 }}" alt="{{ $empresa->razon_social }}"></div>
    @else
        <p class="empresa-nombre">{{ $empresa->razon_social }}</p>
    @endif

    <h1 class="titulo-principal">Escanea y conoce<br>tus derechos y deberes</h1>

    <div class="caja-qr">
        <img src="data:image/svg+xml;base64,{{ $qrBase64 }}" alt="Código QR del Reglamento Interno">
    </div>

    <div class="bloque-bullets">
        <table>
            <tr>
                <td class="paso-icono-td"><img src="{{ $iconoTelefono }}" alt=""></td>
                <td><span class="paso-num">1.</span> Abre la cámara de tu celular.</td>
            </tr>
            <tr>
                <td class="paso-icono-td"></td>
                <td><span class="paso-num">2.</span> Apunta al código QR de arriba.</td>
            </tr>
            <tr>
                <td class="paso-icono-td"></td>
                <td><span class="paso-num">3.</span> Lee el Reglamento y acéptalo desde tu celular.</td>
            </tr>
        </table>
    </div>

    <div class="caja--nota">
        Este código siempre lleva a la versión vigente del Reglamento, sin importar cuántas veces la empresa lo actualice.
    </div>
</body>
</html>
