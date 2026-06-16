<?php

namespace App\Services;

use App\Models\DocumentoLegal;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Importa jurisprudencia de la Corte Constitucional a la biblioteca legal.
 *
 * No se scrapea el buscador (es una SPA AJAX). En su lugar se descarga la
 * PÁGINA ESTÁTICA de cada sentencia, cuya URL es predecible:
 *   https://www.corteconstitucional.gov.co/relatoria/{AÑO}/{T|C|SU|A}-{NUMERO}-{AA}.htm
 *
 * Enfoque curado: el equipo jurídico indica el número de sentencia (p. ej.
 * "T-1040/2006") y el sistema baja el texto, lo limpia y lo procesa con
 * embeddings reutilizando BibliotecaLegalService.
 */
class JurisprudenciaScraperService
{
    private const BASE = 'https://www.corteconstitucional.gov.co/relatoria';

    private const UA = 'Mozilla/5.0 (compatible; CESLegalBot/1.0; +https://ceslegal.com)';

    /**
     * Normaliza y separa una referencia como "T-1040/2006", "T-1040 de 2006",
     * "c-200/19", "SU-049-2017" en sus partes.
     *
     * @return array{prefijo:string, numero:string, anio:int, referencia:string}
     */
    public function parsearReferencia(string $ref): array
    {
        $ref = strtoupper(trim($ref));
        // Captura: prefijo (T, C, SU, A), número, año (2 o 4 dígitos)
        if (! preg_match('/\b(SU|T|C|A)\s*[-\s]?\s*(\d{1,4})\s*(?:\/|-|\s+DE\s+|\s+)\s*(\d{2,4})\b/u', $ref, $m)) {
            throw new \InvalidArgumentException("No pude entender la referencia \"{$ref}\". Usa un formato como T-1040/2006 o C-200/2019.");
        }

        $prefijo = $m[1];
        $numero  = ltrim($m[2], '0') ?: $m[2];
        $anio    = (int) $m[3];
        if ($anio < 100) {
            // Año de 2 dígitos: 92-99 -> 1900s, resto -> 2000s
            $anio += ($anio >= 92) ? 1900 : 2000;
        }

        return [
            'prefijo'    => $prefijo,
            'numero'     => $numero,
            'anio'       => $anio,
            'referencia' => "{$prefijo}-{$numero}/{$anio}",
        ];
    }

    /** Construye la URL de la página estática de la sentencia. */
    public function urlSentencia(array $p): string
    {
        $aa = substr((string) $p['anio'], 2, 2);
        return self::BASE . "/{$p['anio']}/{$p['prefijo']}-{$p['numero']}-{$aa}.htm";
    }

    /**
     * Importa una sentencia por su referencia. Devuelve el DocumentoLegal.
     * Si ya existe (misma referencia), lo retorna sin volver a descargar.
     */
    public function importar(string $referencia): DocumentoLegal
    {
        $p   = $this->parsearReferencia($referencia);
        $ref = $p['referencia'];

        $existente = DocumentoLegal::where('referencia', $ref)->first();
        if ($existente) {
            return $existente;
        }

        $url   = $this->urlSentencia($p);
        $texto = $this->descargarTexto($url);

        if (mb_strlen($texto) < 800) {
            throw new \RuntimeException("La sentencia {$ref} no se encontró o vino vacía en {$url}.");
        }

        $documento = DocumentoLegal::create([
            'titulo'      => "Sentencia {$ref} — Corte Constitucional",
            'tipo'        => $p['prefijo'] === 'A' ? 'otro' : 'sentencia_cc',
            'referencia'  => $ref,
            'descripcion' => mb_substr($texto, 0, 280),
            'estado'      => 'pendiente',
            'activo'      => true,
        ]);

        app(BibliotecaLegalService::class)->procesarTexto($documento, $texto);

        return $documento->refresh();
    }

    /** Descarga la página de la sentencia y devuelve texto plano (UTF-8). */
    public function descargarTexto(string $url): string
    {
        $resp = Http::withHeaders(['User-Agent' => self::UA])
            ->timeout(60)
            ->retry(2, 1500)
            ->get($url);

        if ($resp->status() === 404) {
            throw new \RuntimeException("No existe la página de la sentencia (404): {$url}");
        }
        if (! $resp->successful()) {
            throw new \RuntimeException("Error {$resp->status()} al descargar {$url}");
        }

        return $this->htmlATexto($resp->body());
    }

    /** Convierte el HTML (posible ISO-8859-1) a texto plano UTF-8 limpio. */
    public function htmlATexto(string $html): string
    {
        $enc = mb_detect_encoding($html, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true) ?: 'ISO-8859-1';
        if ($enc !== 'UTF-8') {
            $html = mb_convert_encoding($html, 'UTF-8', $enc);
        }

        // Quitar scripts, estilos y comentarios
        $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $html);
        $html = preg_replace('/<!--.*?-->/s', ' ', $html);
        // Saltos de línea para bloques
        $html = preg_replace('#<(br|/p|/div|/tr|/li|/h[1-6])\s*/?>#i', "\n", $html);

        $texto = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $texto = preg_replace('/[ \t\x{00A0}]+/u', ' ', $texto);
        $texto = preg_replace('/\n[ \t]*\n+/', "\n\n", $texto);

        return trim($texto);
    }
}
