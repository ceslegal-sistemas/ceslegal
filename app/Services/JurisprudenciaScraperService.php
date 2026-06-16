<?php

namespace App\Services;

use App\Models\Jurisprudencia;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Importa jurisprudencia de la Corte Constitucional como unidad discreta y curada.
 *
 * No se scrapea el buscador (SPA AJAX): se descarga la PÁGINA ESTÁTICA de cada
 * sentencia (URL predecible /relatoria/{año}/{T|C|SU|A}-{num}-{aa}.htm), se extrae
 * el texto completo y luego la IA redacta un EXTRACTO (tema + tesis/sub-regla) que
 * es lo que se indexa (embedding) y la IA usa entero. El texto completo se conserva
 * como fuente de verdad. La sentencia queda inactiva hasta que el equipo la cure.
 */
class JurisprudenciaScraperService
{
    private const BASE = 'https://www.corteconstitucional.gov.co/relatoria';

    private const UA = 'Mozilla/5.0 (compatible; CESLegalBot/1.0; +https://ceslegal.com)';

    /**
     * @return array{prefijo:string, numero:string, anio:int, referencia:string}
     */
    public function parsearReferencia(string $ref): array
    {
        $ref = strtoupper(trim($ref));
        if (! preg_match('/\b(SU|T|C|A)\s*[-\s]?\s*(\d{1,4})\s*(?:\/|-|\s+DE\s+|\s+)\s*(\d{2,4})\b/u', $ref, $m)) {
            throw new \InvalidArgumentException("No pude entender la referencia \"{$ref}\". Usa T-1040/2006, C-200/2019, etc.");
        }

        $prefijo = $m[1];
        // La Corte usa el número con ceros a la izquierda (mín. 3 dígitos): SU-070, C-079, T-025.
        $numero  = str_pad(ltrim($m[2], '0') ?: '0', 3, '0', STR_PAD_LEFT);
        $anio    = (int) $m[3];
        if ($anio < 100) {
            $anio += ($anio >= 92) ? 1900 : 2000;
        }

        return [
            'prefijo'    => $prefijo,
            'numero'     => $numero,
            'anio'       => $anio,
            'referencia' => "{$prefijo}-{$numero}/{$anio}",
        ];
    }

    public function urlSentencia(array $p): string
    {
        $aa = substr((string) $p['anio'], 2, 2);
        return self::BASE . "/{$p['anio']}/{$p['prefijo']}-{$p['numero']}-{$aa}.htm";
    }

    /**
     * Importa una sentencia: descarga, crea la fila, genera extracto + embedding.
     * Si ya existe (misma referencia), la retorna sin reprocesar.
     */
    public function importar(string $referencia): Jurisprudencia
    {
        $p   = $this->parsearReferencia($referencia);
        $ref = $p['referencia'];

        $existente = Jurisprudencia::where('referencia', $ref)->first();
        if ($existente) {
            return $existente;
        }

        $texto = $this->descargarConVariantes($p);

        $jur = Jurisprudencia::create([
            'referencia'     => $ref,
            'tipo'           => $p['prefijo'] === 'A' ? 'auto_cc' : 'sentencia_cc',
            'corporacion'    => 'Corte Constitucional',
            'texto_completo' => $texto,
            'total_palabras' => str_word_count($texto),
            'estado'         => 'procesando',
            'activo'         => false,
        ]);

        $this->procesar($jur);

        return $jur->refresh();
    }

    /** Genera el extracto (IA) y su embedding para una sentencia ya descargada. */
    public function procesar(Jurisprudencia $jur): void
    {
        $jur->update(['estado' => 'procesando', 'error_mensaje' => null]);

        try {
            $extracto = $this->generarExtracto($jur->texto_completo, $jur->referencia);

            $jur->fill([
                'tema'       => $extracto['tema'] ?? null,
                'tesis'      => $extracto['tesis'] ?? null,
                'fecha'      => $extracto['fecha'] ?? null,
                'magistrado' => $extracto['magistrado'] ?? null,
            ]);

            if (empty($jur->tesis)) {
                throw new \RuntimeException('La IA no devolvió una tesis utilizable.');
            }

            $embedding = app(BibliotecaLegalService::class)->embedDocumento($jur->textoIndexable());
            if (empty($embedding)) {
                throw new \RuntimeException('No se pudo generar el embedding del extracto.');
            }

            $jur->embedding = $embedding;
            $jur->estado    = 'procesado';
            $jur->error_mensaje = null;
            $jur->save();

            Log::info('Jurisprudencia procesada', ['referencia' => $jur->referencia, 'tema' => $jur->tema]);

        } catch (\Throwable $e) {
            $jur->update(['estado' => 'error', 'error_mensaje' => $e->getMessage()]);
            Log::warning('Jurisprudencia: error procesando', ['referencia' => $jur->referencia, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /** Pide a la IA el extracto relevante (tema + tesis + metadatos) en JSON. */
    public function generarExtracto(string $textoCompleto, string $referencia): array
    {
        // El núcleo doctrinal suele estar en consideraciones/decisión; enviamos un
        // bloque amplio pero acotado para no inflar el prompt.
        $entrada = mb_substr($textoCompleto, 0, 28000);

        // Formato con etiquetas (no JSON): la tesis es texto libre y puede traer
        // comillas y saltos de línea que romperían un JSON. Parsear etiquetas es robusto.
        $prompt = <<<PROMPT
Eres relator de la Corte Constitucional de Colombia. A partir del texto de la sentencia {$referencia}, extrae SOLO lo relevante para procesos disciplinarios laborales (estabilidad laboral reforzada, fuero, debido proceso disciplinario, justa causa de terminación, proporcionalidad, acoso laboral, etc.).

Responde EXACTAMENTE en este formato de TEXTO PLANO (sin markdown, sin JSON), con estas cuatro etiquetas y en este orden. TESIS va de última y puede ocupar varias líneas:
TEMA: <descriptor del tema, una sola línea>
FECHA: <fecha de la sentencia en formato AAAA-MM-DD, o NULL>
MAGISTRADO: <magistrado ponente, o NULL>
TESIS: <la regla o sub-regla y la ratio decidendi relevante para lo laboral disciplinario, en lenguaje claro y autocontenido, máximo 250 palabras. No cites números de OTRAS sentencias.>

TEXTO DE LA SENTENCIA:
{$entrada}
PROMPT;

        $salida = $this->llamarGemini($prompt);

        $tesis = '';
        if (preg_match('/TESIS\s*:\s*(.+)$/is', $salida, $m)) {
            $tesis = trim($m[1]);
        }
        if ($tesis === '') {
            throw new \RuntimeException('La IA no devolvió una TESIS utilizable.');
        }

        $nullable = fn(?string $v) => ($v === null || $v === '' || strtoupper($v) === 'NULL') ? null : $v;

        $fecha = $this->capturarEtiqueta($salida, 'FECHA');
        if ($fecha) {
            try { $fecha = \Carbon\Carbon::parse($fecha)->toDateString(); }
            catch (\Throwable $e) { $fecha = null; }
        }

        return [
            'tema'       => $nullable($this->capturarEtiqueta($salida, 'TEMA')),
            'tesis'      => $tesis,
            'fecha'      => $fecha,
            'magistrado' => $nullable($this->capturarEtiqueta($salida, 'MAGISTRADO')),
        ];
    }

    /** Captura el valor (una línea) de una etiqueta tipo "ETIQUETA: valor". */
    private function capturarEtiqueta(string $texto, string $etiqueta): ?string
    {
        if (preg_match('/^\s*' . preg_quote($etiqueta, '/') . '\s*:\s*(.+)$/im', $texto, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    /** Prueba variantes del número (con/sin ceros) hasta encontrar la sentencia. */
    private function descargarConVariantes(array $p): string
    {
        $aa = substr((string) $p['anio'], 2, 2);
        $numeros = array_values(array_unique([
            $p['numero'],                                                   // 3 dígitos (canónico)
            ltrim($p['numero'], '0') ?: $p['numero'],                       // sin ceros
            str_pad(ltrim($p['numero'], '0') ?: '0', 4, '0', STR_PAD_LEFT), // 4 dígitos
        ]));

        // Formatos de archivo: con guion (T-1040-06) y sin guion (SU070-13).
        $archivos = [];
        foreach ($numeros as $num) {
            $archivos[] = "{$p['prefijo']}-{$num}-{$aa}.htm";
            $archivos[] = "{$p['prefijo']}{$num}-{$aa}.htm";
        }

        $ultimo = null;
        foreach (array_unique($archivos) as $archivo) {
            $url = self::BASE . "/{$p['anio']}/{$archivo}";
            try {
                $texto = $this->descargarTexto($url);
                // Descartar el cascarón del SPA (solo "CORTE CONSTITUCIONAL DE COLOMBIA").
                if (mb_strlen($texto) >= 1500) {
                    return $texto;
                }
            } catch (\Throwable $e) {
                $ultimo = $e;
            }
        }

        throw $ultimo ?? new \RuntimeException("La sentencia {$p['referencia']} no se encontró.");
    }

    /** Descarga la página de la sentencia y devuelve texto plano UTF-8. */
    public function descargarTexto(string $url): string
    {
        $resp = Http::withHeaders(['User-Agent' => self::UA])->timeout(60)->retry(2, 1500)->get($url);

        if ($resp->status() === 404) {
            throw new \RuntimeException("No existe la página de la sentencia (404): {$url}");
        }
        if (! $resp->successful()) {
            throw new \RuntimeException("Error {$resp->status()} al descargar {$url}");
        }

        return $this->htmlATexto($resp->body());
    }

    public function htmlATexto(string $html): string
    {
        $enc = mb_detect_encoding($html, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true) ?: 'ISO-8859-1';
        if ($enc !== 'UTF-8') {
            $html = mb_convert_encoding($html, 'UTF-8', $enc);
        }

        $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $html);
        $html = preg_replace('/<!--.*?-->/s', ' ', $html);
        $html = preg_replace('#<(br|/p|/div|/tr|/li|/h[1-6])\s*/?>#i', "\n", $html);

        $texto = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $texto = preg_replace('/[ \t\x{00A0}]+/u', ' ', $texto);
        $texto = preg_replace('/\n[ \t]*\n+/', "\n\n", $texto);

        return trim($texto);
    }

    /** Llamada simple a Gemini para generar texto (extracto). */
    private function llamarGemini(string $prompt): string
    {
        $config = config('services.ia.gemini', []);
        $apiKey = $config['api_key'] ?? null;
        if (empty($apiKey)) {
            throw new \RuntimeException('Falta la API key de Gemini.');
        }

        $modelos = array_values(array_unique(array_filter([
            $config['model'] ?? 'gemini-2.5-flash',
            'gemini-2.5-flash',
            'gemini-2.5-flash-lite',
        ])));

        $payload = [
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => ['temperature' => 0.2, 'maxOutputTokens' => 2048, 'topP' => 0.95],
        ];

        $resp = null;
        foreach ($modelos as $modelo) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelo}:generateContent?key={$apiKey}";
            try {
                $resp = Http::withHeaders(['Content-Type' => 'application/json'])->timeout(90)->post($url, $payload);
            } catch (\Throwable $e) {
                $resp = null;
                continue;
            }
            if (in_array($resp->status(), [503, 404, 429], true)) {
                continue;
            }
            break;
        }

        if (! $resp || ! $resp->successful()) {
            throw new \RuntimeException('Error en la API Gemini al generar el extracto.');
        }

        $text = $resp->json('candidates.0.content.parts.0.text');
        if (empty($text)) {
            throw new \RuntimeException('Gemini no devolvió contenido para el extracto.');
        }

        return $text;
    }
}
