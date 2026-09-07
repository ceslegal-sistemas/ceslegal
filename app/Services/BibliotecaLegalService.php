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
     * Procesa un DocumentoLegal a partir de texto ya extraído (sin archivo):
     * fragmenta, genera embeddings y guarda. Lo usa el importador de jurisprudencia
     * (scraping de páginas estáticas de la Corte Constitucional).
     */
    public function procesarTexto(DocumentoLegal $documento, string $texto): void
    {
        $documento->update(['estado' => 'procesando', 'error_mensaje' => null]);

        try {
            $texto = $this->limpiarTexto($texto);

            if (empty(trim($texto))) {
                throw new \RuntimeException('El texto está vacío después de limpiarlo.');
            }

            $fragmentos = $this->chunkear($texto);

            if (empty($fragmentos)) {
                throw new \RuntimeException('El texto no generó fragmentos válidos.');
            }

            $documento->fragmentos()->delete();

            $guardados = 0;
            foreach ($fragmentos as $orden => $contenido) {
                FragmentoDocumento::create([
                    'documento_legal_id' => $documento->id,
                    'orden'              => $orden + 1,
                    'contenido'          => $contenido,
                    'embedding'          => $this->obtenerEmbedding($contenido),
                ]);
                $guardados++;
            }

            $documento->update([
                'estado'           => 'procesado',
                'total_fragmentos' => $guardados,
                'total_palabras'   => str_word_count($texto),
                'error_mensaje'    => null,
            ]);

            Log::info('BibliotecaLegal: documento procesado desde texto', [
                'id' => $documento->id, 'titulo' => $documento->titulo, 'fragmentos' => $guardados,
            ]);

        } catch (\Throwable $e) {
            $documento->update(['estado' => 'error', 'error_mensaje' => $e->getMessage()]);
            Log::error('BibliotecaLegal: error procesando texto', ['id' => $documento->id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Busca los fragmentos más relevantes para un texto dado.
     * Retorna un bloque de texto listo para incluir en el prompt de la IA.
     *
     * @param  string     $texto    Texto de búsqueda (hechos del proceso, etc.)
     * @param  int        $limite   Máximo de fragmentos a retornar
     * @param  float      $umbral   Score mínimo de similitud coseno (0-1)
     * @param  array<int> $temaIds  IDs de TemaNormativo a priorizar (boost, no
     *                              filtro - ver reordenarPorTema()). Vacío = mismo
     *                              comportamiento que antes de este parámetro.
     */
    public function buscarFragmentos(string $texto, int $limite = 5, float $umbral = 0.60, array $temaIds = []): string
    {
        if (empty(trim($texto)) || empty($this->apiKey)) {
            return '';
        }

        try {
            $queryEmbedding = $this->obtenerEmbeddingQuery($texto);

            if (empty($queryEmbedding)) {
                return '';
            }

            // Fase 1: puntuar candidatos (solo [id, embedding]) sobre documentos activos.
            $q = FragmentoDocumento::whereNotNull('embedding')
                ->whereHas('documentoLegal', fn($q) => $q->activos()->procesados());

            // Con temas declarados se pide un pool más grande para poder
            // reordenar sin perder cobertura - VectorSearch::topK() igual
            // recorre todos los candidatos para puntuarlos, así que pedir
            // más del top no cuesta una segunda pasada.
            $poolK = empty($temaIds) ? $limite : min($limite * 3, 30);
            $top = \App\Support\VectorSearch::topK($q, $queryEmbedding, $poolK, $umbral);

            if (empty($top)) {
                return '';
            }

            $top = empty($temaIds)
                ? array_slice($top, 0, $limite)
                : $this->reordenarPorTema($top, $temaIds, $limite);

            // Fase 2: hidratar solo el top-K con su documento, conservando el orden por score.
            $ids = array_column($top, 'key');
            $frags = FragmentoDocumento::whereIn('id', $ids)
                ->with('documentoLegal:id,titulo,tipo,referencia')
                ->get()
                ->keyBy('id');

            // Construir bloque de texto con citas
            $lineas = [];
            foreach ($top as $t) {
                $f = $frags[$t['key']] ?? null;
                if (!$f) {
                    continue;
                }
                $doc   = $f->documentoLegal;
                $cita  = $doc->referencia ? "{$doc->titulo} ({$doc->referencia})" : $doc->titulo;
                $score = number_format($t['score'] * 100, 0);

                $lineas[] = "--- [{$cita}] (relevancia: {$score}%) ---";
                $lineas[] = trim($f->contenido);
                $lineas[] = '';
            }

            return trim(implode("\n", $lineas));

        } catch (\Throwable $e) {
            Log::warning('BibliotecaLegal::buscarFragmentos error', ['error' => $e->getMessage()]);
            return '';
        }
    }

    /**
     * Reordena el pool de candidatos priorizando los fragmentos cuyo
     * DocumentoLegal está clasificado en alguno de $temaIds (ver
     * TemaClasificadorService), preservando el orden por score dentro de
     * cada grupo. Es un boost, no un filtro: un fragmento que ya pasó el
     * umbral de similitud nunca se descarta, solo puede quedar después de
     * los que sí coinciden con el tema - así una clasificación incompleta
     * o fallida no le quita cobertura a la búsqueda semántica.
     *
     * @param  array<int, array{key: mixed, score: float}> $top
     * @param  array<int>                                  $temaIds
     * @return array<int, array{key: mixed, score: float}>
     */
    private function reordenarPorTema(array $top, array $temaIds, int $limite): array
    {
        $fragmentoIds = array_column($top, 'key');
        $documentoIdPorFragmento = FragmentoDocumento::whereIn('id', $fragmentoIds)
            ->pluck('documento_legal_id', 'id');

        $documentoIdsConTema = DocumentoLegal::whereIn('id', $documentoIdPorFragmento->unique()->values())
            ->whereHas('temasNormativos', fn($q) => $q->whereIn('temas_normativos.id', $temaIds))
            ->pluck('id')
            ->all();

        $conTema = [];
        $sinTema = [];
        foreach ($top as $t) {
            $documentoId = $documentoIdPorFragmento[$t['key']] ?? null;
            if ($documentoId && in_array($documentoId, $documentoIdsConTema, true)) {
                $conTema[] = $t;
            } else {
                $sinTema[] = $t;
            }
        }

        return array_slice(array_merge($conTema, $sinTema), 0, $limite);
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

        return $this->extraerTextoDeArchivo($rutaAbsoluta, $extension);
    }

    /**
     * Igual que extraerTexto(), pero recibe la ruta+extensión directamente
     * en vez de un DocumentoLegal ya persistido - reutilizable para
     * autocompletar el formulario de creación a partir del archivo
     * temporal que Livewire sube ANTES de guardar el registro (ver
     * BibliotecaLegalResource, sugerencia de metadatos al subir el
     * archivo). La extensión se pasa explícita porque el nombre del
     * archivo temporal de Livewire no siempre la conserva de forma
     * confiable en la ruta.
     */
    public function extraerTextoDeArchivo(string $rutaAbsoluta, string $extension): string
    {
        $extension = strtolower($extension);

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

    /** Embedding de un texto para ALMACENAR (RETRIEVAL_DOCUMENT). Reutilizable. */
    public function embedDocumento(string $texto): ?array
    {
        return $this->obtenerEmbedding($texto);
    }

    /** Embedding de una consulta (RETRIEVAL_QUERY). Reutilizable. */
    public function embedConsulta(string $texto): ?array
    {
        return $this->obtenerEmbeddingQuery($texto);
    }

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

        // Cachear por texto + taskType: mismo texto nunca necesita recalcularse en 24h.
        $cacheKey = 'emb_' . $taskType . '_' . md5($texto);

        return \Illuminate\Support\Facades\Cache::remember($cacheKey, now()->addHours(24), function () use ($texto, $taskType) {
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
        });
    }

    /**
     * Variante SIN el fallback a Gemini Vision, para el autocompletado del
     * formulario de creación (sugerirMetadatos()): un PDF escaneado que
     * necesite OCR no debe pagarse dos veces (una para la sugerencia previa
     * y otra en el procesamiento real vía "Encolar"). Si la extracción
     * nativa no rinde texto suficiente, devuelve null y el formulario
     * simplemente queda sin autocompletar - nunca bloquea la subida.
     */
    public function extraerTextoRapidoSinIA(string $rutaAbsoluta, string $extension): ?string
    {
        try {
            return match (strtolower($extension)) {
                'pdf' => (function () use ($rutaAbsoluta) {
                    $parser = new \Smalot\PdfParser\Parser();
                    $texto = $this->limpiarTexto($parser->parseFile($rutaAbsoluta)->getText());
                    return mb_strlen($texto) >= 200 ? $texto : null;
                })(),
                'docx' => $this->extraerTextoDocx($rutaAbsoluta),
                'txt'  => $this->limpiarTexto(file_get_contents($rutaAbsoluta)),
                default => null,
            };
        } catch (\Throwable $e) {
            Log::info('BibliotecaLegal: extraerTextoRapidoSinIA falló, se deja el formulario sin autocompletar', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    // ─── Sugerencia de metadatos al subir (autocompletar) ───────────────────────

    /**
     * Sugiere titulo/tipo/referencia/fecha_expedicion/incorporar_completo a
     * partir del texto ya extraído del archivo - se llama justo después de
     * subir el archivo en el formulario de creación, ANTES de guardar nada,
     * para ahorrarle trabajo al abogado. Nunca lanza excepción: si Gemini
     * falla o no hay cuota, devuelve [] y el formulario queda igual que
     * hoy (sin sugerencias), nunca bloquea la subida.
     *
     * @return array{titulo?: string, tipo?: string, referencia?: string, fecha_expedicion?: string, incorporar_completo?: bool, justificacion_incorporar_completo?: string}
     */
    public function sugerirMetadatos(string $texto): array
    {
        $texto = trim($texto);
        if ($texto === '' || empty($this->apiKey)) {
            return [];
        }

        // Título, tipo, fecha y la frase "parte integral del RIT" casi
        // siempre aparecen en la primera página/sección - no hace falta (ni
        // conviene, por cuota) mandar el documento completo.
        $extracto = mb_substr($texto, 0, 6000);
        $tiposValidos = implode(', ', array_keys(DocumentoLegal::$tiposLabels));

        $prompt = <<<PROMPT
        Eres un asistente que ayuda a un abogado laboral colombiano a
        catalogar un documento legal recién subido a una biblioteca legal.

        Lee este extracto (puede ser el inicio de una sentencia, ley,
        concepto o política) y sugiere metadatos para catalogarlo. Si algún
        dato no es identificable con confianza en el texto, usa null para
        ese campo - nunca inventes.

        EXTRACTO DEL DOCUMENTO:
        {$extracto}

        Responde ÚNICAMENTE con un JSON válido, sin texto adicional ni
        bloques de código markdown, con esta forma exacta:
        {
          "titulo": "<título corto y descriptivo, o null>",
          "tipo": "<uno exacto de: {$tiposValidos}, o null si no es claro>",
          "referencia": "<número de sentencia/ley/radicado si aparece, o null>",
          "fecha_expedicion": "<fecha en formato YYYY-MM-DD si aparece explícita, o null>",
          "incorporar_completo": true o false,
          "justificacion_incorporar_completo": "<breve, solo si incorporar_completo es true: qué frase del texto indica que debe incorporarse completo, ej. 'declara ser parte integral del Reglamento'>"
        }
        PROMPT;

        try {
            $respuesta = $this->llamarGeminiTexto($prompt);
            $limpio = preg_replace('/^```json\s*|\s*```$/i', '', trim($respuesta)) ?? $respuesta;
            $datos = json_decode($limpio, true);

            if (!is_array($datos)) {
                return [];
            }

            $sugerencia = [];

            if (!empty($datos['titulo'])) {
                $sugerencia['titulo'] = trim((string) $datos['titulo']);
            }
            if (!empty($datos['tipo']) && array_key_exists($datos['tipo'], DocumentoLegal::$tiposLabels)) {
                $sugerencia['tipo'] = $datos['tipo'];
            }
            if (!empty($datos['referencia'])) {
                $sugerencia['referencia'] = trim((string) $datos['referencia']);
            }
            if (!empty($datos['fecha_expedicion']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $datos['fecha_expedicion'])) {
                $sugerencia['fecha_expedicion'] = $datos['fecha_expedicion'];
            }
            if (!empty($datos['incorporar_completo'])) {
                $sugerencia['incorporar_completo'] = true;
                $sugerencia['justificacion_incorporar_completo'] = (string) ($datos['justificacion_incorporar_completo'] ?? '');
            }

            return $sugerencia;

        } catch (\Throwable $e) {
            Log::warning('BibliotecaLegal: sugerirMetadatos falló, se deja el formulario sin autocompletar', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /** Mismo patrón de llamada a Gemini (cascada + reintento) ya usado en RitActualizacionAutomaticaService/TemaClasificadorService. */
    protected function llamarGeminiTexto(string $prompt): string
    {
        $modelosCascada = ['gemini-2.5-flash', 'gemini-2.5-flash-lite'];
        $lastError = null;

        foreach ($modelosCascada as $model) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$this->apiKey}";

            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(30)
                ->post($url, [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => [
                        'temperature'     => 0.1,
                        'maxOutputTokens' => 1024,
                        'thinkingConfig'  => ['thinkingBudget' => 0],
                    ],
                ]);

            if ($response->successful()) {
                $texto = $response->json('candidates.0.content.parts.0.text') ?? '';
                if (!empty($texto)) {
                    return trim($texto);
                }
            }

            $lastError = $response->body();
        }

        throw new \RuntimeException('No se pudo sugerir metadatos con IA: ' . $lastError);
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
