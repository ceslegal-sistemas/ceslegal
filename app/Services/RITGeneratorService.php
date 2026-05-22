<?php

namespace App\Services;

use App\Models\Empresa;
use App\Services\BibliotecaLegalService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Dompdf\Dompdf;
use Dompdf\Options;
use Dompdf\Adapter\CPDF as CpdfAdapter;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;

class RITGeneratorService
{
    /** Modelo que terminó generando el texto. Consultable desde el código llamador. */
    public string $modeloUsado = '';

    /** True solo cuando se llegó al último recurso (flash-lite). */
    public bool $esFallbackLite = false;

    /**
     * Genera el texto completo del RIT usando Gemini a partir de las respuestas del cuestionario F2.
     */
    public function generarTextoRIT(array $respuestas, Empresa $empresa): string
    {
        $config = config('services.ia.gemini', []);
        $apiKey = $config['api_key'] ?? '';

        // RIT: Flash genera en 10-30s (dentro del timeout HTTP).
        // Pro tomaba 60-120s síncronamente → timeout de Livewire/nginx.
        // Cascade: Flash → Flash-Lite (último recurso, activa aviso de calidad reducida).
        $modelPrincipal = 'gemini-2.5-flash';
        $modelosCascada = ['gemini-2.5-flash', 'gemini-2.5-flash-lite'];

        $prompt = $this->construirPrompt($respuestas, $empresa);

        // Limpiar bytes UTF-8 inválidos que provienen de fragmentos de PDFs/DOCX
        // y que rompen json_encode al construir el payload
        $prompt = preg_replace(
            '/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u',
            '',
            $prompt
        ) ?? iconv('UTF-8', 'UTF-8//IGNORE', $prompt);

        $payload = [
            'contents' => [
                ['parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => [
                'temperature'     => 0.3,
                'maxOutputTokens' => 32768,
                'topP'            => 0.95,
            ],
        ];

        $lastError    = null;
        $totalModelos = count($modelosCascada);

        foreach (array_values($modelosCascada) as $idx => $model) {
            $url         = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
            $esUltimo    = ($idx === $totalModelos - 1);
            // Flash: 2 intentos antes de ceder al lite. Flash-lite: 2 intentos.
            $maxIntentos = 2;
            $esperas     = [10, 30]; // segundos entre intentos

            Log::info('RITGeneratorService: generando texto con Gemini', [
                'empresa_id' => $empresa->id,
                'model'      => $model,
                'intento_modelo' => $idx + 1,
            ]);

            $sobrecarga = false;

            for ($intento = 1; $intento <= $maxIntentos; $intento++) {
                $response = Http::withHeaders(['Content-Type' => 'application/json'])
                    ->timeout(90)
                    ->post($url, $payload);

                if ($response->successful()) {
                    $data  = $response->json();
                    $parts = $data['candidates'][0]['content']['parts'] ?? [];

                    // gemini-2.5-flash (thinking model): el texto real está en el último part sin "thought"
                    // gemini-2.0/1.5-flash: parts[0]['text'] es el contenido directamente
                    $texto = '';
                    foreach (array_reverse($parts) as $part) {
                        if (empty($part['thought']) && !empty($part['text'])) {
                            $texto = $part['text'];
                            break;
                        }
                    }
                    if (empty($texto)) {
                        $texto = $parts[0]['text'] ?? '';
                    }
                    if (empty($texto)) {
                        throw new \RuntimeException('Respuesta de Gemini sin contenido válido');
                    }

                    $this->modeloUsado    = $model;
                    $this->esFallbackLite = ($model === 'gemini-2.5-flash-lite');

                    if ($idx > 0) {
                        Log::info('RITGeneratorService: texto generado con modelo de respaldo', [
                            'empresa_id'    => $empresa->id,
                            'model_usado'   => $model,
                            'model_primario' => $modelPrincipal,
                        ]);
                    }

                    return trim($texto);
                }

                $status    = $response->status();
                $lastError = $response->body();

                // 503/429 = sobrecarga → probar siguiente modelo en cascade
                // Otros errores (400, 401, 404) → no tiene sentido reintentar con otro modelo
                $esSobrecarga  = in_array($status, [429, 503]);
                $esTransitorio = in_array($status, [500, 502, 504]);

                Log::warning('RITGeneratorService: fallo en intento', [
                    'empresa_id'  => $empresa->id,
                    'model'       => $model,
                    'intento'     => $intento,
                    'status'      => $status,
                    'cascade'     => $esSobrecarga && !$esUltimo,
                ]);

                if ($esSobrecarga) {
                    // Espera corta y luego intenta con el siguiente modelo
                    if ($intento < $maxIntentos) {
                        sleep($esperas[$intento - 1]);
                    } else {
                        $sobrecarga = true;
                        break;
                    }
                } elseif ($esTransitorio && $intento < $maxIntentos) {
                    sleep($esperas[$intento - 1]);
                } else {
                    // Error permanente — no tiene sentido probar otro modelo
                    throw new \RuntimeException('Error en API Gemini: ' . $lastError);
                }
            }

            // Si llegamos aquí por sobrecarga y hay más modelos, continuar cascade
            if ($sobrecarga && !$esUltimo) {
                Log::warning('RITGeneratorService: modelo saturado, cambiando al siguiente', [
                    'empresa_id'    => $empresa->id,
                    'model_fallido' => $model,
                    'model_next'    => $modelosCascada[$idx + 1] ?? 'ninguno',
                ]);
                continue;
            }

            break;
        }

        throw new \RuntimeException('Error en API Gemini (todos los modelos intentados): ' . $lastError);
    }

    /**
     * Genera el documento Word (.docx) con el texto del RIT.
     * Retorna la ruta relativa dentro de storage/app/private/.
     */
    /**
     * Genera el DOCX y lo guarda en storage/app/private/rits/{id}/reglamento.docx.
     * Retorna la ruta relativa. Lanza excepción si no puede escribir.
     */
    public function generarDocumentoWord(string $textoRIT, Empresa $empresa): string
    {
        $directorio = "private/rits/{$empresa->id}";
        Storage::makeDirectory($directorio);

        $rutaRelativa = "{$directorio}/reglamento.docx";
        $rutaAbsoluta = storage_path("app/{$rutaRelativa}");

        $this->escribirDocx($textoRIT, $empresa, $rutaAbsoluta);

        Log::info('RITGeneratorService: documento Word guardado', [
            'empresa_id' => $empresa->id,
            'ruta'       => $rutaRelativa,
        ]);

        return $rutaRelativa;
    }

    /**
     * Genera un PDF de solo lectura (no editable) en un archivo temporal.
     * Usa DOMPDF + encriptación Cpdf para bloquear modificaciones.
     * El documento se abre sin contraseña pero no puede ser editado.
     */
    public function generarPDFTemp(string $textoRIT, Empresa $empresa): string
    {
        $html = $this->textoAHtml($textoRIT, $empresa);

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultPaperSize', 'letter');
        $options->set('isFontSubsettingEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();

        // Número de página centrado en el pie — DomPDF canvas API (más fiable que CSS counter)
        $canvas   = $dompdf->getCanvas();
        $w        = $canvas->get_width();
        $h        = $canvas->get_height();
        $font     = $dompdf->getFontMetrics()->getFont('Helvetica');
        // Omitir portada (página 1) — solo aplica a partir de la página 2
        $canvas->page_script(function (int $pageNum, int $pageCount, $canvas, $fontMetrics) use ($w, $h) {
            if ($pageNum <= 1) {
                return; // portada sin número
            }
            $font  = $fontMetrics->getFont('Helvetica');
            $texto = "— {$pageNum} —";
            $tw    = $fontMetrics->getTextWidth($texto, $font, 7.5);
            $canvas->text(($w - $tw) / 2, $h - 40, $texto, $font, 7.5, [0.60, 0.64, 0.68]);
        });

        // Encriptar: solo lectura + impresión permitida — sin contraseña para abrir
        // La clave del propietario es derivada del app key + empresa → nadie la conoce
        if ($canvas instanceof CpdfAdapter) {
            $ownerPass = substr(hash('sha256', config('app.key') . $empresa->id . 'rit'), 0, 32);
            $canvas->get_cpdf()->setEncryption('', $ownerPass, ['print']);
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'rit_') . '.pdf';
        file_put_contents($tmpPath, $dompdf->output());

        return $tmpPath;
    }

    /**
     * Convierte el texto plano del RIT a HTML profesional para DOMPDF.
     * Genera portada, encabezados de capítulo, artículos, parágrafos y listas con diseño formal.
     */
    private function textoAHtml(string $textoRIT, Empresa $empresa): string
    {
        $eNombre       = htmlspecialchars($empresa->nombre_completo ?? $empresa->razon_social ?? '', ENT_QUOTES, 'UTF-8');
        $eNit          = htmlspecialchars($empresa->nit ?? '', ENT_QUOTES, 'UTF-8');
        $eRepresentante= htmlspecialchars($empresa->representante_legal ?? '', ENT_QUOTES, 'UTF-8');
        $eCiudad       = htmlspecialchars($empresa->ciudad ?? '', ENT_QUOTES, 'UTF-8');
        $eDpto         = htmlspecialchars($empresa->departamento ?? '', ENT_QUOTES, 'UTF-8');
        $eAnio         = now()->year;
        $eLugar        = trim($eCiudad . ($eDpto ? ', ' . $eDpto : ''));

        $cuerpo      = '';
        $enLista     = false;  // dentro de un <div class="lista">

        $lineas = explode("\n", $textoRIT);

        foreach ($lineas as $linea) {
            $linea = rtrim($linea);

            // Línea vacía
            if (trim($linea) === '') {
                if ($enLista) { $cuerpo .= '</div>'; $enLista = false; }
                continue;
            }

            // Limpiar markdown residual (no debería haber, pero por si acaso)
            $linea = preg_replace('/\*{1,2}([^*]+)\*{1,2}/', '$1', $linea);
            $linea = ltrim($linea, '-*# ');
            $linea = rtrim($linea);

            // ── CAPÍTULO ──────────────────────────────────────────────────────
            if (preg_match('/^(CAPÍTULO\s+[IVXLCDM]+)\s*[—–\-]+\s*(.+)$/iu', $linea, $m)) {
                if ($enLista) { $cuerpo .= '</div>'; $enLista = false; }
                $capNum   = htmlspecialchars(strtoupper($m[1]), ENT_QUOTES, 'UTF-8');
                $capTit   = htmlspecialchars(strtoupper(trim($m[2])), ENT_QUOTES, 'UTF-8');
                $cuerpo .= '<div class="cap-wrap">'
                          . '<div class="cap-header">'
                          . '<span class="cap-num">' . $capNum . '</span>'
                          . '<span class="cap-tit">' . $capTit . '</span>'
                          . '</div></div>';
                continue;
            }

            // ── ARTÍCULO ──────────────────────────────────────────────────────
            if (preg_match('/^(ARTÍCULO\s+\d+\.)\s*([A-ZÁÉÍÓÚÑÜ][A-ZÁÉÍÓÚÑÜ ]+\.)?\s*(.*)/iu', $linea, $m)) {
                if ($enLista) { $cuerpo .= '</div>'; $enLista = false; }
                $artNum   = htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8');
                $artNom   = isset($m[2]) && $m[2] !== ''
                              ? htmlspecialchars(rtrim($m[2], '.'), ENT_QUOTES, 'UTF-8')
                              : null;
                $artBody  = htmlspecialchars(trim($m[3] ?? ''), ENT_QUOTES, 'UTF-8');
                $cuerpo .= '<p class="art">'
                          . '<span class="art-num">' . $artNum . '</span>'
                          . ($artNom ? ' <span class="art-nom">' . $artNom . '.</span>' : '')
                          . ($artBody !== '' ? ' <span class="art-body">' . $artBody . '</span>' : '')
                          . '</p>';
                continue;
            }

            // ── PARÁGRAFO ─────────────────────────────────────────────────────
            if (preg_match('/^(PARÁGRAFO(?:\s+(?:ÚNICO|PRIMERO|SEGUNDO|TERCERO|CUARTO|\d+))?)\s*[:.]\s*(.*)/iu', $linea, $m)) {
                if ($enLista) { $cuerpo .= '</div>'; $enLista = false; }
                $pLbl  = htmlspecialchars(strtoupper($m[1]), ENT_QUOTES, 'UTF-8');
                $pBody = htmlspecialchars(trim($m[2] ?? ''), ENT_QUOTES, 'UTF-8');
                $cuerpo .= '<p class="paragrafo">'
                          . '<span class="para-lbl">' . $pLbl . ':</span>'
                          . ($pBody !== '' ? ' ' . $pBody : '')
                          . '</p>';
                continue;
            }

            // ── Sub-ítems numerados: 1) o a) ─────────────────────────────────
            if (preg_match('/^\s*(\d+|[a-zA-Z])\)\s+(.+)$/', $linea, $m)) {
                if (!$enLista) { $cuerpo .= '<div class="lista">'; $enLista = true; }
                $marc = htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8');
                $txt  = htmlspecialchars(trim($m[2]), ENT_QUOTES, 'UTF-8');
                $cuerpo .= '<div class="lista-item">'
                          . '<span class="lista-marc">' . $marc . ')</span>'
                          . '<span class="lista-txt">' . $txt . '</span>'
                          . '</div>';
                continue;
            }

            // ── Viñetas: • ────────────────────────────────────────────────────
            if (preg_match('/^\s*[•·▪▸]\s+(.+)$/', $linea, $m)) {
                if (!$enLista) { $cuerpo .= '<div class="lista">'; $enLista = true; }
                $txt = htmlspecialchars(trim($m[1]), ENT_QUOTES, 'UTF-8');
                $cuerpo .= '<div class="lista-item">'
                          . '<span class="lista-marc">•</span>'
                          . '<span class="lista-txt">' . $txt . '</span>'
                          . '</div>';
                continue;
            }

            // ── Texto genérico ────────────────────────────────────────────────
            if ($enLista) { $cuerpo .= '</div>'; $enLista = false; }
            $cuerpo .= '<p class="body">' . htmlspecialchars($linea, ENT_QUOTES, 'UTF-8') . '</p>';
        }

        if ($enLista) { $cuerpo .= '</div>'; }

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>

/* ══ Página ═══════════════════════════════════════════════════════════════ */
@page {
    size: letter portrait;
    margin: 2.5cm;
}
@page cover {
    margin: 0;
}

/* ══ Encabezado y pie de página corridos ══════════════════════════════════ */
.hdr {
    position: fixed;
    top: -2.1cm;
    left: 2.5cm;
    right: 2.5cm;
    height: 1.5cm;
    border-bottom: 0.5pt solid #c9a84c;
}
.hdr-table {
    display: table;
    width: 100%;
    height: 100%;
}
.hdr-left {
    display: table-cell;
    vertical-align: bottom;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 7.5pt;
    color: #5a6a7a;
    padding-bottom: 4pt;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}
.hdr-right {
    display: table-cell;
    vertical-align: bottom;
    text-align: right;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 7.5pt;
    color: #5a6a7a;
    padding-bottom: 4pt;
    letter-spacing: 0.04em;
}
.ftr {
    position: fixed;
    bottom: -2cm;
    left: 2.5cm;
    right: 2.5cm;
    height: 1.4cm;
    border-top: 0.5pt solid #e2e5ea;
    text-align: center;
}
.ftr-inner {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 8pt;
    color: #9ca3af;
    padding-top: 5pt;
}

/* ══ Base ═════════════════════════════════════════════════════════════════ */
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'Times New Roman', Times, serif;
    font-size: 11pt;
    line-height: 1.65;
    color: #111827;
}

/* ══ Portada ══════════════════════════════════════════════════════════════ */
.cover {
    page: cover;
    page-break-after: always;
}
.cover-top {
    background: #0d1f3c;
    padding: 4.8cm 3cm 3.2cm 3cm;
    text-align: center;
}
.cover-pais {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 8pt;
    letter-spacing: 0.22em;
    text-transform: uppercase;
    color: #6b8cad;
    margin-bottom: 2.2cm;
}
.cover-titulo {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 28pt;
    font-weight: bold;
    color: #ffffff;
    line-height: 1.15;
    letter-spacing: 0.01em;
    margin-bottom: 0.5cm;
}
.cover-linea {
    display: block;
    width: 3.5cm;
    height: 3pt;
    background: #c9a84c;
    margin: 1cm auto;
}
.cover-empresa {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 17pt;
    font-weight: bold;
    color: #c9a84c;
    letter-spacing: 0.025em;
    margin-bottom: 0.3cm;
}
.cover-nit {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 10pt;
    color: #7e9bb5;
    letter-spacing: 0.04em;
}
.cover-bottom {
    background: #ffffff;
    border-top: 4pt solid #c9a84c;
    padding: 1.4cm 3cm;
}
.meta-tbl {
    display: table;
    width: 100%;
}
.meta-row {
    display: table-row;
}
.meta-cell {
    display: table-cell;
    text-align: center;
    vertical-align: middle;
    padding: 0.2cm 0.8cm;
}
.meta-sep {
    display: table-cell;
    width: 1pt;
    background: #e5e7eb;
    vertical-align: middle;
    padding: 0 0;
}
.meta-lbl {
    display: block;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 6.5pt;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: #9ca3af;
    margin-bottom: 3pt;
}
.meta-val {
    display: block;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 10.5pt;
    font-weight: bold;
    color: #0d1f3c;
}

/* ══ Capítulo ════════════════════════════════════════════════════════════ */
.cap-wrap {
    margin-top: 20pt;
    margin-bottom: 11pt;
    page-break-inside: avoid;
}
.cap-header {
    background: #0d1f3c;
    padding: 9pt 14pt 10pt 14pt;
}
.cap-num {
    display: block;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 7pt;
    font-weight: bold;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: #c9a84c;
    margin-bottom: 3pt;
}
.cap-tit {
    display: block;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 12.5pt;
    font-weight: bold;
    color: #ffffff;
    letter-spacing: 0.015em;
}

/* ══ Artículo ════════════════════════════════════════════════════════════ */
.art {
    margin-top: 9pt;
    margin-bottom: 5pt;
    text-align: justify;
    page-break-inside: avoid;
    line-height: 1.65;
}
.art-num {
    font-family: Arial, Helvetica, sans-serif;
    font-weight: bold;
    font-size: 11pt;
    color: #0d1f3c;
}
.art-nom {
    font-family: Arial, Helvetica, sans-serif;
    font-weight: bold;
    font-size: 11pt;
    color: #0d1f3c;
}
.art-body {
    font-family: 'Times New Roman', Times, serif;
    font-size: 11pt;
    color: #111827;
}

/* ══ Parágrafo ═══════════════════════════════════════════════════════════ */
.paragrafo {
    margin-top: 5pt;
    margin-bottom: 4pt;
    margin-left: 20pt;
    padding-left: 10pt;
    border-left: 2.5pt solid #c9a84c;
    font-size: 10.5pt;
    text-align: justify;
    line-height: 1.6;
}
.para-lbl {
    font-family: Arial, Helvetica, sans-serif;
    font-weight: bold;
    font-size: 10.5pt;
    color: #0d1f3c;
}

/* ══ Listas ══════════════════════════════════════════════════════════════ */
.lista {
    margin-left: 20pt;
    margin-top: 4pt;
    margin-bottom: 5pt;
}
.lista-item {
    display: table;
    width: 100%;
    margin-bottom: 3pt;
}
.lista-marc {
    display: table-cell;
    font-family: Arial, Helvetica, sans-serif;
    font-weight: bold;
    font-size: 10.5pt;
    color: #0d1f3c;
    width: 16pt;
    vertical-align: top;
    padding-top: 1pt;
}
.lista-txt {
    display: table-cell;
    font-family: 'Times New Roman', Times, serif;
    font-size: 10.5pt;
    text-align: justify;
    line-height: 1.55;
    vertical-align: top;
}

/* ══ Cuerpo genérico ══════════════════════════════════════════════════════ */
.body {
    margin-top: 4pt;
    margin-bottom: 5pt;
    text-align: justify;
    font-size: 11pt;
    line-height: 1.65;
}

/* ══ Bloque de firma ══════════════════════════════════════════════════════ */
.firma-wrap {
    margin-top: 52pt;
    page-break-inside: avoid;
}
.firma-tbl {
    display: table;
    width: 100%;
}
.firma-col {
    display: table-cell;
    width: 50%;
    text-align: center;
    padding: 0 2cm;
    vertical-align: bottom;
}
.firma-linea {
    border-top: 1pt solid #0d1f3c;
    margin-bottom: 5pt;
}
.firma-nombre {
    font-family: Arial, Helvetica, sans-serif;
    font-weight: bold;
    font-size: 10pt;
    color: #0d1f3c;
}
.firma-cargo {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 8.5pt;
    color: #6b7280;
    margin-top: 2pt;
}

</style>
</head>
<body>

<!-- Encabezado corrido (se repite en cada página, excepto portada) -->
<div class="hdr">
  <div class="hdr-table">
    <span class="hdr-left">{$eNombre}</span>
    <span class="hdr-right">Reglamento Interno de Trabajo</span>
  </div>
</div>

<!-- Pie corrido -->
<div class="ftr">
  <div class="ftr-inner">&#8212;</div>
</div>

<!-- ══ PORTADA ════════════════════════════════════════════════════════════ -->
<div class="cover">
  <div class="cover-top">
    <div class="cover-pais">República de Colombia · Ministerio del Trabajo</div>
    <div class="cover-titulo">REGLAMENTO<br>INTERNO<br>DE TRABAJO</div>
    <span class="cover-linea"></span>
    <div class="cover-empresa">{$eNombre}</div>
    <div class="cover-nit">NIT {$eNit}</div>
  </div>
  <div class="cover-bottom">
    <div class="meta-tbl">
      <div class="meta-row">
        <div class="meta-cell">
          <span class="meta-lbl">Ciudad</span>
          <span class="meta-val">{$eLugar}</span>
        </div>
        <div class="meta-sep"></div>
        <div class="meta-cell">
          <span class="meta-lbl">Año</span>
          <span class="meta-val">{$eAnio}</span>
        </div>
        <div class="meta-sep"></div>
        <div class="meta-cell">
          <span class="meta-lbl">Representante Legal</span>
          <span class="meta-val">{$eRepresentante}</span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ══ CONTENIDO ══════════════════════════════════════════════════════════ -->
{$cuerpo}

<!-- ══ FIRMA ══════════════════════════════════════════════════════════════ -->
<div class="firma-wrap">
  <div class="firma-tbl">
    <div class="firma-col">
      <div class="firma-linea"></div>
      <div class="firma-nombre">{$eRepresentante}</div>
      <div class="firma-cargo">Representante Legal</div>
      <div class="firma-cargo">{$eNombre}</div>
    </div>
    <div class="firma-col">
      <div class="firma-linea"></div>
      <div class="firma-nombre">Firma del Trabajador</div>
      <div class="firma-cargo">Fecha de recibido: ___________________________</div>
    </div>
  </div>
</div>

</body>
</html>
HTML;
    }

    /**
     * Genera el DOCX en un archivo temporal del sistema y retorna su ruta absoluta.
     * Usar para descargas en servidores con permisos restringidos en storage.
     */
    public function generarDocumentoWordTemp(string $textoRIT, Empresa $empresa): string
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'rit_') . '.docx';
        $this->escribirDocx($textoRIT, $empresa, $tmpPath);
        return $tmpPath;
    }

    /**
     * Genera el DOCX, lo almacena en el disco 'public' y retorna la ruta relativa.
     * Usa un temp file intermedio para evitar errores de permisos en directorios storage.
     * Retorna null si no pudo guardar en disco.
     */
    public function guardarDocxPublico(string $textoRIT, Empresa $empresa): ?string
    {
        try {
            $tmpPath     = tempnam(sys_get_temp_dir(), 'rit_') . '.docx';
            $this->escribirDocx($textoRIT, $empresa, $tmpPath);

            $rutaPublica = "rits/{$empresa->id}/reglamento.docx";
            Storage::disk('public')->put($rutaPublica, file_get_contents($tmpPath));
            @unlink($tmpPath);

            return $rutaPublica;
        } catch (\Throwable $e) {
            Log::warning('RITGeneratorService: no se pudo guardar DOCX en disco público', [
                'empresa_id' => $empresa->id,
                'error'      => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function escribirDocx(string $textoRIT, Empresa $empresa, string $rutaAbsoluta): void
    {
        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Times New Roman');
        $phpWord->setDefaultFontSize(12);

        $section = $phpWord->addSection([
            'marginTop'    => Converter::cmToTwip(2.5),
            'marginBottom' => Converter::cmToTwip(2.5),
            'marginLeft'   => Converter::cmToTwip(3),
            'marginRight'  => Converter::cmToTwip(2.5),
        ]);

        $section->addText(
            'REGLAMENTO INTERNO DE TRABAJO',
            ['bold' => true, 'size' => 14, 'name' => 'Times New Roman'],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 120]
        );

        $section->addText(
            strtoupper($empresa->nombre_completo),
            ['bold' => true, 'size' => 12, 'name' => 'Times New Roman'],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 240]
        );

        $lineas = explode("\n", $textoRIT);
        foreach ($lineas as $linea) {
            $linea = rtrim($linea);

            if ($linea === '') {
                $section->addTextBreak(1);
                continue;
            }

            // Detectar líneas completamente en negrita markdown (**texto**)
            $esNegritaMarkdown = preg_match('/^\*{1,2}(.+?)\*{1,2}$/', $linea, $m);
            $textoLimpio = $esNegritaMarkdown
                ? trim($m[1])
                : preg_replace('/\*{1,2}([^*]+)\*{1,2}/', '$1', $linea); // quitar ** inline

            // Quitar guiones, asteriscos, almohadillas al inicio (e.g. "- ARTÍCULO 1.")
            $textoLimpio = ltrim($textoLimpio, '-*# ');
            $textoLimpio = trim($textoLimpio);

            // Detectar títulos: CAPÍTULO, ARTÍCULO, o línea markdown-bold
            $esTitulo = $esNegritaMarkdown
                || preg_match('/^(CAPÍTULO|ARTÍCULO|ART\.)\s*/ui', $textoLimpio);

            if ($esTitulo) {
                $section->addText(
                    $textoLimpio,
                    ['bold' => true, 'size' => 12, 'name' => 'Times New Roman'],
                    ['spaceAfter' => 80, 'spaceBefore' => 120]
                );
            } else {
                $section->addText(
                    $textoLimpio,
                    ['size' => 12, 'name' => 'Times New Roman'],
                    ['spaceAfter' => 60, 'lineHeight' => 1.5]
                );
            }
        }

        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($rutaAbsoluta);
    }

    private function construirPrompt(array $r, Empresa $empresa): string
    {
        // RAG por área temática: múltiples consultas para mayor cobertura de la biblioteca
        $biblioteca = app(BibliotecaLegalService::class);

        $queriesTematicas = [
            'admisión período de prueba jornada laboral horas extras recargos nocturnos',
            'vacaciones licencias maternidad paternidad salario forma de pago periodicidad',
            'régimen disciplinario faltas sanciones procedimiento descargos suspensión',
            'seguridad salud trabajo SG-SST COPASST vigía accidentes exámenes médicos EPP Decreto 1072',
            'acoso laboral sexual comité convivencia Ley 1010 Ley 2365 protocolo denuncia',
        ];

        $fragmentosPorTema = [];
        $yaVisto = [];
        foreach ($queriesTematicas as $query) {
            $resultado = $biblioteca->buscarFragmentos($query, limite: 5, umbral: 0.35);
            if ($resultado && !in_array(md5($resultado), $yaVisto)) {
                $fragmentosPorTema[] = $resultado;
                $yaVisto[] = md5($resultado);
            }
        }
        $contextoBiblioteca = implode("\n\n---\n\n", array_filter($fragmentosPorTema));
        // Limitar el contexto de biblioteca para no saturar el prompt
        if (strlen($contextoBiblioteca) > 8000) {
            $contextoBiblioteca = substr($contextoBiblioteca, 0, 8000) . "\n[...fragmentos adicionales omitidos por límite de longitud]";
        }

        $razonSocial  = $empresa->nombre_completo; // razón social + tipo societario
        $nit          = $empresa->nit;

        // Helpers para aplanar arrays a texto legible
        $lista  = fn($arr) => is_array($arr) ? implode(', ', array_filter($arr)) : ($arr ?? '');
        $lineas = fn($arr) => is_array($arr) ? implode("\n  ", array_filter($arr)) : ($arr ?? '');

        // Cargos: array de {nombre_cargo, puede_sancionar}
        $cargosTexto = '';
        foreach ((array)($r['cargos'] ?? []) as $c) {
            $nombre   = $c['nombre_cargo'] ?? '';
            $sanciona = ($c['puede_sancionar'] ?? false) ? 'puede sancionar' : 'no sanciona';
            if ($nombre) $cargosTexto .= "  - {$nombre} ({$sanciona})\n";
        }

        // Sucursales: array de {ciudad, direccion, num_trabajadores}
        $sucursalesTexto = '';
        foreach ((array)($r['sucursales'] ?? []) as $s) {
            $ciudad = $s['ciudad'] ?? '';
            $dir    = $s['direccion'] ?? '';
            $trab   = $s['num_trabajadores'] ?? '';
            if ($ciudad) $sucursalesTexto .= "  - {$ciudad}: {$dir}, {$trab} trabajadores\n";
        }

        // Beneficios extralegalesdocument: array de {nombre_beneficio, descripcion}
        $beneficiosTexto = '';
        foreach ((array)($r['beneficios_extralegales'] ?? []) as $b) {
            $nb = $b['nombre_beneficio'] ?? '';
            $db = $b['descripcion'] ?? '';
            if ($nb) $beneficiosTexto .= "  - {$nb}: {$db}\n";
        }

        $representante = $empresa->representante_legal ?? '';
        $fechaHoy      = now()->locale('es')->translatedFormat('j \d\e F \d\e Y');

        $infoEmpresa = "
EMPRESA Y ACTIVIDAD
- Razón social: {$razonSocial}
- Tipo societario: " . ($empresa->tipo_societario ?? 'No especificado') . "
- NIT: {$nit}
- Domicilio: " . ($r['domicilio'] ?? '') . "
- Representante Legal: {$representante}
- Actividad económica principal: " . ($r['actividad_economica'] ?? '') . "
- Actividades secundarias: " . ($r['actividades_secundarias'] ?? 'N/A') . "
- Número de trabajadores: " . ($r['num_trabajadores'] ?? '') . "
- Tiene sucursales: " . ($r['tiene_sucursales'] === 'si' ? "Sí\n{$sucursalesTexto}" : 'No') . "

ESTRUCTURA ORGANIZACIONAL
- Cargos:\n{$cargosTexto}
- Tiene manual de funciones: " . ($r['tiene_manual_funciones'] ?? '') . "
- Tipos de contrato: " . $lista($r['tipos_contrato'] ?? []) . "
- Usa trabajadores de misión (temporal): " . ($r['tiene_trabajadores_mision'] ?? 'no') . "

JORNADA LABORAL
- Modalidades de jornada: " . $lista($r['modalidades_jornada'] ?? []) . "
- Horario principal/administrativo: " . ($r['horario_entrada'] ?? '') . " a " . ($r['horario_salida'] ?? '') . "
- Opera en múltiples turnos: " . ($r['opera_en_turnos'] ?? 'No') . "
- Número de turnos: " . ($r['numero_turnos'] ?? 'N/A') . "
- Definición de turnos: " . ($r['definicion_turnos'] ?? 'N/A') . "
- Sistema de rotación: " . ($r['rotacion_turnos'] ?? 'N/A') . "
- Cargos con turno nocturno regular (21:00-06:00): " . ($r['cargos_nocturnos'] ?? 'N/A') . "
- Trabaja sábados: " . ($r['trabaja_sabados'] ?? 'no') . "
- Trabaja dominicales/festivos: " . ($r['trabaja_dominicales'] ?? 'no') . "
- Cargos exentos jornada máxima (Art. 162 CST): " . ($r['cargos_exentos_jornada'] ?? 'N/A') . "
- Control de asistencia: " . ($r['control_asistencia'] ?? '') . "
- Política horas extras: " . ($r['politica_horas_extras'] ?? '') . "

SALARIO Y BENEFICIOS
- Forma de pago: " . ($r['forma_pago'] ?? '') . "
- Periodicidad de pago: " . $lista($r['periodicidad_pago'] ?? []) . "
- Detalle periodicidad por cargo: " . ($r['periodicidad_detalle'] ?? 'N/A') . "
- Maneja comisiones/bonificaciones: " . ($r['maneja_comisiones'] ?? 'no') . "
- Tipo de comisiones: " . ($r['tipo_comisiones'] ?? 'N/A') . "
- Beneficios extralegales:\n" . ($beneficiosTexto ?: "  - Ninguno\n") . "
- Política de permisos personales: " . ($r['politica_permisos'] ?? '') . "
- Licencias especiales: " . ($r['tiene_licencias_especiales'] ?? 'no') . "
- Descripción licencias: " . ($r['descripcion_licencias'] ?? 'N/A') . "

RÉGIMEN DISCIPLINARIO
- Faltas leves: " . $lista($r['faltas_leves'] ?? []) . "
- Faltas graves: " . $lista($r['faltas_graves'] ?? []) . "
- Faltas muy graves: " . $lista($r['faltas_muy_graves'] ?? []) . "
- Sanciones contempladas: " . $lista($r['sanciones_contempladas'] ?? []) . "

SEGURIDAD Y SALUD EN EL TRABAJO
- Tiene SG-SST implementado: " . ($r['tiene_sg_sst'] ?? '') . "
- Riesgos principales: " . $lista($r['riesgos_principales'] ?? []) . "
- Usa EPP: " . ($r['tiene_epp'] ?? 'no') . "
- EPP requerido: " . ($r['epp_descripcion'] ?? 'N/A') . "

CONDUCTA Y CONVIVENCIA
- Política uso celular: " . ($r['politica_celular'] ?? '') . "
- Usa uniforme: " . ($r['usa_uniforme'] ?? 'no') . "
- Tiene código de ética: " . ($r['tiene_codigo_etica'] ?? 'no') . "
- Política de confidencialidad: " . ($r['politica_confidencialidad'] ?? '') . "
- Qué quiere prevenir: " . ($r['que_quiere_prevenir'] ?? '') . "
";

        $seccionBiblioteca = $contextoBiblioteca
            ? "\nFRAGMENTOS DE LA BIBLIOTECA JURÍDICA (FUENTE AUTORIZADA PARA CITAS DE ARTÍCULOS):\n"
              . "REGLA DE CITAS: Usa números de artículo y referencias normativas ÚNICAMENTE cuando\n"
              . "aparezcan en estos fragmentos. Si un artículo específico no está en los fragmentos,\n"
              . "omite el número pero REDACTA EL CONTENIDO IGUAL — no suprimas el capítulo.\n"
              . "REGLA DE CONTENIDO: TODOS los capítulos son OBLIGATORIOS independientemente de los\n"
              . "fragmentos disponibles. La falta de fragmentos sobre un tema nunca justifica omitir\n"
              . "o reducir un capítulo — usa el texto de referencia indicado en cada capítulo.\n\n"
              . $contextoBiblioteca . "\n"
            : "\nADVERTENCIA: La biblioteca legal no devolvió fragmentos. Redacta TODOS los capítulos\n"
              . "con contenido completo sin citar números de artículos específicos.\n";

        return <<<PROMPT
Eres un abogado laboral colombiano experto en reglamentos internos de trabajo.

Redacta el Reglamento Interno de Trabajo de {$razonSocial} (NIT: {$nit}) con cumplimiento estricto del Artículo 105 y siguientes del Código Sustantivo del Trabajo de Colombia.

INSTRUCCIONES DE CONTENIDO:
- Usa lenguaje formal y técnico-jurídico
- Numera cada artículo de forma consecutiva (Artículo 1, Artículo 2, ...)
- Incluye TODOS los capítulos obligatorios del CST
- Incluye capítulo sobre Política de Prevención de Acoso Sexual según la Ley 2365 de 2024
- Redacta de manera lista para presentar ante el Ministerio del Trabajo
- Regla de citas: usa números de artículo y nombres de ley ÚNICAMENTE cuando aparezcan en los fragmentos de biblioteca adjuntos; si no aparecen, omite el número pero redacta el contenido completo
- Si alguna información no fue proporcionada, usa valores razonables y típicos para una empresa colombiana
- NO incluyas comentarios ni aclaraciones fuera del texto del reglamento
- NUNCA uses corchetes ni placeholders ([DÍA], [MES], [AÑO], [NOMBRE], [NIT], etc.); usa siempre los datos reales
- La fecha de elaboración es: {$fechaHoy}
- El representante legal firmante es: {$representante}

INSTRUCCIONES DE FORMATO — CRÍTICAS, INCUMPLIRLAS INVALIDA EL DOCUMENTO:
1. CADA artículo es un párrafo independiente de mínimo 60 palabras. NUNCA un resumen de una línea.
2. NUNCA colapses varios artículos en una sola línea. Ejemplo PROHIBIDO: "Artículo 5-8: SST..."
3. NUNCA uses guiones (-), asteriscos (*), almohadillas (#) ni viñetas al inicio de línea.
4. El título del capítulo va en línea propia en MAYÚSCULAS: CAPÍTULO I — DENOMINACIÓN, DOMICILIO Y OBJETO
5. Cada artículo comienza en línea propia así: ARTÍCULO 1. NOMBRE DEL ARTÍCULO. Seguido del texto completo en el mismo párrafo.
6. Para listas dentro de un artículo usa numeración interna: "1) ... 2) ... 3) ..." integrada en el párrafo.
7. Sin Markdown: sin asteriscos, sin # ni **.

EJEMPLO DE FORMATO CORRECTO (sigue este modelo exactamente):
CAPÍTULO II — ADMISIÓN Y PERÍODO DE PRUEBA

ARTÍCULO 4. REQUISITOS DE INGRESO. Para ingresar como trabajador de {$razonSocial} se requerirá la presentación de hoja de vida con soportes, fotocopia del documento de identidad, certificados de estudios y experiencia laboral, certificado de antecedentes judiciales y disciplinarios, y los demás documentos que la empresa estime pertinentes conforme a la naturaleza del cargo. Queda expresamente prohibido solicitar prueba de embarazo o estado de gravidez como requisito de ingreso, así como cualquier otra condición que configure discriminación en el proceso de selección.

ARTÍCULO 5. PERÍODO DE PRUEBA. El período de prueba deberá pactarse siempre por escrito como cláusula expresa del contrato de trabajo. En contratos a término indefinido, el período de prueba no podrá exceder de dos (2) meses. En contratos a término fijo, el período de prueba no podrá exceder de la quinta parte del término pactado, sin que pueda exceder de dos (2) meses. Durante el período de prueba cualquiera de las partes podrá dar por terminado el contrato en cualquier momento, sin previo aviso y sin indemnización, pero la terminación debe ser fundamentada y comunicada por escrito.

CAPÍTULOS OBLIGATORIOS — redacta CADA artículo como párrafo completo, no como resumen:

CAPÍTULO I — DENOMINACIÓN, DOMICILIO Y OBJETO
Artículos requeridos: ámbito de aplicación del reglamento, denominación y NIT de la empresa, domicilio principal y sucursales, actividad económica, representante legal y su facultad para sancionar.

CAPÍTULO II — ADMISIÓN Y PERÍODO DE PRUEBA
Artículos requeridos: documentos exigidos para ingreso (hoja de vida, fotocopia del documento de identidad, certificados de estudio y experiencia; el certificado de antecedentes judiciales solo es exigible cuando el cargo lo requiera por razones de seguridad, y NO podrá usarse como criterio automático de exclusión); período de prueba estipulado siempre por escrito — máximo 2 meses en indefinidos y proporcional al plazo en fijos; prórroga del período de prueba solo por acuerdo escrito dentro del plazo original; prohibición expresa de discriminación en selección.
ARTÍCULO OBLIGATORIO VERBATIM — incluir esta regla exacta: "El período de prueba deberá pactarse siempre por escrito como cláusula expresa del contrato de trabajo. La terminación durante el período de prueba debe comunicarse con fundamentación y por escrito."
ARTÍCULO OBLIGATORIO VERBATIM sobre prohibiciones de ingreso — incluir con esta redacción exacta: "Queda expresamente prohibido exigir como requisito de ingreso la presentación de la libreta militar, certificados o pruebas de gravidez o estado de embarazo, prueba de VIH/SIDA, ni ningún otro examen o documento que pueda constituir discriminación en el proceso de selección, de conformidad con el artículo 77 del Decreto 2663 de 1950, la Ley 972 de 2005 y la Ley 1010 de 2006."

CAPÍTULO III — JORNADA ORDINARIA DE TRABAJO
Artículos requeridos:
A) Jornada máxima semanal: 47h con reducción progresiva a 42h (Ley 2101/2021); definición de trabajo diurno (06:00-21:00) y nocturno (21:00-06:00); distribución de la jornada diaria (cómo se dividen las horas a lo largo del día, incluido el descanso para almuerzo).
B) Horario específico de la empresa: indicar el horario exacto de entrada y salida según los datos del cuestionario.
C) DESCANSO DOMINICAL OBLIGATORIO — artículo independiente con este contenido mínimo: "Todo trabajador tiene derecho a un descanso remunerado que comprende el domingo de cada semana, de conformidad con el artículo 181 del Código Sustantivo del Trabajo. Este descanso será remunerado con el salario ordinario de un día de trabajo." (Art. 181 CST)
D) DESCANSO COMPENSATORIO — artículo independiente: cuando por razón del trabajo se labore el día de descanso obligatorio, el trabajador tendrá derecho a un descanso compensatorio remunerado en la semana siguiente, sin perjuicio del recargo del 75% sobre el valor del trabajo en domingo o festivo (Art. 182 CST).
Si la empresa opera en múltiples turnos: artículo específico para cada turno con nombre, horario exacto y cargos. Si opera 24/7, artículo de operación continua con designación de turnos.
Si existen cargos de dirección, manejo o confianza (Art. 162 CST): artículo expreso indicando que dichos cargos quedan excluidos del límite de jornada máxima, sin que esto les prive del descanso dominical remunerado.

CAPÍTULO IV — TRABAJO SUPLEMENTARIO, DOMINICALES Y FESTIVOS
Artículos requeridos:
A) Límite horas extras — VERBATIM OBLIGATORIO: "El trabajo suplementario o de horas extras no podrá exceder de dos (2) horas diarias ni de doce (12) horas semanales, de conformidad con el artículo 167A del Decreto 2663 de 1950 (Código Sustantivo del Trabajo)." Autorización previa y escrita del empleador; horas extras no autorizadas no generan pago.
B) Recargos exactos: hora extra diurna 25% sobre el ordinario; hora extra nocturna 75%; trabajo en dominical o festivo 75%; recargo nocturno ordinario 35% (trabajo entre 21:00-06:00 no en jornada ordinaria).
C) Si la empresa opera en turnos nocturnos regulares: artículo expreso sobre recargo nocturno del 35% para quienes tienen jornada ordinaria nocturna.
D) Registro individual del trabajo suplementario por trabajador, firmado por ambas partes.

CAPÍTULO V — REMUNERACIÓN Y FORMA DE PAGO
Artículos requeridos:
A) Modalidades de salario: por unidad de tiempo, por obra o tarea, variable; el salario integral (cuando supere 10 SMMLV incluye prestaciones) si aplica a algún cargo de la empresa.
B) Período de pago: jornales (trabajo diario u obra) se pagan semanal o quincenalmente; sueldos (contrato a tiempo) se pagan mensualmente; periodicidad específica de la empresa según los datos del cuestionario.
C) Forma de pago: modalidad indicada en el cuestionario (transferencia, efectivo, cheque o mixto).
D) Prohibición de trueque — VERBATIM OBLIGATORIO: "Queda absolutamente prohibido pagar el salario con fichas, vales, mercancías, bonos o cualquier otro signo representativo, así como con bebidas alcohólicas, estupefacientes o sustancias alucinógenas." (Art. 134 y 136 CST)
E) Salario en especie: máximo el 50% del salario total (Art. 129 CST); para trabajadores que devenguen el salario mínimo mensual legal vigente, el pago en especie no podrá exceder el 30% de ese salario; debe pactarse por escrito; los alimentos, habitación y vestido de trabajo no se consideran salario en especie cuando son ocasionales o para el desempeño del cargo.
F) Propinas — VERBATIM OBLIGATORIO: "Las propinas que reciban los trabajadores no constituyen salario y no se pueden pactar como tal. En consecuencia, no se computarán en el salario para ningún efecto legal." (Art. 131 CST)
G) Comprobante de pago discriminado que detalle devengados y descuentos.

CAPÍTULO VI — VACACIONES Y PERMISOS
Artículos requeridos:
ARTÍCULO OBLIGATORIO VERBATIM — incluir esta frase exacta: "Todo trabajador tiene derecho a quince (15) días hábiles consecutivos de vacaciones remuneradas por cada año de servicio, de conformidad con el artículo 186 del Código Sustantivo del Trabajo."
Adicionalmente: período de disfrute acordado entre partes con aviso previo de 15 días; la empresa llevará un registro especial de vacaciones con nombre del trabajador, fecha de salida, fecha de retorno y saldo acumulado (Art. 187 CST); acumulación hasta 4 años por acuerdo escrito entre las partes; interrupción justificada — cuando durante el disfrute de las vacaciones sobrevenga una causa justificada (incapacidad médica, calamidad doméstica), el trabajador tendrá derecho a reanudarlas tan pronto desaparezca la causa de interrupción; compensación en dinero — la empresa podrá, por acuerdo escrito con el trabajador, compensar en dinero hasta la mitad de las vacaciones, siempre que el trabajador devenga más de un (1) salario mínimo mensual legal vigente (Art. 189 CST); permisos remunerados (calamidad doméstica, sufragio, diligencias personales con aviso previo).

CAPÍTULO VII — LICENCIAS ESPECIALES
Artículos requeridos: licencia de maternidad 18 semanas remuneradas (Ley 2114/2021); licencia de paternidad 2 semanas remuneradas (Ley 2114/2021); licencia de luto 5 días hábiles por cónyuge, compañero permanente o familiar hasta segundo grado de consanguinidad (Ley 1280/2009); licencia por calamidad doméstica grave; licencias no remuneradas.

CAPÍTULO VIII — RÉGIMEN DISCIPLINARIO: CLASIFICACIÓN DE FALTAS
Artículos requeridos: definición de falta disciplinaria; catálogo completo de faltas LEVES con ejemplos concretos de la empresa; catálogo completo de faltas GRAVES con ejemplos concretos; catálogo de faltas MUY GRAVES con ejemplos concretos.
Procedimiento garantista — artículo obligatorio con todos estos pasos: 1) comunicación escrita de los cargos al trabajador; 2) traslado de las pruebas que obran en su contra; 3) plazo mínimo de 5 días hábiles para que el trabajador presente sus descargos por escrito; 4) audiencia de descargos en la que el trabajador puede estar acompañado de un representante sindical o de la persona de su confianza; 5) fallo motivado por escrito comunicado al trabajador; 6) notificación al trabajador de los recursos que proceden contra el fallo, incluyendo el recurso de apelación ante el superior jerárquico o el cargo designado para resolver en segunda instancia, con término de 5 días hábiles para interponerlo. (Art. 115 CST)

CAPÍTULO IX — ESCALA DE SANCIONES
Artículos requeridos — la escala debe ser estrictamente proporcional a la gravedad:
A) Faltas LEVES: amonestación verbal primera vez; amonestación escrita en reincidencia; multa máximo 1/5 del salario diario (destinada a premios para trabajadores, no a la empresa). PROHIBIDO aplicar suspensión como sanción por falta leve.
B) Faltas GRAVES: suspensión sin remuneración de 1 a 8 días calendario la primera vez (Art. 112 CST).
C) Faltas MUY GRAVES / reincidencia en graves: suspensión hasta 2 meses o terminación con justa causa (Art. 62 CST numerales aplicables).
D) Garantía del debido proceso y proporcionalidad en toda sanción; derecho del trabajador a impugnar la sanción impuesta ante el Ministerio del Trabajo.

CAPÍTULO X — RECLAMOS Y PROCEDIMIENTOS
Artículos requeridos: instancias internas para presentar reclamos; plazos de respuesta máximo 15 días hábiles; procedimiento cuando el reclamo involucra al superior jerárquico; acceso a Ministerio del Trabajo o jurisdicción laboral cuando no hay acuerdo.

CAPÍTULO XI — NORMAS DE CONDUCTA Y COMPORTAMIENTO
Artículos requeridos: obligaciones especiales del trabajador (puntualidad, cuidado de bienes, respeto, confidencialidad, obediencia razonable); obligaciones del empleador (instrumentos, seguridad, pago oportuno, respeto a la dignidad); prohibiciones del trabajador (sustracción de bienes, actividades personales en jornada, consumo de alcohol/sustancias, proselitismo, uso ilícito de recursos); política de uso de celulares/dispositivos personales en jornada; política de confidencialidad de información empresarial.

CAPÍTULO XII — SEGURIDAD Y SALUD EN EL TRABAJO (SG-SST)
ARTÍCULOS OBLIGATORIOS — cada uno como párrafo completo de mínimo 60 palabras:
A) Política de SST: compromiso de la alta dirección, recursos asignados, ámbito de aplicación
B) Obligaciones del empleador en SST: afiliar a ARL, proveer EPP, garantizar condiciones seguras, realizar exámenes médicos ocupacionales de ingreso/periódicos/egreso, investigar accidentes y enfermedades laborales
C) Obligaciones del trabajador en SST: usar correctamente el EPP, reportar condiciones inseguras, asistir a capacitaciones, no manipular equipos de seguridad sin autorización
D) Vigía de SST (empresas con menos de 10 trabajadores) o COPASST (10 o más): designación, período de 2 años, reunión mensual, funciones de vigilancia
E) Exámenes médicos ocupacionales: ingreso, periódicos y egreso; obligatorios; incluir exámenes complementarios de alcoholemia y detección de sustancias psicoactivas para trabajadores en cargos que impliquen manejo de maquinaria, conducción de vehículos, trabajo en alturas o cualquier riesgo para terceros; reserva absoluta de la información médica.
F) Reporte de accidentes: el trabajador notifica al empleador el mismo día; la empresa notifica a la ARL dentro de los 2 días hábiles siguientes; investigación interna obligatoria.
G) EPP: uso obligatorio según matriz de riesgos del cargo; incumplimiento = falta disciplinaria grave.
H) Prohibición para trabajadores en cargos de riesgo: artículo expreso que prohíbe a los trabajadores que ocupen cargos que impliquen riesgo para terceros (conductores, operadores de maquinaria, trabajo en alturas, vigilantes) presentarse al trabajo o permanecer en él bajo efectos de alcohol, sustancias psicoactivas, estupefacientes o medicamentos que alteren el estado de alerta; violación = falta muy grave con terminación justificada. (Decreto 1069/2015 Art. 2.2.2.2.8.1)

CAPÍTULO XIII — USO DE EQUIPOS, UNIFORMES Y BIENES DE LA EMPRESA
Artículos requeridos: asignación formal de equipos con acta; responsabilidad del trabajador por daño causado por negligencia o mal uso; política de uniformes (si aplica) o presentación personal; devolución formal de todos los bienes al terminar el contrato.

CAPÍTULO XIV — COMITÉ DE CONVIVENCIA LABORAL Y PREVENCIÓN DE ACOSO
ARTÍCULOS OBLIGATORIOS — cada uno como párrafo completo de mínimo 60 palabras:
A) Definición y modalidades de acoso laboral: persecución, discriminación, entorpecimiento, inequidad y desprotección (Ley 1010/2006, Art. 2). Definir cada modalidad con ejemplo.
B) Comité de Convivencia Laboral — VERBATIM OBLIGATORIO incluir estas dos frases exactas:
   FRASE 1: "El comité estará conformado de manera bipartita por dos (2) representantes del empleador y dos (2) representantes de los trabajadores, para la adopción de medidas de prevención y corrección del acoso laboral, de conformidad con la Resolución 734 de 2006 y la Resolución 652 de 2012."
   FRASE 2 (PARÁGRAFO OBLIGATORIO): "Las personas que hayan sido víctimas o victimarios de conductas de acoso laboral no podrán integrar el Comité de Convivencia Laboral. Para empresas con menos de veinte (20) trabajadores, el Comité se conformará con un (1) representante del empleador y un (1) representante de los trabajadores."
   Elección democrática de representantes de los trabajadores, período de 2 años, reunión mensual ordinaria y extraordinaria cuando se presente un caso.
C) Funciones del Comité: recibir quejas, examinar conductas, facilitar diálogo entre las partes, formular recomendaciones, hacer seguimiento, informar a la dirección.
D) Procedimiento interno de queja por acoso laboral — artículo con pasos numerados: 1) presentación escrita al Comité; 2) aviso al presunto acosador en máx 5 días; 3) investigación confidencial en máx 30 días; 4) audiencia de conciliación; 5) informe final con medidas correctivas concretas y plazos; 6) seguimiento trimestral.
E) POLÍTICA DE PREVENCIÓN DEL ACOSO SEXUAL — LEY 2365 DE 2024 — ARTÍCULO AUTÓNOMO CON ESTE TÍTULO EXACTO. Contenido mínimo: definición legal de acoso sexual en el trabajo; conductas que lo constituyen (solicitudes de favores sexuales, comentarios, contacto físico no deseado, exhibicionismo, acoso digital); canal confidencial exclusivo para denuncias de acoso sexual; protocolo de atención con plazos máximos; garantía de confidencialidad de la víctima; prohibición expresa de represalias contra quien denuncie; obligación del empleador de investigar dentro de los 5 días hábiles siguientes a la denuncia.
F) Sanciones por acoso laboral o sexual: falta muy grave con terminación con justa causa, denuncia ante Inspector del Trabajo, acciones penales según gravedad (Art. 210A Código Penal para acoso sexual).

CAPÍTULO XV — PROTECCIÓN DE SUJETOS DE ESPECIAL PROTECCIÓN
Artículos requeridos:
A) Mujer embarazada y en período de lactancia: prohibición de despido sin autorización previa del Inspector del Trabajo (Art. 241A CST); licencia de maternidad de 18 semanas remuneradas (Ley 2114/2021); prohibición expresa de solicitar prueba de embarazo, examen de VIH/SIDA o hacer preguntas sobre estado de gravidez en entrevistas de trabajo o durante la relación laboral (Ley 972/2005, Art. 236 CST).
B) Licencia de paternidad: dos (2) semanas remuneradas pagadas por el empleador al padre trabajador, de conformidad con la Ley 2114 de 2021, prorrogables según el número de hijos.
C) Personas en situación de discapacidad: estabilidad laboral reforzada — prohibición de despido sin autorización del Ministerio del Trabajo (Sentencia T-306/2024 y T-427/1992 CSJ); deber de realizar ajustes razonables en el puesto de trabajo.
D) Trabajadores con fuero sindical (fundadores, adherentes, directivos): prohibición de despido, traslado o desmejora sin autorización judicial previa (Art. 405-411 CST); el desconocimiento del fuero genera la obligación de reintegro y pago de salarios dejados de percibir.
E) No discriminación: prohibición absoluta de discriminación por raza, color, sexo, edad, idioma, religión, opinión política, orientación sexual o identidad de género, origen nacional o social, posición económica o cualquier otra condición. (Art. 143 CST, Ley 1482/2011)

CAPÍTULO XVI — DISPOSICIONES FINALES
Artículos requeridos: vigencia desde la publicación a los trabajadores; procedimiento para modificaciones (comunicación a trabajadores y depósito ante Ministerio del Trabajo); obligación de publicar en lugar visible y entregar copia a cada trabajador; depósito ante la Dirección Territorial del Ministerio del Trabajo competente; incorporación del RIT a todos los contratos individuales de trabajo.
{$seccionBiblioteca}
INFORMACIÓN DE LA EMPRESA PROPORCIONADA POR EL ADMINISTRADOR:
{$infoEmpresa}

VERIFICACIÓN OBLIGATORIA ANTES DE TERMINAR — revisa CADA punto:
1. CAP. II: ¿incluye prohibición de exigir libreta militar y prueba de embarazo con referencia al Art. 77 del Decreto 2663 de 1950? ¿incluye prórroga del período de prueba?
2. CAP. III: ¿tiene ARTÍCULO INDEPENDIENTE de descanso dominical remunerado (Art. 181 CST)? ¿tiene ARTÍCULO INDEPENDIENTE de descanso compensatorio (Art. 182 CST)? ¿describe la distribución de la jornada diaria?
3. CAP. IV: ¿contiene la frase literal "no podrá exceder de dos (2) horas diarias ni de doce (12) horas semanales" con referencia al "artículo 167A del Decreto 2663 de 1950"? ¿especifica los recargos exactos (25%, 75%)?
4. CAP. V: ¿contiene la prohibición de pago con fichas, mercancías o víveres? ¿menciona el salario integral?
5. CAP. VI: ¿contiene la frase "quince (15) días hábiles consecutivos de vacaciones remuneradas"? ¿menciona el registro especial de vacaciones?
6. CAP. VIII: ¿el procedimiento disciplinario incluye el derecho del trabajador a estar acompañado de un representante sindical o persona de su confianza en la audiencia de descargos?
7. CAP. IX: ¿la escala diferencia claramente entre faltas leves (multa, no suspensión), graves (suspensión 1-8 días) y muy graves (hasta 2 meses o terminación)?
8. CAP. XII: ¿incluye exámenes de alcoholemia/sustancias para cargos de riesgo? ¿tiene artículo de prohibición expresa para trabajadores en cargos de riesgo para terceros?
9. CAP. XIV B: ¿contiene la frase "comité conformado de manera bipartita por dos (2) representantes del empleador y dos (2) representantes de los trabajadores" y referencia a la "Resolución 734 de 2006"?
10. CAP. XIV E: ¿tiene un ARTÍCULO AUTÓNOMO titulado "POLÍTICA DE PREVENCIÓN DEL ACOSO SEXUAL — LEY 2365 DE 2024" con canal de denuncia y protocolo de atención separado del artículo del Comité?
Si cualquiera de estos puntos está ausente, REDÁCTALO AHORA antes de finalizar.

Redacta el Reglamento Interno de Trabajo completo:
PROMPT;
    }
}
