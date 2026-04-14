<?php

namespace App\Services;

use App\Models\DocumentoLegal;
use App\Models\FragmentoDocumento;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BibliotecaLegalService
{
    // Palabras aproximadas por fragmento. Gemini embedding acepta hasta ~2048 tokens (~1500 palabras).
    // 600 palabras: tamaño óptimo para precisión de recuperación.
    const PALABRAS_POR_FRAGMENTO = 600;

    // Solapamiento entre fragmentos (palabras) para no perder contexto en los bordes.
    const PALABRAS_SOLAPAMIENTO = 80;

    // Modelo de embeddings de Gemini
    const EMBEDDING_MODEL = 'gemini-embedding-001';

    protected string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.ia.gemini.api_key', '');
    }

    // ─── API pública ─────────────────────────────────────────────────────────────

    /**
     * Procesa un DocumentoLegal completo:
     * 1. Extrae texto del archivo (PDF o DOCX)
     * 2. Divide en fragmentos
     * 3. Genera embeddings para cada fragmento
     * 4. Guarda los fragmentos en BD
     */
    public function procesarDocumento(DocumentoLegal $documento): void
    {
        $documento->update(['estado' => 'procesando', 'error_mensaje' => null]);

        try {
            // 1. Extraer texto
            $texto = $this->extraerTexto($documento);

            if (empty(trim($texto))) {
                throw new \RuntimeException('No se pudo extraer texto del documento. El archivo puede estar dañado o protegido.');
            }

            // 2. Fragmentar
            $fragmentos = $this->chunkear($texto);

            if (empty($fragmentos)) {
                throw new \RuntimeException('El texto extraído no generó fragmentos válidos.');
            }

            // 3. Eliminar fragmentos anteriores si se reprocesa
            $documento->fragmentos()->delete();

            // 4. Generar embeddings y guardar
            $guardados = 0;
            foreach ($fragmentos as $orden => $contenido) {
                $embedding = $this->obtenerEmbedding($contenido);

                FragmentoDocumento::create([
                    'documento_legal_id' => $documento->id,
                    'orden'              => $orden + 1,
                    'contenido'          => $contenido,
                    'embedding'          => $embedding,
                ]);

                $guardados++;
            }

            $totalPalabras = str_word_count($texto);

            $documento->update([
                'estado'           => 'procesado',
                'total_fragmentos' => $guardados,
                'total_palabras'   => $totalPalabras,
                'error_mensaje'    => null,
            ]);

            Log::info('BibliotecaLegal: documento procesado', [
                'id'         => $documento->id,
                'titulo'     => $documento->titulo,
                'fragmentos' => $guardados,
                'palabras'   => $totalPalabras,
            ]);

        } catch (\Throwable $e) {
            $documento->update([
                'estado'        => 'error',
                'error_mensaje' => $e->getMessage(),
            ]);

            Log::error('BibliotecaLegal: error procesando documento', [
                'id'    => $documento->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Busca los fragmentos más relevantes para un texto dado.
     * Retorna un bloque de texto listo para inyectar en el prompt de la IA.
     *
     * @param  string $texto    Texto de búsqueda (hechos del proceso, etc.)
     * @param  int    $limite   Máximo de fragmentos a retornar
     * @param  float  $umbral   Score mínimo de similitud coseno (0-1)
     */
    public function buscarFragmentos(string $texto, int $limite = 5, float $umbral = 0.60): string
    {
        if (empty(trim($texto)) || empty($this->apiKey)) {
            return '';
        }

        try {
            $queryEmbedding = $this->obtenerEmbeddingQuery($texto);

            if (empty($queryEmbedding)) {
                return '';
            }

            // Cargar solo fragmentos con embedding de documentos activos y procesados
            $fragmentos = FragmentoDocumento::whereNotNull('embedding')
                ->whereHas('documentoLegal', fn($q) => $q->activos()->procesados())
                ->with('documentoLegal:id,titulo,tipo,referencia')
                ->get();

            if ($fragmentos->isEmpty()) {
                return '';
            }

            // Calcular similitud coseno
            $scored = $fragmentos
                ->map(function (FragmentoDocumento $f) use ($queryEmbedding) {
                    $emb = $f->embedding;
                    if (empty($emb)) return null;
                    return [
                        'fragmento' => $f,
                        'score'     => $this->cosineSimilarity($queryEmbedding, $emb),
                    ];
                })
                ->filter(fn($item) => $item && $item['score'] >= $umbral)
                ->sortByDesc('score')
                ->take($limite)
                ->values();

            if ($scored->isEmpty()) {
                return '';
            }

            // Construir bloque de texto con citas
            $lineas = [];
            foreach ($scored as $item) {
                $doc   = $item['fragmento']->documentoLegal;
                $cita  = $doc->referencia ? "{$doc->titulo} ({$doc->referencia})" : $doc->titulo;
                $score = number_format($item['score'] * 100, 0);

                $lineas[] = "--- [{$cita}] (relevancia: {$score}%) ---";
                $lineas[] = trim($item['fragmento']->contenido);
                $lineas[] = '';
            }

            return trim(implode("\n", $lineas));

        } catch (\Throwable $e) {
            Log::warning('BibliotecaLegal::buscarFragmentos error', ['error' => $e->getMessage()]);
            return '';
        }
    }

    // ─── Extracción de texto ─────────────────────────────────────────────────────

    public function extraerTexto(DocumentoLegal $documento): string
    {
        if (empty($documento->archivo_path)) {
            throw new \RuntimeException('El documento no tiene archivo adjunto.');
        }

        $rutaAbsoluta = storage_path('app/public/' . $documento->archivo_path);

        if (!file_exists($rutaAbsoluta)) {
            throw new \RuntimeException("Archivo no encontrado: {$rutaAbsoluta}");
        }

        $extension = strtolower(pathinfo($rutaAbsoluta, PATHINFO_EXTENSION));

        return match ($extension) {
            'pdf'        => $this->extraerTextoPDF($rutaAbsoluta),
            'docx'       => $this->extraerTextoDocx($rutaAbsoluta),
            'txt'        => file_get_contents($rutaAbsoluta),
            default      => throw new \RuntimeException("Formato no soportado: .{$extension}. Use PDF, DOCX o TXT."),
        };
    }

    protected function extraerTextoPDF(string $ruta): string
    {
        // 1. Intentar extracción de texto nativa (PDF con texto embebido)
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf    = $parser->parseFile($ruta);
            $texto  = $this->limpiarTexto($pdf->getText());

            if (mb_strlen($texto) >= 200) {
                return $texto;
            }
        } catch (\Throwable $e) {
            Log::info('BibliotecaLegal: parser PDF falló, usando Gemini Vision', ['error' => $e->getMessage()]);
        }

        // 2. Fallback: PDF escaneado → Gemini Vision
        if (empty($this->apiKey)) {
            throw new \RuntimeException('PDF sin texto extraíble y sin API key para usar Vision. Configure la clave de Gemini.');
        }

        Log::info('BibliotecaLegal: PDF escaneado detectado, usando Gemini Vision', ['archivo' => basename($ruta)]);
        return $this->extraerTextoConGeminiVision($ruta);
    }

    protected function extraerTextoConGeminiVision(string $ruta): string
    {
        $tamano = filesize($ruta);

        // PDFs grandes (>15 MB) → Files API de Gemini
        if ($tamano > 15 * 1024 * 1024) {
            return $this->extraerTextoConGeminiFilesAPI($ruta);
        }

        // PDFs pequeños/medianos → inline base64
        $base64 = base64_encode(file_get_contents($ruta));

        $response = Http::timeout(180)
            ->post(
                "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$this->apiKey}",
                [
                    'contents' => [[
                        'parts' => [
                            [
                                'inline_data' => [
                                    'mime_type' => 'application/pdf',
                                    'data'      => $base64,
                                ],
                            ],
                            [
                                'text' => 'Extrae TODO el texto de este documento de forma literal y completa. No resumas ni omitas nada. Transcribe el contenido íntegro tal como aparece. Devuelve SOLO el texto extraído, sin comentarios ni explicaciones adicionales.',
                            ],
                        ],
                    ]],
                ]
            );

        if ($response->successful()) {
            $texto = $response->json('candidates.0.content.parts.0.text') ?? '';
            return $this->limpiarTexto($texto);
        }

        Log::warning('BibliotecaLegal: Gemini Vision inline falló', [
            'status' => $response->status(),
            'body'   => substr($response->body(), 0, 300),
        ]);

        throw new \RuntimeException(
            'No se pudo extraer texto del PDF. Status: ' . $response->status()
        );
    }

    protected function extraerTextoConGeminiFilesAPI(string $ruta): string
    {
        $contenido     = file_get_contents($ruta);
        $tamano        = strlen($contenido);
        $nombreArchivo = basename($ruta);

        // 1. Iniciar subida resumable
        $initResponse = Http::withHeaders([
            'X-Goog-Upload-Protocol'              => 'resumable',
            'X-Goog-Upload-Command'               => 'start',
            'X-Goog-Upload-Header-Content-Length' => $tamano,
            'X-Goog-Upload-Header-Content-Type'   => 'application/pdf',
            'Content-Type'                         => 'application/json',
        ])->post(
            "https://generativelanguage.googleapis.com/upload/v1beta/files?key={$this->apiKey}",
            ['file' => ['display_name' => $nombreArchivo]]
        );

        if (!$initResponse->successful()) {
            throw new \RuntimeException('No se pudo iniciar subida a Gemini Files API.');
        }

        $uploadUrl = $initResponse->header('X-Goog-Upload-URL');

        // 2. Subir el archivo completo
        $uploadResponse = Http::timeout(120)->withHeaders([
            'Content-Length'         => $tamano,
            'X-Goog-Upload-Offset'   => '0',
            'X-Goog-Upload-Command'  => 'upload, finalize',
        ])->withBody($contenido, 'application/pdf')->put($uploadUrl);

        if (!$uploadResponse->successful()) {
            throw new \RuntimeException('Error subiendo PDF a Gemini Files API.');
        }

        $fileUri  = $uploadResponse->json('file.uri');
        $fileName = $uploadResponse->json('file.name'); // e.g. "files/abc123"

        try {
            // 3. Extraer texto desde el archivo subido
            $response = Http::timeout(180)->post(
                "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$this->apiKey}",
                [
                    'contents' => [[
                        'parts' => [
                            ['file_data' => ['mime_type' => 'application/pdf', 'file_uri' => $fileUri]],
                            ['text' => 'Extrae TODO el texto de este documento de forma literal y completa. No resumas ni omitas nada. Transcribe el contenido íntegro tal como aparece. Devuelve SOLO el texto extraído, sin comentarios ni explicaciones adicionales.'],
                        ],
                    ]],
                ]
            );

            if (!$response->successful()) {
                throw new \RuntimeException('Gemini Vision no pudo leer el PDF. Status: ' . $response->status());
            }

            $texto = $response->json('candidates.0.content.parts.0.text') ?? '';
            return $this->limpiarTexto($texto);

        } finally {
            // 4. Eliminar el archivo de Gemini (se auto-elimina a las 48h pero limpiamos ya)
            if ($fileName) {
                Http::delete(
                    "https://generativelanguage.googleapis.com/v1beta/{$fileName}?key={$this->apiKey}"
                );
            }
        }
    }

    protected function extraerTextoDocx(string $ruta): string
    {
        $phpWord  = \PhpOffice\PhpWord\IOFactory::load($ruta);
        $partes   = [];

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
                    $partes[] = $element->getText();
                } elseif ($element instanceof \PhpOffice\PhpWord\Element\Text) {
                    $partes[] = $element->getText();
                } elseif (method_exists($element, 'getText')) {
                    $partes[] = $element->getText();
                }
            }
        }

        return $this->limpiarTexto(implode("\n", $partes));
    }

    protected function limpiarTexto(string $texto): string
    {
        // Normalizar saltos de línea
        $texto = str_replace(["\r\n", "\r"], "\n", $texto);
        // Colapsar líneas en blanco múltiples (más de 2 seguidas)
        $texto = preg_replace('/\n{3,}/', "\n\n", $texto);
        // Eliminar espacios al inicio/fin de cada línea
        $texto = implode("\n", array_map('trim', explode("\n", $texto)));

        return trim($texto);
    }

    // ─── Fragmentación (chunking) ────────────────────────────────────────────────

    /**
     * Divide el texto en fragmentos de ~PALABRAS_POR_FRAGMENTO palabras,
     * respetando párrafos y con solapamiento para no perder contexto.
     *
     * @return string[]
     */
    public function chunkear(string $texto): array
    {
        // Separar por párrafos (doble salto de línea)
        $parrafos = preg_split('/\n\s*\n/', $texto, -1, PREG_SPLIT_NO_EMPTY);
        $parrafos = array_map('trim', $parrafos);
        $parrafos = array_filter($parrafos, fn($p) => str_word_count($p) >= 5);
        $parrafos = array_values($parrafos);

        if (empty($parrafos)) {
            // Fallback: dividir por líneas si no hay párrafos
            $parrafos = array_filter(
                explode("\n", $texto),
                fn($l) => str_word_count(trim($l)) >= 5
            );
            $parrafos = array_values($parrafos);
        }

        $fragmentos = [];
        $buffer     = [];
        $palabrasBuffer = 0;
        $solapamiento   = []; // últimas palabras del fragmento anterior

        foreach ($parrafos as $parrafo) {
            $palabrasParrafo = str_word_count($parrafo);

            // Si agregar este párrafo supera el límite, cerrar fragmento
            if ($palabrasBuffer + $palabrasParrafo > self::PALABRAS_POR_FRAGMENTO && !empty($buffer)) {
                $contenido = implode("\n\n", $buffer);
                // Añadir solapamiento al inicio si existe
                if (!empty($solapamiento)) {
                    $contenido = implode(' ', $solapamiento) . "\n\n" . $contenido;
                }
                $fragmentos[] = trim($contenido);

                // Preparar solapamiento: últimas N palabras del fragmento cerrado
                $palabrasCierre = explode(' ', implode(' ', $buffer));
                $solapamiento   = array_slice($palabrasCierre, -self::PALABRAS_SOLAPAMIENTO);

                $buffer       = [$parrafo];
                $palabrasBuffer = $palabrasParrafo;
            } else {
                $buffer[]       = $parrafo;
                $palabrasBuffer += $palabrasParrafo;
            }
        }

        // Último fragmento
        if (!empty($buffer)) {
            $contenido = implode("\n\n", $buffer);
            if (!empty($solapamiento)) {
                $contenido = implode(' ', $solapamiento) . "\n\n" . $contenido;
            }
            $fragmentos[] = trim($contenido);
        }

        return array_values(array_filter($fragmentos, fn($f) => str_word_count($f) >= 20));
    }

    // ─── Embeddings ──────────────────────────────────────────────────────────────

    protected function obtenerEmbedding(string $texto): ?array
    {
        return $this->llamarEmbeddingApi($texto, 'RETRIEVAL_DOCUMENT');
    }

    protected function obtenerEmbeddingQuery(string $texto): ?array
    {
        return $this->llamarEmbeddingApi($texto, 'RETRIEVAL_QUERY');
    }

    protected function llamarEmbeddingApi(string $texto, string $taskType): ?array
    {
        if (empty($this->apiKey)) {
            return null;
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/" . self::EMBEDDING_MODEL . ":embedContent?key={$this->apiKey}";

        try {
            $response = Http::timeout(20)->post($url, [
                'model'   => 'models/' . self::EMBEDDING_MODEL,
                'content' => ['parts' => [['text' => mb_substr($texto, 0, 8000)]]],
                'taskType' => $taskType,
            ]);

            if (!$response->successful()) {
                Log::warning('BibliotecaLegal: embedding API error', [
                    'status' => $response->status(),
                    'body'   => substr($response->body(), 0, 300),
                ]);
                return null;
            }

            $values = $response->json('embedding.values');
            return is_array($values) && !empty($values) ? $values : null;

        } catch (\Throwable $e) {
            Log::warning('BibliotecaLegal: embedding excepción', ['error' => $e->getMessage()]);
            return null;
        }
    }

    protected function cosineSimilarity(array $a, array $b): float
    {
        $dot  = 0.0;
        $magA = 0.0;
        $magB = 0.0;
        $n    = min(count($a), count($b));

        for ($i = 0; $i < $n; $i++) {
            $dot  += $a[$i] * $b[$i];
            $magA += $a[$i] * $a[$i];
            $magB += $b[$i] * $b[$i];
        }

        $mag = sqrt($magA) * sqrt($magB);
        return $mag > 0 ? $dot / $mag : 0.0;
    }
}
