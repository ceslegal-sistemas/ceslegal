<?php

namespace App\Console\Commands;

use App\Models\ArticuloLegal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Descarga artículos del Código Sustantivo del Trabajo desde leyes.co
 * y los importa con embeddings Gemini en articulos_legales.
 *
 * Es idempotente. Excluye automáticamente artículos que tienen versión
 * manual más actualizada (ej: Art. 115 modificado por Ley 2466/2025).
 *
 * Uso:
 *   php artisan cst:scraper                # Importa/actualiza todos
 *   php artisan cst:scraper --force        # Regenera embeddings aunque ya existan
 *   php artisan cst:scraper --solo=62      # Solo el artículo 62
 */
class ScrapearArticulosCst extends Command
{
    protected $signature   = 'cst:scraper
                                {--force  : Regenerar embeddings aunque ya existan}
                                {--solo=  : Scrapear solo el artículo indicado (ej: --solo=62)}';
    protected $description = 'Descarga artículos del CST desde leyes.co e importa con embeddings para RAG';

    private const BASE_URL  = 'https://leyes.co/codigo_sustantivo_del_trabajo/';
    private const FUENTE    = 'CST';

    /**
     * Artículos que NO se scraperan porque tienen versión manual más actualizada.
     * Mantener sincronizado con ImportarArticulosCst.php.
     */
    private array $excluidos = [
        115, // Ley 2466/2025 — leyes.co puede no tenerlo aún
        236, // Ley 2114/2021 — versión manual incluye parágrafos completos
    ];

    /**
     * Lista de artículos a importar.
     * Formato: numero => [categoria, orden]
     */
    private array $articulos = [

        // ── PRINCIPIOS GENERALES ─────────────────────────────────────────────
        10  => ['principios',                 10],
        13  => ['principios',                 13],

        // ── CONTRATO DE TRABAJO ──────────────────────────────────────────────
        22  => ['contrato',                   22],
        23  => ['contrato',                   23],
        24  => ['contrato',                   24],
        37  => ['contrato',                   37],
        45  => ['contrato',                   45],
        46  => ['contrato',                   46],
        47  => ['contrato',                   47],

        // ── SUSPENSIÓN DEL CONTRATO ──────────────────────────────────────────
        51  => ['contrato',                   51],
        52  => ['contrato',                   52],
        53  => ['contrato',                   53],
        54  => ['contrato',                   54],

        // ── OBLIGACIONES, PROHIBICIONES Y TERMINACIÓN ────────────────────────
        55  => ['obligaciones',               55],
        56  => ['obligaciones',               56],
        57  => ['obligaciones',               57],
        58  => ['obligaciones',               58],
        59  => ['prohibiciones',              59],
        60  => ['prohibiciones',              60],
        61  => ['terminacion',                61],
        62  => ['terminacion',                62],
        64  => ['terminacion',                64],
        65  => ['terminacion',                65],
        69  => ['terminacion',                69],

        // ── PERÍODO DE PRUEBA ────────────────────────────────────────────────
        76  => ['contrato',                   76],
        77  => ['contrato',                   77],
        78  => ['contrato',                   78],
        80  => ['contrato',                   80],

        // ── REGLAMENTO INTERNO DE TRABAJO ────────────────────────────────────
        104 => ['reglamento_interno',        104],
        105 => ['reglamento_interno',        105],
        106 => ['reglamento_interno',        106],
        107 => ['reglamento_interno',        107],
        108 => ['reglamento_interno',        108],
        109 => ['reglamento_interno',        109],
        110 => ['reglamento_interno',        110],

        // ── SANCIONES DISCIPLINARIAS ─────────────────────────────────────────
        111 => ['procedimiento_disciplinario', 111],
        112 => ['procedimiento_disciplinario', 112],
        113 => ['procedimiento_disciplinario', 113],
        114 => ['procedimiento_disciplinario', 114],
        115 => ['procedimiento_disciplinario', 115], // excluido → versión manual

        // ── PROTECCIÓN A LA MATERNIDAD ───────────────────────────────────────
        236 => ['grupos_protegidos',         236], // excluido → versión manual
        237 => ['grupos_protegidos',         237],
        238 => ['grupos_protegidos',         238],
        239 => ['grupos_protegidos',         239],
        240 => ['grupos_protegidos',         240],
        241 => ['grupos_protegidos',         241],

        // ── FUERO SINDICAL ───────────────────────────────────────────────────
        405 => ['grupos_protegidos',         405],
        406 => ['grupos_protegidos',         406],
        407 => ['grupos_protegidos',         407],
        408 => ['grupos_protegidos',         408],
        409 => ['grupos_protegidos',         409],
        410 => ['grupos_protegidos',         410],
        411 => ['grupos_protegidos',         411],
    ];

    public function handle(): int
    {
        $this->info('Descargando artículos del CST desde leyes.co...');

        $apiKey = config('services.ia.gemini.api_key') ?? config('services.gemini.api_key');
        if (!$apiKey) {
            $this->error('No se encontró GEMINI_API_KEY en la configuración.');
            return self::FAILURE;
        }

        $force    = $this->option('force');
        $soloNum  = $this->option('solo') ? (int) $this->option('solo') : null;
        $lista    = $soloNum
            ? [$soloNum => $this->articulos[$soloNum] ?? ['general', $soloNum]]
            : $this->articulos;

        $ok = $skip = $errores = 0;

        foreach ($lista as $numero => $meta) {
            // Respetar exclusiones (solo si no se usa --solo)
            if (!$soloNum && in_array($numero, $this->excluidos)) {
                $this->line("  [excluido] Art. {$numero} — usar versión manual (más actualizada)");
                $skip++;
                continue;
            }

            [$categoria, $orden] = $meta;
            $codigo = "Art. {$numero} CST";

            // Verificar si ya existe con embedding (skip si no --force)
            $existente = ArticuloLegal::where('codigo', $codigo)
                ->where('fuente', self::FUENTE)
                ->whereNull('empresa_id')
                ->first();

            if ($existente && !$force && $existente->embedding) {
                $this->line("  [skip] {$codigo}");
                $skip++;
                continue;
            }

            $resultado = $this->scrapearArticulo($numero);

            if (!$resultado) {
                $this->warn("  [error] {$codigo} — no se pudo obtener de leyes.co");
                Log::warning("cst:scraper — sin resultado para Art. {$numero}");
                $errores++;
                continue;
            }

            $embedding = $this->generarEmbedding($resultado['texto'], $apiKey);

            ArticuloLegal::updateOrCreate(
                [
                    'codigo'     => $codigo,
                    'fuente'     => self::FUENTE,
                    'empresa_id' => null,
                ],
                [
                    'titulo'         => $resultado['titulo'],
                    'descripcion'    => mb_substr($resultado['texto'], 0, 255),
                    'texto_completo' => $resultado['texto'],
                    'categoria'      => $categoria,
                    'orden'          => $orden,
                    'activo'         => true,
                    'embedding'      => $embedding,
                ]
            );

            $estado = $embedding ? '[ok]' : '[guardado sin embedding]';
            $this->info("  {$estado} {$codigo} — {$resultado['titulo']}");
            $ok++;

            usleep(400_000); // 400 ms entre requests
        }

        $this->info("Listo. {$ok} importados, {$skip} omitidos, {$errores} errores.");
        return self::SUCCESS;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Scraping y parsing
    // ──────────────────────────────────────────────────────────────────────────

    private function scrapearArticulo(int $numero): ?array
    {
        $url = self::BASE_URL . "{$numero}.htm";

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; CES-Legal/1.0; +https://ceslegal.co)',
                    'Accept'     => 'text/html,application/xhtml+xml',
                    'Accept-Language' => 'es-CO,es;q=0.9',
                ])
                ->get($url);

            if (!$response->successful()) {
                Log::warning("cst:scraper HTTP {$response->status()} Art. {$numero}");
                return null;
            }

            return $this->parsearHtml($response->body(), $numero);
        } catch (\Exception $e) {
            Log::error("cst:scraper excepción Art. {$numero}", ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function parsearHtml(string $html, int $numero): ?array
    {
        libxml_use_internal_errors(true);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML(
            mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'),
            LIBXML_NOERROR | LIBXML_NOWARNING
        );

        $xpath = new \DOMXPath($dom);

        // El contenido del artículo está en div#statya (estructura de leyes.co)
        $statya = $xpath->query('//div[@id="statya"]');
        if (!$statya || $statya->length === 0) {
            Log::warning("cst:scraper — div#statya no encontrado Art. {$numero}");
            return null;
        }

        $nodo = $statya->item(0);

        // Extraer y limpiar el título desde el h1
        $h1s   = $xpath->query('.//h1', $nodo);
        $titulo = '';
        if ($h1s->length > 0) {
            $h1Text = trim(preg_replace('/\s+/', ' ', $h1s->item(0)->textContent));
            // Quitar "Código Sustantivo del Trabajo" y "Artículo X."
            $titulo = preg_replace('/^Código\s+Sustantivo\s+del\s+Trabajo\s*/ui', '', $h1Text);
            $titulo = preg_replace('/^Art[ií]culo\s+\d+[\.\-\s]*/ui', '', $titulo);
            $titulo = trim($titulo);
        }

        // Extraer texto completo del div#statya
        $texto = $this->extraerTextoNodo($nodo);

        // Eliminar pie de página que leyes.co agrega al final
        $texto = preg_replace(
            '/Colombia\s+Art\.?\s+' . $numero . '\.?\s+Código\s+Sustantivo\s+del\s+Trabajo[^\n]*/ui',
            '',
            $texto
        );

        // Normalizar espacios y saltos
        $texto = preg_replace('/[ \t]+/', ' ', $texto);
        $texto = preg_replace('/\n[ \t]+/', "\n", $texto);
        $texto = preg_replace('/\n{3,}/', "\n\n", $texto);
        $texto = html_entity_decode(trim($texto), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (mb_strlen($texto) < 15) {
            Log::warning("cst:scraper — texto demasiado corto Art. {$numero}");
            return null;
        }

        return [
            'titulo' => $titulo ?: "Artículo {$numero}",
            'texto'  => $texto,
        ];
    }

    /**
     * Extrae el texto de un nodo DOM preservando estructura básica (saltos de línea).
     */
    private function extraerTextoNodo(\DOMNode $nodo): string
    {
        $partes = [];

        foreach ($nodo->childNodes as $hijo) {
            if ($hijo instanceof \DOMText) {
                $t = trim($hijo->textContent);
                if ($t !== '') {
                    $partes[] = $t;
                }
                continue;
            }

            if (!($hijo instanceof \DOMElement)) {
                continue;
            }

            $tag = strtolower($hijo->tagName);

            // Ignorar elementos no relevantes
            if (in_array($tag, ['script', 'style', 'nav', 'footer', 'iframe', 'noscript'])) {
                continue;
            }

            // El h1 contiene el título — lo formateamos especial
            if ($tag === 'h1') {
                $t = trim(preg_replace('/\s+/', ' ', $hijo->textContent));
                if ($t !== '') {
                    $partes[] = 'ARTICULO ' . $t;
                }
                continue;
            }

            // Elementos de bloque: agregar como párrafo
            if (in_array($tag, ['p', 'div', 'li', 'h2', 'h3', 'h4', 'h5', 'blockquote'])) {
                $t = trim(preg_replace('/\s+/', ' ', $hijo->textContent));
                if ($t !== '') {
                    $partes[] = $t;
                }
                continue;
            }

            // br → salto de línea
            if ($tag === 'br') {
                $partes[] = '';
                continue;
            }

            // Cualquier otro elemento inline
            $t = trim(preg_replace('/\s+/', ' ', $hijo->textContent));
            if ($t !== '') {
                $partes[] = $t;
            }
        }

        return implode("\n", $partes);
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
                Log::warning('cst:scraper — embedding fallido', ['status' => $response->status()]);
                return null;
            }

            $values = $response->json('embedding.values');
            return is_array($values) && !empty($values) ? $values : null;
        } catch (\Exception $e) {
            Log::error('cst:scraper — excepción embedding', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
