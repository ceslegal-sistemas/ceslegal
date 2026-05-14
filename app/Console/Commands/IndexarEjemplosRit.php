<?php

namespace App\Console\Commands;

use App\Models\ArticuloLegal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\IOFactory as WordFactory;
use Smalot\PdfParser\Parser as PdfParser;

/**
 * Indexa ejemplos de Reglamentos Internos de Trabajo (RIT) de referencia.
 *
 * Fuentes:
 *   - storage/app/documentos-referencia/rit/excelente/  → fuente = 'RIT-EJEMPLO-EXCELENTE'
 *   - storage/app/documentos-referencia/rit/malo/       → fuente = 'RIT-EJEMPLO-MALO'
 *
 * Los chunks se almacenan en articulos_legales con empresa_id = NULL (universal).
 * Es idempotente: elimina chunks anteriores antes de regenerar.
 *
 * Uso:
 *   php artisan rit:indexar-ejemplos
 *   php artisan rit:indexar-ejemplos --solo=excelente
 *   php artisan rit:indexar-ejemplos --solo=malo
 *   php artisan rit:indexar-ejemplos --sin-embeddings   (indexa sin llamar a Gemini)
 */
class IndexarEjemplosRit extends Command
{
    protected $signature = 'rit:indexar-ejemplos
                            {--solo= : Procesar solo una carpeta: excelente|malo}
                            {--sin-embeddings : Indexar sin generar embeddings (más rápido, para pruebas)}';

    protected $description = 'Indexa RITs de referencia (excelente/malo) en articulos_legales para RAG';

    private const CHUNK_SIZE      = 1500;
    private const CHUNK_OVERLAP   = 200;
    private const EMBEDDING_PAUSE = 400; // ms — margen seguro para rate limit de Gemini

    private const FUENTES = [
        'excelente' => 'RIT-EJEMPLO-EXCELENTE',
        'malo'      => 'RIT-EJEMPLO-MALO',
    ];

    public function handle(): int
    {
        $this->info('Indexando ejemplos de RIT de referencia...');

        $apiKey         = config('services.ia.gemini.api_key') ?? config('services.gemini.api_key');
        $sinEmbeddings  = $this->option('sin-embeddings');
        $solo           = $this->option('solo');

        if (!$sinEmbeddings && !$apiKey) {
            $this->error('No se encontró GEMINI_API_KEY. Use --sin-embeddings para indexar sin vectores.');
            return self::FAILURE;
        }

        $carpetas = $solo
            ? [$solo => self::FUENTES[$solo] ?? null]
            : self::FUENTES;

        $totalChunks = 0;

        foreach ($carpetas as $tipo => $fuente) {
            if (!$fuente) {
                $this->warn("Tipo desconocido: {$tipo}. Use 'excelente' o 'malo'.");
                continue;
            }

            $directorio = storage_path("app/documentos-referencia/rit/{$tipo}");

            if (!is_dir($directorio)) {
                $this->warn("Directorio no encontrado: {$directorio}");
                continue;
            }

            $archivos = array_merge(
                glob("{$directorio}/*.pdf") ?: [],
                glob("{$directorio}/*.docx") ?: [],
            );

            if (empty($archivos)) {
                $this->warn("No se encontraron archivos PDF/DOCX en {$directorio}");
                continue;
            }

            $this->line("\n[{$tipo}] {$fuente} — " . count($archivos) . ' archivo(s)');

            foreach ($archivos as $ruta) {
                $chunks = $this->procesarArchivo($ruta, $fuente, $apiKey, $sinEmbeddings);
                $totalChunks += $chunks;
            }
        }

        $this->newLine();
        $this->info("Completado. Total chunks indexados: {$totalChunks}");

        return self::SUCCESS;
    }

    private function procesarArchivo(string $ruta, string $fuente, ?string $apiKey, bool $sinEmbeddings): int
    {
        $nombreArchivo = basename($ruta);
        $extension     = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
        $slug          = $this->slugFromFilename($nombreArchivo);

        $this->line("  Procesando: {$nombreArchivo}");

        // Extraer texto
        $texto = match ($extension) {
            'pdf'  => $this->extraerTextoPdf($ruta),
            'docx' => $this->extraerTextoDocx($ruta),
            default => null,
        };

        if (blank($texto) || mb_strlen(trim($texto)) < 100) {
            $this->warn("    [skip] No se pudo extraer texto o el contenido es muy corto.");
            return 0;
        }

        $this->line("    Texto extraído: " . number_format(mb_strlen($texto)) . " caracteres");

        // Limpiar chunks anteriores de este archivo
        $eliminados = ArticuloLegal::whereNull('empresa_id')
            ->where('fuente', $fuente)
            ->where('codigo', 'like', "RIT-REF-{$slug}-%")
            ->delete();

        if ($eliminados > 0) {
            $this->line("    [limpio] {$eliminados} chunk(s) anteriores eliminados");
        }

        // Dividir y guardar
        $chunks = $this->dividirEnChunks($texto);
        $this->line("    Generando " . count($chunks) . " chunk(s)...");

        foreach ($chunks as $i => $chunk) {
            $orden  = $i + 1;
            $codigo = "RIT-REF-{$slug}-{$orden}";

            $embedding = (!$sinEmbeddings && $apiKey)
                ? $this->generarEmbedding($chunk, $apiKey)
                : null;

            ArticuloLegal::create([
                'empresa_id'     => null,
                'codigo'         => $codigo,
                'titulo'         => $this->tituloDesdeFuente($fuente, $nombreArchivo, $orden),
                'descripcion'    => mb_substr($chunk, 0, 255),
                'texto_completo' => $chunk,
                'categoria'      => 'rit_ejemplo',
                'fuente'         => $fuente,
                'activo'         => true,
                'orden'          => $orden,
                'embedding'      => $embedding,
            ]);

            $status = $embedding ? '[ok]' : ($sinEmbeddings ? '[sin embedding]' : '[embedding fallido]');
            $this->line("    {$status} {$codigo}");

            if (!$sinEmbeddings && $apiKey) {
                usleep(self::EMBEDDING_PAUSE * 1000);
            }
        }

        $this->info("    {$slug}: " . count($chunks) . " chunk(s) indexados.");

        return count($chunks);
    }

    // ── Extracción de texto ───────────────────────────────────────────────────

    private function extraerTextoPdf(string $ruta): string
    {
        try {
            $parser    = new PdfParser();
            $pdf       = $parser->parseFile($ruta);
            $texto     = $pdf->getText();

            // Limpiar texto extraído de PDF (artefactos comunes)
            $texto = preg_replace('/\s{3,}/', "\n", $texto);           // múltiples espacios
            $texto = preg_replace('/(\r\n|\r|\n){3,}/', "\n\n", $texto); // múltiples saltos

            return trim($texto);
        } catch (\Exception $e) {
            Log::warning('rit:indexar-ejemplos — error PDF', [
                'archivo' => basename($ruta),
                'error'   => $e->getMessage(),
            ]);
            $this->warn("    [error PDF] " . $e->getMessage());
            return '';
        }
    }

    private function extraerTextoDocx(string $ruta): string
    {
        try {
            $phpWord = WordFactory::load($ruta);
            $lineas  = [];

            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    $lineas[] = $this->elementoATexto($element);
                }
            }

            return trim(implode("\n", array_filter($lineas)));
        } catch (\Exception $e) {
            Log::warning('rit:indexar-ejemplos — error DOCX', [
                'archivo' => basename($ruta),
                'error'   => $e->getMessage(),
            ]);
            $this->warn("    [error DOCX] " . $e->getMessage());
            return '';
        }
    }

    private function elementoATexto(mixed $element): string
    {
        if ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
            return implode('', array_map([$this, 'elementoATexto'], $element->getElements()));
        }
        if ($element instanceof \PhpOffice\PhpWord\Element\Text) {
            return $element->getText();
        }
        if ($element instanceof \PhpOffice\PhpWord\Element\Paragraph) {
            return implode('', array_map([$this, 'elementoATexto'], $element->getElements()));
        }
        if ($element instanceof \PhpOffice\PhpWord\Element\Table) {
            $filas = [];
            foreach ($element->getRows() as $row) {
                $celdas = [];
                foreach ($row->getCells() as $cell) {
                    $celdas[] = implode(' ', array_map([$this, 'elementoATexto'], $cell->getElements()));
                }
                $filas[] = implode(' | ', $celdas);
            }
            return implode("\n", $filas);
        }
        if ($element instanceof \PhpOffice\PhpWord\Element\ListItem) {
            return '- ' . $this->elementoATexto($element->getTextObject());
        }
        return '';
    }

    // ── Chunking ─────────────────────────────────────────────────────────────

    private function dividirEnChunks(string $texto): array
    {
        $texto  = trim($texto);
        $length = mb_strlen($texto);

        if ($length <= self::CHUNK_SIZE) {
            return [$texto];
        }

        $chunks = [];
        $offset = 0;

        while ($offset < $length) {
            // Evitar micro-chunks del overlap final: el texto restante ya está
            // cubierto en el overlap del chunk anterior.
            if ($length - $offset <= self::CHUNK_OVERLAP) {
                break;
            }

            $chunk = mb_substr($texto, $offset, self::CHUNK_SIZE);

            if ($offset + self::CHUNK_SIZE < $length) {
                $corte = mb_strrpos($chunk, "\n\n");
                if ($corte === false || $corte < self::CHUNK_SIZE * 0.5) {
                    $corte = mb_strrpos($chunk, '. ');
                }
                if ($corte !== false && $corte > self::CHUNK_SIZE * 0.5) {
                    $chunk = mb_substr($chunk, 0, $corte + 1);
                }
            }

            $chunks[]  = trim($chunk);
            $chunkLen  = mb_strlen($chunk);
            $offset   += max(1, $chunkLen - self::CHUNK_OVERLAP);
        }

        return array_filter($chunks, fn($c) => mb_strlen(trim($c)) > 50);
    }

    // ── Embedding ────────────────────────────────────────────────────────────

    private function generarEmbedding(string $texto, string $apiKey): ?array
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-001:embedContent?key={$apiKey}";

        try {
            $response = Http::timeout(15)->post($url, [
                'content'  => ['parts' => [['text' => mb_substr($texto, 0, 8000)]]],
                'taskType' => 'RETRIEVAL_DOCUMENT',
            ]);

            if (!$response->successful()) {
                Log::warning('rit:indexar-ejemplos — embedding fallido', [
                    'status' => $response->status(),
                ]);
                return null;
            }

            $values = $response->json('embedding.values');
            return is_array($values) && !empty($values) ? $values : null;
        } catch (\Exception $e) {
            Log::error('rit:indexar-ejemplos — excepción embedding', ['error' => $e->getMessage()]);
            return null;
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function slugFromFilename(string $nombre): string
    {
        $base  = pathinfo($nombre, PATHINFO_FILENAME);
        $base  = strtolower($base);
        $base  = preg_replace('/[^a-z0-9]+/', '-', $base);
        $base  = trim($base, '-');
        $short = mb_substr($base, 0, 20);
        $hash  = substr(md5($base), 0, 6); // hash único por nombre de archivo
        return "{$short}-{$hash}"; // ej: reglamento-interno-de-a1b2c3 → 27 chars max
        // RIT-REF-(27)-(4) = 39 chars < 50 limit
    }

    private function tituloDesdeFuente(string $fuente, string $nombreArchivo, int $seccion): string
    {
        $calidad   = str_contains($fuente, 'EXCELENTE') ? 'Excelente calidad' : 'Mala calidad';
        $empresa   = pathinfo($nombreArchivo, PATHINFO_FILENAME);
        return "RIT Referencia ({$calidad}) — {$empresa} § {$seccion}";
    }
}
