<?php

namespace App\Services\Scrapers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Descarga sentencias de la Corte Constitucional de Colombia
 * usando la API oficial de Datos Abiertos (Socrata):
 * https://www.datos.gov.co/resource/v2k4-2t8s.json
 *
 * Filtra tipos relevantes para derecho laboral disciplinario:
 * T (tutela), C (constitucionalidad), SU (unificación)
 */
class CorteConstitucionalScraper
{
    const SOCRATA_API   = 'https://www.datos.gov.co/resource/v2k4-2t8s.json';
    const RELATORIA_URL = 'https://www.corteconstitucional.gov.co/relatoria';

    // Tipos de sentencia relevantes para derecho laboral
    const TIPOS_RELEVANTES = ['T', 'C', 'SU', 'C-'];

    // Palabras clave para filtrar sentencias relevantes
    const PALABRAS_CLAVE = [
        'proceso disciplinario',
        'disciplinario',
        'código sustantivo',
        'trabajador',
        'despido',
        'terminación del contrato',
        'fuero',
        'reintegro',
        'justa causa',
        'debido proceso',
        'derecho de defensa',
        'descargos',
        'sanción disciplinaria',
        'empleador',
        'subordinado',
        'estabilidad laboral reforzada',
    ];

    /**
     * Obtiene sentencias publicadas desde $desde hasta hoy.
     * Retorna array de documentos listos para guardar en DocumentoLegal.
     *
     * @param  Carbon $desde
     * @param  int    $limite  Máximo de sentencias a retornar por ejecución
     * @return array[]
     */
    public function obtenerSentencias(Carbon $desde, int $limite = 30): array
    {
        $fechaStr = $desde->format('Y-m-d') . 'T00:00:00';

        $respuesta = Http::timeout(30)->get(self::SOCRATA_API, [
            '$limit'  => 200,
            '$order'  => 'fecha_sentencia DESC',
            '$where'  => "fecha_sentencia >= '{$fechaStr}'",
        ]);

        if (!$respuesta->successful()) {
            Log::warning('CorteConstitucionalScraper: API error', [
                'status' => $respuesta->status(),
            ]);
            return [];
        }

        $items     = $respuesta->json() ?? [];
        $resultado = [];

        foreach ($items as $item) {
            if (count($resultado) >= $limite) {
                break;
            }

            $tipo      = strtoupper(trim($item['sentencia_tipo'] ?? ''));
            $sentencia = trim($item['sentencia'] ?? '');

            if (empty($sentencia)) {
                continue;
            }

            // Filtrar solo tipos relevantes para derecho laboral
            if (!$this->esTipoRelevante($tipo, $sentencia)) {
                continue;
            }

            // Construir URL de la relatoría
            $fecha = isset($item['fecha_sentencia'])
                ? Carbon::parse($item['fecha_sentencia'])
                : null;

            $anno    = $fecha ? $fecha->year : null;
            $url     = $this->construirUrl($sentencia, $anno);
            $texto   = $url ? $this->extraerTextoHtml($url) : null;

            if (empty($texto) || mb_strlen($texto) < 100) {
                continue;
            }

            // Filtrar por relevancia de contenido
            if (!$this->esRelevante($texto)) {
                continue;
            }

            $resultado[] = [
                'titulo'     => "Sentencia {$sentencia} — Corte Constitucional",
                'tipo'       => 'sentencia_cc',
                'referencia' => $sentencia,
                'descripcion'=> isset($item['magistrado_a'])
                    ? "M.P.: {$item['magistrado_a']}"
                    : null,
                'fecha'      => $fecha?->toDateString(),
                'texto'      => $texto,
                'url_origen' => $url,
            ];
        }

        return $resultado;
    }

    private function esTipoRelevante(string $tipo, string $sentencia): bool
    {
        foreach (self::TIPOS_RELEVANTES as $t) {
            if (str_starts_with(strtoupper($sentencia), $t)) {
                return true;
            }
        }
        return false;
    }

    private function construirUrl(string $sentencia, ?int $anno): ?string
    {
        if (!$anno) {
            return null;
        }

        // "T-239-21" → "t-239-21" o "C-1270-00"
        $slug = strtolower(trim($sentencia));
        return self::RELATORIA_URL . "/{$anno}/{$slug}.htm";
    }

    private function extraerTextoHtml(string $url): string
    {
        try {
            $respuesta = Http::timeout(20)
                ->withHeaders(['Accept' => 'text/html'])
                ->get($url);

            if (!$respuesta->successful()) {
                return '';
            }

            $html = $respuesta->body();

            // Eliminar scripts y estilos
            $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
            $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);

            // Convertir a texto
            $texto = strip_tags($html);

            // Limpiar espacios
            $texto = preg_replace('/[ \t]+/', ' ', $texto);
            $texto = preg_replace('/\n{3,}/', "\n\n", $texto);

            return trim($texto);
        } catch (\Throwable $e) {
            Log::debug('CorteConstitucionalScraper: no se pudo descargar', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);
            return '';
        }
    }

    private function esRelevante(string $texto): bool
    {
        $haystack = mb_strtolower($texto);
        $encontradas = 0;

        foreach (self::PALABRAS_CLAVE as $palabra) {
            if (str_contains($haystack, $palabra)) {
                $encontradas++;
                if ($encontradas >= 2) {
                    return true;
                }
            }
        }

        return false;
    }
}
