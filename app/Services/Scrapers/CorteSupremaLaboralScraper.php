<?php

namespace App\Services\Scrapers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Descarga sentencias de la Sala de Casación Laboral de la
 * Corte Suprema de Justicia de Colombia.
 *
 * Las sentencias están disponibles como PDFs en:
 * https://archivodigitalapi.cortesuprema.gov.co/share/{año}/{mes}/Sentencias/{PREFIX}{NUM}-{AÑO}.pdf
 *
 * Prefijos de sala laboral: SL, STL
 * Los números son secuenciales por año (SL1-2025, SL2-2025, ...)
 *
 * Estrategia: para cada mes del período solicitado, intentar
 * números de 1 hasta MAX_NUM_POR_MES, deteniéndose después de
 * INTENTOS_CONSECUTIVOS_FALLIDOS fallos seguidos.
 */
class CorteSupremaLaboralScraper
{
    const BASE_URL = 'https://archivodigitalapi.cortesuprema.gov.co/share';

    // Prefijos de la Sala de Casación Laboral
    const PREFIJOS = ['SL', 'STL'];

    // Número máximo a intentar por mes (las SL raramente superan 400/mes)
    const MAX_NUM_POR_MES = 500;

    // Parar si hay esta cantidad de 404s seguidos (indica que ya no hay más)
    const INTENTOS_CONSECUTIVOS_FALLIDOS = 15;

    /**
     * Descarga PDFs de sentencias SL/STL desde $desde hasta hoy.
     *
     * @param  Carbon $desde
     * @param  int    $limite  Máximo de PDFs a descargar
     * @return array[]  Cada elemento: ['titulo', 'tipo', 'referencia', 'pdf_contenido', 'url_origen']
     */
    public function obtenerSentencias(Carbon $desde, int $limite = 20): array
    {
        $resultado = [];
        $ahora     = Carbon::now();

        // Iterar mes a mes desde $desde hasta hoy
        $cursor = $desde->copy()->startOfMonth();

        while ($cursor->lte($ahora) && count($resultado) < $limite) {
            $anno = $cursor->year;
            $mes  = $cursor->month;

            foreach (self::PREFIJOS as $prefijo) {
                if (count($resultado) >= $limite) {
                    break;
                }

                $encontradosEnMes = $this->buscarEnMes($anno, $mes, $prefijo, $limite - count($resultado));
                $resultado = array_merge($resultado, $encontradosEnMes);
            }

            $cursor->addMonth();
        }

        return $resultado;
    }

    private function buscarEnMes(int $anno, int $mes, string $prefijo, int $limiteRestante): array
    {
        $resultado        = [];
        $fallosConsecutivos = 0;

        for ($num = 1; $num <= self::MAX_NUM_POR_MES; $num++) {
            if (count($resultado) >= $limiteRestante) {
                break;
            }

            if ($fallosConsecutivos >= self::INTENTOS_CONSECUTIVOS_FALLIDOS) {
                break;
            }

            $nombre   = "{$prefijo}{$num}-{$anno}";
            $url      = self::BASE_URL . "/{$anno}/{$mes}/Sentencias/{$nombre}.pdf";

            $contenido = $this->descargarPDF($url);

            if ($contenido === null) {
                $fallosConsecutivos++;
                continue;
            }

            $fallosConsecutivos = 0;

            $resultado[] = [
                'titulo'       => "Sentencia {$nombre} — Corte Suprema de Justicia (Sala Laboral)",
                'tipo'         => 'sentencia_csj',
                'referencia'   => $nombre,
                'descripcion'  => "Sala de Casación Laboral · {$anno}",
                'pdf_contenido'=> $contenido,
                'url_origen'   => $url,
            ];

            Log::info("CorteSupremaLaboralScraper: descargado {$nombre}");
        }

        return $resultado;
    }

    private function descargarPDF(string $url): ?string
    {
        try {
            $respuesta = Http::timeout(20)
                ->withHeaders(['Accept' => 'application/pdf'])
                ->get($url);

            if ($respuesta->status() === 404) {
                return null;
            }

            if (!$respuesta->successful()) {
                Log::debug('CorteSupremaLaboralScraper: HTTP ' . $respuesta->status(), ['url' => $url]);
                return null;
            }

            $contenido = $respuesta->body();

            // Verificar que es realmente un PDF
            if (!str_starts_with($contenido, '%PDF')) {
                return null;
            }

            return $contenido;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
