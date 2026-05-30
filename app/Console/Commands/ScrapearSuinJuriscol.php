<?php

namespace App\Console\Commands;

use App\Models\ArticuloLegal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Descarga artículos de leyes colombianas desde SUIN-Juriscol (Sistema Único de
 * Información Normativa del Ministerio de Justicia).
 *
 * Ventajas sobre leyes.co (fuente del cst:scraper):
 *   - Fuente oficial: Ministerio de Justicia de Colombia.
 *   - Texto consolidado con todas las modificaciones (ej: Art. 236 CST con Ley 2114/2021).
 *   - Cubre el CST completo y otras leyes laborales en un solo portal.
 *
 * Uso:
 *   php artisan suin:scraper                    # CST completo
 *   php artisan suin:scraper --norma=ley1010    # Solo Ley 1010/2006
 *   php artisan suin:scraper --norma=all        # Todas las normas configuradas
 *   php artisan suin:scraper --force            # Regenerar embeddings aunque ya existan
 *   php artisan suin:scraper --solo=236         # Solo Art. 236 CST (depuración)
 *   php artisan suin:scraper --dry-run          # Ver artículos detectados sin guardar
 *
 * Cómo obtener el doc_id de SUIN:
 *   1. Buscar la ley en https://www.suin-juriscol.gov.co
 *   2. Abrir la ley → copiar el número del parámetro ?id= de la URL.
 */
class ScrapearSuinJuriscol extends Command
{
    protected $signature   = 'suin:scraper
                                {--norma=cst  : Norma a scrapear: cst | ley1010 | all}
                                {--force      : Regenerar embeddings aunque ya existan}
                                {--solo=      : Solo el artículo indicado (ej: --solo=236)}
                                {--dry-run    : Muestra artículos detectados sin guardar}';
    protected $description = 'Descarga artículos legales desde SUIN-Juriscol (MinJusticia — fuente oficial) con embeddings';

    private const BASE_URL = 'https://www.suin-juriscol.gov.co';

    /**
     * Normas disponibles.
     *
     * doc_id : parámetro ?id= de la URL de SUIN.
     * fuente  : campo "fuente" en articulos_legales.
     * prefijo : patrón del campo "codigo" — {N} se reemplaza por el número del artículo.
     * target  : lista de números de artículo a importar (null = todos los detectados).
     *
     * Para agregar una nueva ley:
     *   1. Buscar el doc_id en SUIN.
     *   2. Agregar la entrada aquí.
     *   3. Definir el prefijo de código (ej: 'Art. {N} Ley 2114').
     */
    private const NORMAS = [
        'cst' => [
            'nombre'    => 'Código Sustantivo del Trabajo',
            'doc_id'    => 30019323,
            'fuente'    => 'CST',
            'categoria' => 'general',
            'prefijo'   => 'Art. {N} CST',
            // null = importar todos los artículos detectados en el documento.
            // Lista de int para importar solo un subconjunto.
            'target'    => null,
        ],
        'ley1010' => [
            'nombre'    => 'Ley 1010 de 2006 — Acoso Laboral',
            // doc_id a verificar: buscar en https://www.suin-juriscol.gov.co
            // la "Ley 1010 de 2006" y copiar el ?id= de la URL.
            'doc_id'    => 30036777,
            'fuente'    => 'LEY_1010',
            'categoria' => 'acoso_laboral',
            'prefijo'   => 'Art. {N} Ley 1010',
            'target'    => null,
        ],
    ];

    // ──────────────────────────────────────────────────────────────────────────

    public function handle(): int
    {
        $apiKey = config('services.ia.gemini.api_key') ?? config('services.gemini.api_key');
        if (!$apiKey && !$this->option('dry-run')) {
            $this->error('No se encontró GEMINI_API_KEY en la configuración.');
            return self::FAILURE;
        }

        $force  = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');
        $soloN  = $this->option('solo') ? trim((string) $this->option('solo')) : null;
        $normaOpt = $this->option('norma');

        $normasAscrapear = $normaOpt === 'all'
            ? self::NORMAS
            : (isset(self::NORMAS[$normaOpt]) ? [$normaOpt => self::NORMAS[$normaOpt]] : []);

        if (empty($normasAscrapear)) {
            $this->error("Norma desconocida: '{$normaOpt}'. Opciones: cst, ley1010, all");
            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('MODO DRY-RUN: los artículos se detectan pero NO se guardan.');
        }

        $totalOk = $totalSkip = $totalErrores = 0;

        foreach ($normasAscrapear as $clave => $config) {
            $this->info("\n» Scrapeando: {$config['nombre']}");
            [$ok, $skip, $errores] = $this->scrapearNorma($config, $apiKey ?? '', $force, $dryRun, $soloN);
            $totalOk      += $ok;
            $totalSkip    += $skip;
            $totalErrores += $errores;
        }

        $this->info("\nTotal: {$totalOk} importados, {$totalSkip} omitidos, {$totalErrores} errores.");
        return self::SUCCESS;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Scraping de una norma
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * @return array{0: int, 1: int, 2: int}  [ok, skip, errores]
     */
    private function scrapearNorma(
        array   $config,
        string  $apiKey,
        bool    $force,
        bool    $dryRun,
        ?string $soloN
    ): array {
        $url  = self::BASE_URL . '/viewDocument.asp?id=' . $config['doc_id'];
        $html = $this->fetchUrl($url);

        if ($html === null) {
            $this->error("  No se pudo descargar: {$url}");
            return [0, 0, 1];
        }

        $this->line('  Parseando HTML...');
        $articulos = $this->parsearArticulos($html, $config['target'] ?? null);

        if (empty($articulos)) {
            $this->error('  No se detectaron artículos. El HTML de SUIN puede haber cambiado.');
            Log::warning('suin:scraper — sin artículos detectados', ['url' => $url]);
            // Guardar muestra del HTML para depuración
            Log::debug('suin:scraper — muestra HTML (primeros 2000 chars)', [
                'html' => mb_substr(strip_tags($html), 0, 2000),
            ]);
            return [0, 0, 1];
        }

        $this->line('  Artículos detectados: ' . count($articulos));

        if ($dryRun) {
            foreach ($articulos as $num => $data) {
                if ($soloN !== null && (string) $num !== $soloN) continue;
                $codigo = str_replace('{N}', (string) $num, $config['prefijo']);
                $preview = mb_substr($data['texto'], 0, 100);
                $this->line("  [dry] {$codigo} — {$data['titulo']}");
                $this->line("         {$preview}...");
            }
            return [0, 0, 0];
        }

        $ok = $skip = $errores = 0;

        foreach ($articulos as $num => $data) {
            $numStr = (string) $num;

            if ($soloN !== null && $numStr !== $soloN) {
                continue;
            }

            $codigo = str_replace('{N}', $numStr, $config['prefijo']);

            // Saltar si ya existe con embedding (a menos que --force)
            $existente = ArticuloLegal::where('codigo', $codigo)
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
                    'empresa_id' => null,
                ],
                [
                    'titulo'         => $data['titulo'],
                    'descripcion'    => mb_substr($data['texto'], 0, 500),
                    'texto_completo' => $data['texto'],
                    'fuente'         => $config['fuente'],
                    'categoria'      => $config['categoria'],
                    'orden'          => is_numeric($num) ? (int) $num : 0,
                    'activo'         => true,
                    'embedding'      => $embedding,
                ]
            );

            $estado = $embedding ? '[ok]' : '[sin embedding]';
            $this->info("  {$estado} {$codigo} — {$data['titulo']}");
            $ok++;

            usleep(350_000); // 350 ms entre llamadas de embedding
        }

        $this->line("  → {$ok} importados, {$skip} omitidos, {$errores} errores");
        return [$ok, $skip, $errores];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // HTTP — SUIN usa certificado SSL no estándar en el gobierno colombiano
    // ──────────────────────────────────────────────────────────────────────────

    private function fetchUrl(string $url): ?string
    {
        $this->line("  GET {$url}");

        try {
            $response = Http::withoutVerifying()   // Cert SSL autofirmado (gov.co)
                ->timeout(60)
                ->withHeaders([
                    'User-Agent'      => 'Mozilla/5.0 (compatible; CES-Legal/1.0)',
                    'Accept'          => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'es-CO,es;q=0.9',
                    'Accept-Encoding' => 'gzip, deflate',
                    'Connection'      => 'keep-alive',
                ])
                ->get($url);

            if (!$response->successful()) {
                $this->warn("  HTTP {$response->status()} en {$url}");
                Log::warning('suin:scraper — HTTP error', ['status' => $response->status(), 'url' => $url]);
                return null;
            }

            $body = $response->body();

            // SUIN puede responder en ISO-8859-1 (sitio antiguo).
            // Detectar encoding desde el meta charset y convertir si es necesario.
            $body = $this->normalizarEncoding($body);

            return $body;
        } catch (\Throwable $e) {
            $this->error("  Excepción al descargar: {$e->getMessage()}");
            Log::error('suin:scraper — excepción fetch', ['url' => $url, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Normaliza el encoding a UTF-8.
     *
     * SUIN-Juriscol declara charset=utf-16 en el meta tag, pero el cuerpo de
     * la respuesta HTTP es UTF-8 válido. Si se convierte creyendo que es UTF-16,
     * los caracteres ASCII (< > " etc.) se corrompen. Por eso verificamos primero
     * si el contenido ya es UTF-8 válido y solo convertimos si realmente no lo es.
     */
    private function normalizarEncoding(string $html): string
    {
        // Si ya es UTF-8 válido, no tocar nada (SUIN declara utf-16 erróneamente).
        if (mb_check_encoding($html, 'UTF-8')) {
            return $html;
        }

        // No es UTF-8: intentar encodings comunes en sitios del gobierno colombiano.
        foreach (['ISO-8859-1', 'UTF-16LE', 'UTF-16BE', 'UTF-16'] as $enc) {
            $candidate = mb_convert_encoding($html, 'UTF-8', $enc);
            if (mb_check_encoding($candidate, 'UTF-8') && str_contains($candidate, '<html')) {
                $this->line("  Encoding detectado: {$enc}");
                return $candidate;
            }
        }

        return $html; // último recurso: devolver sin tocar
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Parsing HTML → artículos
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Extrae los artículos del HTML de SUIN-Juriscol.
     *
     * SUIN almacena el texto de cada artículo dentro de un elemento
     * <div id="toggle_XXXXXX"> donde el contenido es HTML codificado como
     * entidades HTML (&lt;, &gt;, &quot;, etc.). Esta función:
     *   1. Encuentra todos los divs toggle_ con regex.
     *   2. Decodifica las entidades HTML para obtener el texto plano.
     *   3. Extrae el número, título y cuerpo de cada artículo.
     *   4. Filtra por lista target si se proporcionó.
     *
     * @param  ?int[] $target  Lista de números de artículo a conservar; null = todos.
     * @return array<string, array{titulo: string, texto: string}>
     */
    private function parsearArticulos(string $html, ?array $target): array
    {
        // Cada artículo vive en un <div id="toggle_XXXXXX">…</div>
        preg_match_all('/<div id="toggle_(\d+)">(.*?)<\/div>/s', $html, $matches);

        if (empty($matches[0])) {
            Log::warning('suin:scraper — no se encontraron divs toggle_ en el HTML');
            return [];
        }

        $this->line('  Bloques toggle_ encontrados: ' . count($matches[0]));

        $articulos  = [];
        $targetStrs = $target ? array_map('strval', $target) : null;

        foreach ($matches[0] as $i => $block) {
            $encodedContent = $matches[2][$i];

            // El contenido interno está codificado como HTML entities; decodificarlo.
            $decoded = html_entity_decode($encodedContent, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            // Eliminar etiquetas HTML; normalizar espacios en blanco.
            $text = trim(preg_replace('/\s+/', ' ', strip_tags($decoded)));

            // Debe comenzar con "ARTICULO N" o "Artículo N"
            // Captura: "ARTICULO 241", "ARTICULO 241A", "ARTICULO 241 A", "ARTICULO 1o"
            if (!preg_match('/^\s*ART[IÍ]CULO\s+(\d+)\s*([A-Z]?)(?:[oO°º])?[.\s\-]*(.*)/iu', $text, $am)) {
                continue;
            }

            // Reconstruir número: "241" + "A" → "241A"; "1" + "o" (ordinal) → "1"
            $numStr = strtoupper(trim($am[1]));
            $sufijo = strtoupper(trim($am[2] ?? ''));
            $resto  = trim($am[3] ?? '');
            // Añadir sufijo solo si es letra distinta de "O" ordinal (art. 1º, 2º…)
            if ($sufijo !== '' && !($sufijo === 'O' && (int)$numStr <= 9)) {
                $numStr .= $sufijo;
            }

            // Filtrar por lista target
            if ($targetStrs !== null && !in_array($numStr, $targetStrs, true)) {
                continue;
            }

            // Artículo derogado o vacío
            if (mb_strlen($text) < 25) {
                continue;
            }

            // Extraer título: primera oración del cuerpo si es descriptiva y concisa.
            // Ejemplo: "OBLIGACIONES DE LAS PARTES EN GENERAL. De modo general..."
            //          → título = "OBLIGACIONES DE LAS PARTES EN GENERAL"
            $titulo = '';
            if (preg_match('/^([^.]{5,200})\.\s/', $resto, $tm)) {
                $candidate = trim($tm[1]);
                // Rechazar si parece inicio de texto normativo (verbos en 3ª persona, artículos)
                if (!preg_match('/^(?:toda|el |los |las |se |cuando|para |en |con |de )/iu', $candidate)) {
                    $titulo = ucfirst(mb_strtolower($candidate));
                }
            }
            if (empty($titulo)) {
                $titulo = "Artículo {$numStr}";
            }

            // Texto completo con prefijo estándar
            $textoCompleto = trim("Artículo {$numStr}. " . $resto);
            $textoCompleto = preg_replace('/\s{2,}/', ' ', $textoCompleto);

            // Si hay duplicados (varios toggle_ para un mismo artículo),
            // conservar la versión más larga (generalmente la vigente).
            if (!isset($articulos[$numStr]) || mb_strlen($textoCompleto) > mb_strlen($articulos[$numStr]['texto'])) {
                $articulos[$numStr] = [
                    'titulo' => $titulo,
                    'texto'  => $textoCompleto,
                ];
            }
        }

        return $articulos;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Embedding Gemini
    // ──────────────────────────────────────────────────────────────────────────

    private function generarEmbedding(string $texto, string $apiKey): ?array
    {
        if (empty($apiKey)) {
            return null;
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-001:embedContent?key={$apiKey}";

        try {
            $response = Http::timeout(20)->post($url, [
                'content'  => ['parts' => [['text' => mb_substr($texto, 0, 8000)]]],
                'taskType' => 'RETRIEVAL_DOCUMENT',
            ]);

            if (!$response->successful()) {
                Log::warning('suin:scraper — embedding fallido', ['status' => $response->status()]);
                return null;
            }

            $values = $response->json('embedding.values');
            return is_array($values) && !empty($values) ? $values : null;
        } catch (\Throwable $e) {
            Log::error('suin:scraper — excepción embedding', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
