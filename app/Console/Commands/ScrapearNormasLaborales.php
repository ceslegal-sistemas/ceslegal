<?php

namespace App\Console\Commands;

use App\Models\ArticuloLegal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Descarga Ley 1010/2006 y Resolución 652/2012 desde sus fuentes oficiales
 * e importa artículo por artículo en articulos_legales con embeddings Gemini.
 *
 * Uso:
 *   php artisan normas:scraper              # Importa/actualiza ambas normas
 *   php artisan normas:scraper --norma=ley1010
 *   php artisan normas:scraper --norma=res652
 *   php artisan normas:scraper --force      # Regenera embeddings aunque ya existan
 *
 * Nota: Res. 652/2012 fue derogada por Resolución 3461 de 2025, pero se mantiene
 * porque la mayoría de empresas aún la implementan y referencia en sus RITs.
 */
class ScrapearNormasLaborales extends Command
{
    protected $signature   = 'normas:scraper
                                {--force  : Regenerar embeddings aunque ya existan}
                                {--norma=all : Norma a scrapear: ley1010 | res652 | all}';
    protected $description = 'Descarga Ley 1010/2006 y Resolución 652/2012 e importa artículos con embeddings';

    private const NORMAS = [
        'ley1010' => [
            'nombre'    => 'Ley 1010 de 2006 (Acoso Laboral)',
            'fuente'    => 'LEY_1010',
            'categoria' => 'acoso_laboral',
            'url'       => 'http://www.secretariasenado.gov.co/senado/basedoc/ley_1010_2006.html',
            'selector'  => ['id', 'aj_data'],
            'encoding'  => 'ISO-8859-1',
            'timeout'   => 45,
            'prefijo'   => 'Art. {N} Ley 1010',
        ],
        'res652' => [
            'nombre'    => 'Resolución 652 de 2012 (Comité de Convivencia Laboral)',
            'fuente'    => 'RES_652_2012',
            'categoria' => 'acoso_laboral',
            'url'       => 'https://www.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=161738',
            'selector'  => ['class', 'descripcion-contenido'],
            'encoding'  => 'UTF-8',
            'timeout'   => 30,
            'prefijo'   => 'Art. {N} Res. 652/2012',
        ],
    ];

    public function handle(): int
    {
        $apiKey = config('services.ia.gemini.api_key') ?? config('services.gemini.api_key');
        if (!$apiKey) {
            $this->error('No se encontró GEMINI_API_KEY en la configuración.');
            return self::FAILURE;
        }

        $force = $this->option('force');
        $norma = $this->option('norma');

        $normasAscrapear = $norma === 'all'
            ? self::NORMAS
            : array_intersect_key(self::NORMAS, [$norma => true]);

        if (empty($normasAscrapear)) {
            $this->error("Norma desconocida: {$norma}. Opciones: ley1010, res652, all");
            return self::FAILURE;
        }

        $totalOk = $totalSkip = $totalErrores = 0;

        foreach ($normasAscrapear as $clave => $config) {
            $this->info("Scrapeando: {$config['nombre']}");
            [$ok, $skip, $errores] = $this->scrapearNorma($config, $apiKey, $force);
            $totalOk      += $ok;
            $totalSkip    += $skip;
            $totalErrores += $errores;
            $this->info("  → {$ok} importados, {$skip} omitidos, {$errores} errores\n");
        }

        $this->info("Total: {$totalOk} importados, {$totalSkip} omitidos, {$totalErrores} errores.");
        return self::SUCCESS;
    }

    // ──────────────────────────────────────────────────────────────────────────

    private function scrapearNorma(array $config, string $apiKey, bool $force): array
    {
        $html = $this->fetchUrl($config['url'], $config['timeout'], $config['encoding']);
        if ($html === null) {
            $this->error("  No se pudo descargar: {$config['url']}");
            return [0, 0, 1];
        }

        $contenido = $this->extraerContenido($html, $config['selector']);
        if (empty(trim($contenido))) {
            $this->error("  No se encontró el contenido principal en la página.");
            return [0, 0, 1];
        }

        $articulos = $this->parsearArticulos($contenido);
        if (empty($articulos)) {
            $this->error("  No se detectaron artículos en el contenido.");
            return [0, 0, 1];
        }

        $this->line("  Artículos detectados: " . count($articulos));

        $ok = $skip = $errores = 0;

        foreach ($articulos as $num => $data) {
            $codigo = str_replace('{N}', $num, $config['prefijo']);

            $existente = ArticuloLegal::where('codigo', $codigo)
                ->where('fuente', $config['fuente'])
                ->whereNull('empresa_id')
                ->first();

            if ($existente && !$force && $existente->embedding) {
                $this->line("  [skip] {$codigo}");
                $skip++;
                continue;
            }

            $embedding = $this->generarEmbedding($data['texto'], $apiKey);

            ArticuloLegal::updateOrCreate(
                [
                    'codigo'     => $codigo,
                    'fuente'     => $config['fuente'],
                    'empresa_id' => null,
                ],
                [
                    'titulo'         => $data['titulo'],
                    'descripcion'    => mb_substr($data['texto'], 0, 500),
                    'texto_completo' => $data['texto'],
                    'categoria'      => $config['categoria'],
                    'orden'          => (int) $num,
                    'activo'         => true,
                    'embedding'      => $embedding,
                ]
            );

            $estado = $embedding ? '[ok]' : '[sin embedding]';
            $this->info("  {$estado} {$codigo} — {$data['titulo']}");
            $ok++;

            usleep(400_000); // 400 ms entre requests de embedding
        }

        return [$ok, $skip, $errores];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // HTTP
    // ──────────────────────────────────────────────────────────────────────────

    private function fetchUrl(string $url, int $timeout, string $encoding): ?string
    {
        try {
            $response = Http::timeout($timeout)
                ->withHeaders([
                    'User-Agent'      => 'Mozilla/5.0 (compatible; CES-Legal/1.0)',
                    'Accept'          => 'text/html,application/xhtml+xml',
                    'Accept-Language' => 'es-CO,es;q=0.9',
                ])
                ->get($url);

            if (!$response->successful()) {
                Log::warning("normas:scraper HTTP {$response->status()} — {$url}");
                return null;
            }

            $body = $response->body();

            // Convertir a UTF-8 si la fuente usa otro encoding
            if (strtoupper($encoding) !== 'UTF-8') {
                $body = mb_convert_encoding($body, 'UTF-8', $encoding);
            }

            return $body;
        } catch (\Throwable $e) {
            Log::error("normas:scraper — excepción fetch {$url}", ['error' => $e->getMessage()]);
            return null;
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Parsing HTML → texto plano
    // ──────────────────────────────────────────────────────────────────────────

    private function extraerContenido(string $html, array $selector): string
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        // Convertir caracteres no-ASCII a entidades numéricas para evitar
        // que DOMDocument re-codifique el UTF-8 al detectar <meta charset>
        $htmlSafe = mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, ~0], 'UTF-8');
        $dom->loadHTML($htmlSafe, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($dom);

        [$tipo, $valor] = $selector;

        $nodos = match ($tipo) {
            'id'    => $xpath->query("//*[@id='{$valor}']"),
            'class' => $xpath->query("//*[contains(@class,'{$valor}')]"),
            default => null,
        };

        if (!$nodos || $nodos->length === 0) {
            return '';
        }

        return $this->nodeToText($nodos->item(0));
    }

    private function nodeToText(\DOMNode $nodo): string
    {
        $partes = [];

        foreach ($nodo->childNodes as $hijo) {
            if ($hijo instanceof \DOMText) {
                $t = trim($hijo->textContent);
                if ($t !== '') $partes[] = $t;
                continue;
            }
            if (!($hijo instanceof \DOMElement)) continue;

            $tag = strtolower($hijo->tagName);

            if (in_array($tag, ['script', 'style', 'nav', 'footer', 'iframe', 'noscript', 'form', 'select'])) {
                continue;
            }

            if (in_array($tag, ['p', 'div', 'li', 'h1', 'h2', 'h3', 'h4', 'blockquote'])) {
                $t = trim(preg_replace('/\s+/', ' ', $hijo->textContent));
                if ($t !== '') $partes[] = $t;
                continue;
            }

            if ($tag === 'br') {
                $partes[] = '';
                continue;
            }

            $t = trim(preg_replace('/\s+/', ' ', $hijo->textContent));
            if ($t !== '') $partes[] = $t;
        }

        return implode("\n", $partes);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Parsear artículos del texto extraído
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Divide el texto en artículos individuales.
     * Maneja tanto "ARTÍCULO 1o." (Ley 1010) como "ARTÍCULO 1." (Res. 652).
     *
     * @return array<int, array{titulo: string, texto: string}>
     */
    private function parsearArticulos(string $texto): array
    {
        // Normalizar texto: quitar saltos múltiples
        $texto = preg_replace('/\n{3,}/', "\n\n", $texto);

        // Patrón: ARTÍCULO N. o ARTÍCULO No. o ARTÍCULO 1o.
        $patron = '/ARTÍCULO\s+(\d+)[oO°]?\s*\.\s*/u';

        // Dividir preservando el delimitador
        $partes = preg_split($patron, $texto, -1, PREG_SPLIT_DELIM_CAPTURE);

        if (!$partes || count($partes) < 3) {
            return [];
        }

        $articulos = [];
        // partes[0] = preámbulo (antes del primer artículo)
        // partes[1] = número, partes[2] = texto, partes[3] = número, partes[4] = texto...
        for ($i = 1; $i < count($partes) - 1; $i += 2) {
            $num    = (int) $partes[$i];
            $cuerpo = trim($partes[$i + 1] ?? '');

            // Limpiar ruido típico de sitios legales colombianos
            $cuerpo = preg_replace('/\b(Jurisprudencia Vigencia|Notas de Vigencia|Legislación Anterior|Ver Notas de Vigencia|Resumen de Notas de Vigencia)\b/u', '', $cuerpo);
            $cuerpo = preg_replace('/\n{3,}/', "\n\n", $cuerpo);
            $cuerpo = trim($cuerpo);

            // Extraer título: primera línea en MAYÚSCULAS o hasta el primer punto
            $primeraLinea = strtok($cuerpo, "\n");
            $titulo       = $this->extraerTitulo($primeraLinea, $num);

            // Texto completo: incluir el encabezado del artículo
            $textoCompleto = "ARTÍCULO {$num}. {$cuerpo}";

            if (mb_strlen($cuerpo) < 10) continue;

            $articulos[$num] = [
                'titulo' => $titulo,
                'texto'  => $textoCompleto,
            ];
        }

        return $articulos;
    }

    private function extraerTitulo(string $linea, int $num): string
    {
        $linea = trim($linea);

        // Si la línea tiene un subtítulo en formato "OBJETO." o "Objeto."
        // seguido del texto del artículo, extraemos solo hasta el primer punto
        if (preg_match('/^([A-ZÁÉÍÓÚÑÜ][^.]{3,80})\.\s+/u', $linea, $m)) {
            return ucfirst(mb_strtolower(trim($m[1])));
        }

        // Si es todo en mayúsculas (estilo Ley 1010)
        if (mb_strtoupper($linea) === $linea && mb_strlen($linea) > 5) {
            // Puede ser "OBJETO DE LA LEY Y BIENES PROTEGIDOS POR ELLA. La presente..."
            if (preg_match('/^([^.]{5,100})\./u', $linea, $m)) {
                return ucfirst(mb_strtolower(trim($m[1])));
            }
            return ucfirst(mb_strtolower(mb_substr($linea, 0, 80)));
        }

        return "Artículo {$num}";
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Embedding Gemini
    // ──────────────────────────────────────────────────────────────────────────

    private function generarEmbedding(string $texto, string $apiKey): ?array
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-001:embedContent?key={$apiKey}";

        try {
            $response = Http::timeout(15)->post($url, [
                'content'  => ['parts' => [['text' => mb_substr($texto, 0, 8000)]]],
                'taskType' => 'RETRIEVAL_DOCUMENT',
            ]);

            if (!$response->successful()) {
                Log::warning('normas:scraper — embedding fallido', ['status' => $response->status()]);
                return null;
            }

            $values = $response->json('embedding.values');
            return is_array($values) && !empty($values) ? $values : null;
        } catch (\Throwable $e) {
            Log::error('normas:scraper — excepción embedding', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
