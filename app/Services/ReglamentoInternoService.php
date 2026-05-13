<?php

namespace App\Services;

use App\Models\ReglamentoInterno;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\IOFactory;
use Smalot\PdfParser\Parser as PdfParser;

class ReglamentoInternoService
{
    /**
     * Procesa un archivo (.docx o .pdf), extrae el texto y lo guarda en BD.
     *
     * La extracción de texto es opcional — si falla, el registro se crea de
     * todas formas con activo=true para que la empresa quede con RIT activo.
     */
    /**
     * @param  string      $rutaArchivo    Ruta absoluta al archivo (temp o permanente)
     * @param  int         $empresaId
     * @param  string      $nombreOriginal Nombre visible del archivo
     * @param  string|null $rutaRelativa   Ruta relativa en disco 'local' para guardar en ruta_docx
     */
    public function procesarDocumento(
        string $rutaArchivo,
        int    $empresaId,
        string $nombreOriginal,
        ?string $rutaRelativa = null,
    ): ReglamentoInterno {
        $texto = '';

        try {
            $extension = strtolower(pathinfo($rutaArchivo, PATHINFO_EXTENSION));

            $texto = match ($extension) {
                'pdf'  => $this->extraerTextoPdf($rutaArchivo),
                default => $this->extraerTextoDocx($rutaArchivo),
            };
        } catch (\Exception $e) {
            // La extracción de texto falla con gracia — el RIT aún se registra
            Log::warning('ReglamentoInternoService: no se pudo extraer texto del documento', [
                'empresa_id' => $empresaId,
                'archivo'    => basename($rutaArchivo),
                'error'      => $e->getMessage(),
            ]);
        }

        $campos = [
            'nombre'         => $nombreOriginal,
            'texto_completo' => $texto ?: null,
            'activo'         => true,
            'fuente'         => 'subido',
        ];

        // Si se proporciona una ruta permanente, guardarla para descarga directa
        if ($rutaRelativa) {
            $campos['ruta_docx'] = $rutaRelativa;
        }

        // Al subir un nuevo RIT manual, limpiar sanciones previas para forzar re-extracción
        $campos['sanciones_extraidas'] = null;

        $reglamento = ReglamentoInterno::updateOrCreate(
            ['empresa_id' => $empresaId],
            $campos
        );

        Log::info('ReglamentoInternoService: documento registrado', [
            'empresa_id' => $empresaId,
            'nombre'     => $nombreOriginal,
            'chars'      => strlen($texto),
        ]);

        // Extraer sanciones inmediatamente si hay texto disponible
        if (!empty($texto)) {
            $this->extraerYPersistirSanciones($reglamento);
        }

        return $reglamento;
    }

    /**
     * Extrae faltas y sanciones del RIT para el correo de citación y procesos disciplinarios.
     *
     * - RIT wizard (respuestas_cuestionario) → devuelve datos ya estructurados, sin IA.
     * - RIT manual con sanciones_extraidas   → devuelve datos ya guardados en BD.
     * - RIT manual sin sanciones_extraidas   → extrae con Gemini y persiste en BD.
     * - Sin texto_completo                   → array vacío (tabla no se mostrará).
     *
     * Siempre retorna strings legibles en español.
     * Estructura: ['faltas_leves' => [...], 'faltas_graves' => [...], 'sanciones' => [...]]
     */
    public function extraerSancionesParaEmail(ReglamentoInterno $rit): array
    {
        // ── Caso 1: wizard (construido_ia) — datos ya estructurados ───────────
        // Solo se usa si la fuente activa es el wizard; si se subió un documento
        // posterior, respuestas_cuestionario puede seguir existiendo pero no es
        // la fuente vigente.
        if ($rit->fuente !== 'subido') {
            $cuestionario = $rit->respuestas_cuestionario ?? [];
            if (!empty($cuestionario['faltas_leves']) || !empty($cuestionario['faltas_graves'])) {
                $mapa         = $this->mapaClavesSanciones();
                $sancionesRaw = $cuestionario['sanciones_contempladas'] ?? $cuestionario['sanciones'] ?? [];
                return [
                    'faltas_leves'  => $cuestionario['faltas_leves']  ?? [],
                    'faltas_graves' => $cuestionario['faltas_graves'] ?? [],
                    'sanciones'     => array_map(fn($s) => $mapa[$s] ?? $s, $sancionesRaw),
                ];
            }
        }

        // ── Caso 2: documento subido con extracción ya guardada en BD ──────────
        if (!empty($rit->sanciones_extraidas)) {
            return $rit->sanciones_extraidas;
        }

        // ── Caso 3: documento subido sin extracción — extraer con IA y persistir
        if (empty($rit->texto_completo)) {
            return [];
        }

        return $this->extraerYPersistirSanciones($rit);
    }

    /**
     * Extrae sanciones con IA y las guarda en reglamentos_internos.sanciones_extraidas.
     * Retorna el array resultante (vacío si falla).
     */
    public function extraerYPersistirSanciones(ReglamentoInterno $rit): array
    {
        $datos = $this->extraerSancionesConIA($rit->texto_completo ?? '');

        if (!empty($datos)) {
            $rit->sanciones_extraidas = $datos;
            $rit->saveQuietly(); // sin disparar eventos/observers
        }

        return $datos;
    }

    /**
     * Extrae el capítulo disciplinario del texto y solicita a Gemini la estructura de faltas.
     */
    private function extraerSancionesConIA(string $textoRIT): array
    {
        $fragmento = $this->extraerCapituloDisciplinario($textoRIT);

        if (empty($fragmento)) {
            Log::info('ReglamentoInternoService: capítulo disciplinario no encontrado en texto del RIT');
            return [];
        }

        $prompt = <<<PROMPT
Analiza el siguiente capítulo del Reglamento Interno de Trabajo de una empresa colombiana y extrae la lista de faltas laborales.

TEXTO DEL REGLAMENTO:
{$fragmento}

Responde ÚNICAMENTE con un JSON válido, sin texto adicional, con esta estructura exacta:
{
  "faltas_leves": ["descripción concreta de falta 1", "descripción concreta de falta 2"],
  "faltas_graves": ["descripción concreta de falta 1", "descripción concreta de falta 2"],
  "sanciones": ["Llamado de Atención Verbal", "Suspensión hasta X días", "Terminación del Contrato"]
}

Reglas:
- faltas_leves y faltas_graves: máximo 8 items cada uno, máximo 100 caracteres por item
- sanciones: texto legible en español, no claves técnicas; incluye solo las sanciones que menciona el RIT
- Si el texto no tiene información clara de faltas, devuelve arrays vacíos
- No listes artículos del CST genéricos; solo lo que describe concretamente este RIT
PROMPT;

        try {
            $respuesta = $this->llamarGeminiJSON($prompt);
            $datos     = $this->parsearJSON($respuesta);

            return [
                'faltas_leves'  => array_slice($datos['faltas_leves']  ?? [], 0, 10),
                'faltas_graves' => array_slice($datos['faltas_graves'] ?? [], 0, 10),
                'sanciones'     => $datos['sanciones'] ?? [],
            ];
        } catch (\Throwable $e) {
            Log::warning('ReglamentoInternoService: error extrayendo sanciones con IA', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Extrae el fragmento de régimen disciplinario del texto completo del RIT.
     * Estrategia 1: por encabezado CAPÍTULO (captura dos capítulos consecutivos: faltas + sanciones).
     * Estrategia 2: palabras clave con contexto (fallback).
     */
    private function extraerCapituloDisciplinario(string $textoRIT): string
    {
        $lineas   = explode("\n", $textoRIT);
        $total    = count($lineas);
        $maxChars = 5000;

        $capitulosRef  = ['RÉGIMEN DISCIPLINARIO', 'REGIMEN DISCIPLINARIO', 'FALTAS', 'SANCIONES', 'ESCALA DE SANCIONES'];
        $palabrasClave = ['falta', 'sanc', 'disciplin', 'descargo', 'amonestac', 'suspens', 'multa'];

        // Estrategia 1: buscar encabezado CAPÍTULO
        $inicio = null;
        foreach ($lineas as $i => $linea) {
            if (!preg_match('/CAP[IÍ]TULO/ui', $linea)) continue;
            $lineaUp = mb_strtoupper($linea);
            foreach ($capitulosRef as $keyword) {
                if (str_contains($lineaUp, mb_strtoupper($keyword))) {
                    $inicio = $i;
                    break 2;
                }
            }
        }

        if ($inicio !== null) {
            $fin = $total;
            $chapterCount = 0;
            for ($i = $inicio + 1; $i < $total; $i++) {
                if (preg_match('/CAP[IÍ]TULO/ui', $lineas[$i])) {
                    $chapterCount++;
                    if ($chapterCount >= 2) { $fin = $i; break; }
                }
            }
            $fragmento = implode("\n", array_slice($lineas, $inicio, $fin - $inicio));
            if (!empty(trim($fragmento))) {
                return mb_substr(trim($fragmento), 0, $maxChars);
            }
        }

        // Estrategia 2: palabras clave con ±10 líneas de contexto
        $indices = [];
        foreach ($lineas as $i => $linea) {
            $lineaNorm = mb_strtolower($linea);
            foreach ($palabrasClave as $clave) {
                if (str_contains($lineaNorm, $clave)) {
                    for ($j = max(0, $i - 5); $j <= min($total - 1, $i + 10); $j++) {
                        $indices[$j] = true;
                    }
                    break;
                }
            }
        }

        if (empty($indices)) return '';

        ksort($indices);
        $fragmento = '';
        $prev = -2;
        foreach (array_keys($indices) as $i) {
            if ($i > $prev + 1) $fragmento .= "\n";
            $fragmento .= $lineas[$i] . "\n";
            $prev = $i;
        }

        return mb_substr(trim($fragmento), 0, $maxChars);
    }

    /** Llama a Gemini solicitando JSON puro; cascada flash → flash-lite. */
    private function llamarGeminiJSON(string $prompt): string
    {
        $apiKey  = config('services.ia.gemini.api_key', '');
        $modelos = ['gemini-2.5-flash', 'gemini-2.5-flash-lite'];

        $payload = [
            'contents'         => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'temperature'      => 0.1,
                'maxOutputTokens'  => 2048,
                'responseMimeType' => 'application/json',
                'thinkingConfig'   => ['thinkingBudget' => 0],
            ],
        ];

        foreach ($modelos as $model) {
            $url      = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(45)
                ->post($url, $payload);

            if ($response->successful()) {
                $parts = $response->json('candidates.0.content.parts', []);
                foreach (array_reverse($parts) as $part) {
                    if (empty($part['thought']) && !empty($part['text'])) {
                        return $part['text'];
                    }
                }
                return $response->json('candidates.0.content.parts.0.text', '');
            }

            Log::warning("ReglamentoInternoService: Gemini {$response->status()} con modelo {$model}");

            if (!in_array($response->status(), [429, 503, 500, 502, 504])) {
                throw new \RuntimeException('Error Gemini (' . $response->status() . '): ' . $response->body());
            }
        }

        throw new \RuntimeException('Todos los modelos Gemini fallaron al extraer sanciones del RIT');
    }

    /** Parsea la respuesta JSON de Gemini, tolerando bloques de código markdown. */
    private function parsearJSON(string $texto): array
    {
        $texto = preg_replace('/^```(?:json)?\s*/i', '', trim($texto));
        $texto = preg_replace('/\s*```$/m', '', $texto);
        $datos = json_decode(trim($texto), true);
        return is_array($datos) ? $datos : [];
    }

    /** Mapa de claves internas del wizard a texto legible en español. */
    private function mapaClavesSanciones(): array
    {
        return [
            'llamado_verbal'  => 'Llamado de Atención Verbal',
            'llamado_escrito' => 'Llamado de Atención Escrito',
            'suspension_1_8'  => 'Suspensión 1 a 8 días sin sueldo',
            'suspension_1_15' => 'Suspensión 1 a 15 días sin sueldo',
            'suspension_1_30' => 'Suspensión 1 a 30 días sin sueldo',
            'suspension_1_40' => 'Suspensión 1 a 40 días sin sueldo',
            'suspension_1_60' => 'Suspensión 1 a 60 días sin sueldo',
            'terminacion'     => 'Terminación del Contrato con Justa Causa',
        ];
    }

    /**
     * Devuelve el texto completo del reglamento activo para una empresa, o null si no existe.
     */
    public function getTextoReglamento(int $empresaId): ?string
    {
        $reglamento = ReglamentoInterno::where('empresa_id', $empresaId)
            ->where('activo', true)
            ->latest()
            ->first();

        return $reglamento?->texto_completo;
    }

    /**
     * Extrae texto plano de un .docx usando PhpWord.
     */
    private function extraerTextoDocx(string $rutaArchivo): string
    {
        $phpWord = IOFactory::load($rutaArchivo);
        $lineas  = [];

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                $lineas[] = $this->elementoATexto($element);
            }
        }

        return trim(implode("\n", array_filter($lineas)));
    }

    /**
     * Extrae texto plano de un .pdf usando smalot/pdfparser.
     */
    private function extraerTextoPdf(string $rutaArchivo): string
    {
        $parser = new PdfParser();
        $pdf    = $parser->parseFile($rutaArchivo);

        return trim($pdf->getText());
    }

    /**
     * Convierte un elemento PhpWord a texto plano recursivamente.
     */
    private function elementoATexto(mixed $element): string
    {
        if ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
            $partes = [];
            foreach ($element->getElements() as $child) {
                $partes[] = $this->elementoATexto($child);
            }
            return implode('', $partes);
        }

        if ($element instanceof \PhpOffice\PhpWord\Element\Text) {
            return $element->getText();
        }

        if ($element instanceof \PhpOffice\PhpWord\Element\Paragraph) {
            $partes = [];
            foreach ($element->getElements() as $child) {
                $partes[] = $this->elementoATexto($child);
            }
            return implode('', $partes);
        }

        if ($element instanceof \PhpOffice\PhpWord\Element\Table) {
            $filas = [];
            foreach ($element->getRows() as $row) {
                $celdas = [];
                foreach ($row->getCells() as $cell) {
                    $contenido = [];
                    foreach ($cell->getElements() as $child) {
                        $contenido[] = $this->elementoATexto($child);
                    }
                    $celdas[] = implode(' ', $contenido);
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
}
