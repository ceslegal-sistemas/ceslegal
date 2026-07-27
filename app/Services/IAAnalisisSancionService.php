<?php

namespace App\Services;

use App\Models\ProcesoDisciplinario;
use App\Models\Trabajador;
use App\Services\GeminiCircuitBreaker;
use App\Services\ReglamentoInternoService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IAAnalisisSancionService
{
    /**
     * Analizar el proceso disciplinario y sugerir sanciones apropiadas
     */
    public function analizarYSugerirSanciones(ProcesoDisciplinario $proceso): array
    {
        try {
            // Obtener información del contexto
            $trabajador = $proceso->trabajador;
            $empresa = $proceso->empresa;

            // Obtener historial de procesos disciplinarios del trabajador
            $historialProcesos = $this->obtenerHistorialProcesos($trabajador, $proceso->id);

            // Obtener los descargos si existen
            $contextoDescargos = $this->obtenerContextoDescargos($proceso);

            // Obtener contexto del RIT: array estructurado (wizard) o fragmentos RAG (subido)
            [$sancionesRIT, $contextoRITRag] = $this->obtenerContextoRIT($empresa, $proceso);

            // Obtener artículos del CST desde la base de datos (evita alucinaciones)
            $contextoCST = $this->obtenerContextoCST($proceso);

            // Obtener jurisprudencia curada relevante (modelo Jurisprudencia)
            $contextoJurisprudencia = $this->obtenerContextoJurisprudencia($proceso);

            // Analizar (multimodal) las pruebas que el trabajador adjuntó en sus descargos
            $contextoPruebas = $this->analizarPruebasTrabajador($proceso, strip_tags($proceso->hechos ?? ''));

            // Construir el prompt para la IA
            $prompt = $this->construirPromptAnalisisSancion(
                $proceso,
                $trabajador,
                $empresa,
                $historialProcesos,
                $contextoDescargos,
                $sancionesRIT,
                $contextoRITRag,
                $contextoCST,
                $contextoJurisprudencia,
                $contextoPruebas
            );

            Log::info('Analizando proceso disciplinario para sugerir sanciones', [
                'proceso_id' => $proceso->id,
                'trabajador_id' => $trabajador->id,
                'cantidad_procesos_previos' => count($historialProcesos),
            ]);

            $analisisTexto = $this->llamarGemini($prompt);

            // Parsear la respuesta de la IA
            $analisis = $this->parsearAnalisisIA($analisisTexto);

            // Adjuntar el análisis multimodal de pruebas (para mostrarlo verbatim en la UI)
            if (!empty($contextoPruebas)) {
                $analisis['analisis_pruebas'] = $contextoPruebas;
            }

            Log::info('Análisis de sanciones completado', [
                'proceso_id' => $proceso->id,
                'sanciones_sugeridas' => $analisis['sanciones_disponibles'] ?? [],
                'gravedad' => $analisis['gravedad'] ?? 'desconocida',
            ]);

            return [
                'success' => true,
                'analisis' => $analisis,
            ];

        } catch (\Exception $e) {
            Log::error('Error al analizar proceso para sugerir sanciones', [
                'proceso_id' => $proceso->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Retornar opciones por defecto en caso de error
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'analisis' => $this->obtenerOpcionesPorDefecto(),
            ];
        }
    }

    /**
     * Retorna [$sancionesRIT, $contextoRITRag]:
     * - Wizard (construido_ia): $sancionesRIT = array estructurado, $contextoRITRag = ''.
     * - Subido (DOCX/PDF):      $sancionesRIT = [],  $contextoRITRag = fragmentos RAG relevantes.
     */
    private function obtenerContextoRIT($empresa, ProcesoDisciplinario $proceso): array
    {
        $rit = $empresa->reglamentoInterno;
        if (!$rit) {
            return [[], ''];
        }

        try {
            $service = app(ReglamentoInternoService::class);

            // Wizard: datos ya estructurados desde el cuestionario
            if ($rit->fuente === 'construido_ia') {
                return [$service->extraerSancionesParaEmail($rit), ''];
            }

            // Documento subido: usar RAG sobre el texto completo
            $query    = $this->construirQueryRIT($proceso);
            $contexto = $service->buscarEnRIT($rit, $query);

            return [[], $contexto];

        } catch (\Throwable $e) {
            Log::warning('IAAnalisisSancionService: error obteniendo contexto RIT', [
                'empresa_id' => $empresa->id,
                'fuente'     => $rit->fuente ?? 'desconocida',
                'error'      => $e->getMessage(),
            ]);
            return [[], ''];
        }
    }

    /**
     * Obtiene los artículos del CST almacenados en articulos_legales que son
     * relevantes para el proceso disciplinario.
     *
     * Estrategia dual:
     *   1. Artículos obligatorios por código (siempre incluidos):
     *      Art. 58 (obligaciones trabajador), Art. 62 (terminación justa causa),
     *      Art. 111 (sanciones en RIT), Art. 112 (límites suspensión).
     *   2. Búsqueda semántica por embedding sobre los hechos y motivos del caso.
     *
     * La IA SOLO puede citar artículos presentes en este contexto proporcionado.
     */
    private function obtenerContextoCST(ProcesoDisciplinario $proceso): string
    {
        try {
            /** @var \App\Services\RITGeneratorService $ritGen */
            $ritGen = app(\App\Services\RITGeneratorService::class);

            // ── 1. Artículos siempre relevantes para sanciones disciplinarias ──
            $codigosBase = [
                'Art. 58 CST',   // Obligaciones especiales del trabajador
                'Art. 60 CST',   // Prohibiciones a los trabajadores
                'Art. 62 CST',   // Terminación del contrato con justa causa
                'Art. 111 CST',  // Sanciones contempladas en el reglamento interno
                'Art. 112 CST',  // Límites legales a la suspensión
                'Art. 113 CST',  // Procedimiento de imposición de sanciones
            ];

            $textoBase = $ritGen->obtenerArticulosObligatorios($codigosBase);

            // ── 2. Búsqueda semántica adicional según los hechos del caso ─────
            $query = 'proceso disciplinario sanción suspensión terminación contrato falta leve grave reglamento interno'
                . ' ' . ($proceso->sanciones_laborales_texto ?? '')
                . ' ' . mb_substr(strip_tags($proceso->hechos ?? ''), 0, 300);

            $textoSemantico = $ritGen->buscarArticulosPorEmbedding(
                queryTema:   trim($query),
                yaObtenidos: $codigosBase,
                limite:      5,
                umbral:      0.35,
            );

            $partes = array_filter([$textoBase, $textoSemantico]);

            return implode("\n\n", $partes);

        } catch (\Throwable $e) {
            Log::warning('IAAnalisisSancionService: no se pudo obtener contexto CST', [
                'proceso_id' => $proceso->id,
                'error'      => $e->getMessage(),
            ]);
            return '';
        }
    }

    /**
     * Recupera jurisprudencia relevante (modelo Jurisprudencia: unidades curadas
     * con tesis/sub-regla y embedding del extracto) por similitud semántica sobre
     * los hechos del caso. Devuelve un bloque con la referencia + tesis de cada una.
     *
     * La IA SOLO podrá citar lo que aparezca en este bloque. Si no hay jurisprudencia
     * activa cargada, retorna '' y el prompt no cita sentencias.
     */
    private function obtenerContextoJurisprudencia(ProcesoDisciplinario $proceso): string
    {
        try {
            $sentencias = \App\Models\Jurisprudencia::query()
                ->activas()->procesadas()
                ->whereNotNull('embedding')
                ->get(['referencia', 'tema', 'tesis', 'fecha', 'magistrado', 'embedding']);

            if ($sentencias->isEmpty()) {
                return '';
            }

            $query = 'sanción disciplinaria debido proceso despido justa causa estabilidad laboral reforzada fuero'
                . ' proporcionalidad reincidencia descargos'
                . ' ' . ($proceso->sanciones_laborales_texto ?? '')
                . ' ' . mb_substr(strip_tags($proceso->hechos ?? ''), 0, 300);

            $queryEmb = app(\App\Services\BibliotecaLegalService::class)->embedConsulta(trim($query));
            if (empty($queryEmb)) {
                return '';
            }

            $scored = $sentencias
                ->map(function ($s) use ($queryEmb) {
                    $emb = $s->embedding;
                    if (empty($emb)) {
                        return null;
                    }
                    return ['s' => $s, 'score' => $this->cosenoSimilitud($queryEmb, $emb)];
                })
                ->filter(fn($x) => $x !== null && $x['score'] >= 0.45)
                ->sortByDesc('score')
                ->take(3)
                ->values();

            if ($scored->isEmpty()) {
                return '';
            }

            return $scored->map(function ($x) {
                $s    = $x['s'];
                $meta = trim(($s->fecha?->toDateString() ? $s->fecha->toDateString() . '; ' : '') . ($s->magistrado ? 'M.P. ' . $s->magistrado : ''));
                $head = "--- Sentencia {$s->referencia}" . ($meta ? " ({$meta})" : '') . " ---";
                $tema = $s->tema ? "Tema: {$s->tema}\n" : '';
                return "{$head}\n{$tema}Tesis: {$s->tesis}";
            })->implode("\n\n");

        } catch (\Throwable $e) {
            Log::warning('IAAnalisisSancionService: no se pudo obtener jurisprudencia', [
                'proceso_id' => $proceso->id,
                'error'      => $e->getMessage(),
            ]);
            return '';
        }
    }

    /** Similitud coseno entre dos vectores. */
    private function cosenoSimilitud(array $a, array $b): float
    {
        $n = min(count($a), count($b));
        if ($n === 0) {
            return 0.0;
        }
        $dot = $na = $nb = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $dot += $a[$i] * $b[$i];
            $na  += $a[$i] * $a[$i];
            $nb  += $b[$i] * $b[$i];
        }
        if ($na <= 0 || $nb <= 0) {
            return 0.0;
        }
        return $dot / (sqrt($na) * sqrt($nb));
    }

    /**
     * Analiza (multimodal) las pruebas que el trabajador adjuntó en sus descargos:
     * lee fotos/PDF, las resume y concluye si SOSTIENEN, CONTRADICEN o son
     * INSUFICIENTES para su justificación. NUNCA valida autenticidad (lo decide un
     * humano): siempre recomienda verificar con la fuente. Devuelve texto o ''.
     */
    private function analizarPruebasTrabajador(ProcesoDisciplinario $proceso, string $hechosTexto): string
    {
        try {
            $diligencia = $proceso->diligenciaDescargo;
            if (!$diligencia) {
                return '';
            }

            // Recopilar adjuntos: nivel diligencia + por respuesta.
            $archivos = array_values($diligencia->archivos_evidencia ?? []);
            foreach ($diligencia->preguntas()->with('respuesta')->get() as $pregunta) {
                foreach ($pregunta->respuesta?->archivos_adjuntos ?? [] as $adj) {
                    $archivos[] = $adj;
                }
            }
            if (empty($archivos)) {
                return '';
            }

            $mimes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif', 'pdf' => 'application/pdf'];
            $parts = [];
            $totalBytes = 0;

            foreach (array_slice($archivos, 0, 6) as $a) {
                $path = $a['path'] ?? null;
                if (!$path || !\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
                    continue;
                }
                $mime = $mimes[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? null;
                if (!$mime) {
                    continue;
                }
                $bytes = \Illuminate\Support\Facades\Storage::disk('public')->get($path);
                if ($bytes === null) {
                    continue;
                }
                $size = strlen($bytes);
                if ($size > 7_000_000 || ($totalBytes + $size) > 14_000_000) {
                    continue; // límite inline de Gemini
                }
                $parts[] = ['inline_data' => ['mime_type' => $mime, 'data' => base64_encode($bytes)]];
                $totalBytes += $size;
            }

            if (empty($parts)) {
                return '';
            }

            $instruccion = "Eres analista jurídico laboral en Colombia. El trabajador aportó las siguientes pruebas en sus descargos.\n"
                . "HECHOS IMPUTADOS: " . mb_substr($hechosTexto, 0, 600) . "\n\n"
                . "Analiza CADA prueba adjunta: qué es y qué muestra (fechas, entidad emisora, contenido relevante). "
                . "Luego concluye en una frase si en conjunto las pruebas SOSTIENEN, CONTRADICEN o son INSUFICIENTES "
                . "para justificar o explicar la conducta imputada.\n"
                . "REGLAS ESTRICTAS: NO afirmes que un documento es auténtico (no puedes verificarlo); SIEMPRE recomienda "
                . "verificar la autenticidad con la fuente (EPS, tránsito, aseguradora, etc.). Texto plano SIN markdown "
                . "(no uses asteriscos, viñetas con * ni negritas), claro y directo, máximo 180 palabras.";

            array_unshift($parts, ['text' => $instruccion]);

            return trim($this->llamarGeminiMultimodal($parts));

        } catch (\Throwable $e) {
            Log::warning('IAAnalisisSancionService: error analizando pruebas adjuntas', [
                'proceso_id' => $proceso->id,
                'error'      => $e->getMessage(),
            ]);
            return '';
        }
    }

    /** Llamada multimodal a Gemini (texto + imágenes/PDF inline). */
    private function llamarGeminiMultimodal(array $parts): string
    {
        $config = config('services.ia.gemini', []);
        $apiKey = $config['api_key'] ?? null;
        if (empty($apiKey)) {
            return '';
        }
        $modelo = $config['model'] ?? 'gemini-2.5-flash';
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelo}:generateContent?key={$apiKey}";

        $resp = Http::withHeaders(['Content-Type' => 'application/json'])
            ->timeout(120)
            ->post($url, [
                'contents'         => [['parts' => $parts]],
                'generationConfig' => ['temperature' => 0.2, 'maxOutputTokens' => 1024],
            ]);

        if (!$resp->successful()) {
            throw new \RuntimeException('Gemini multimodal devolvió ' . $resp->status());
        }

        return (string) ($resp->json('candidates.0.content.parts.0.text') ?? '');
    }

    /**
     * Construye la query de búsqueda RAG combinando los motivos del proceso con
     * términos disciplinarios clave para maximizar la recuperación de fragmentos relevantes.
     */
    private function construirQueryRIT(ProcesoDisciplinario $proceso): string
    {
        $partes = ['faltas leves graves sanciones disciplinarias suspensión terminación llamado atención reglamento disciplinario'];

        $nombres = $proceso->sancionesLaborales->pluck('nombre_claro')->filter()->join(' ');
        if ($nombres) {
            $partes[] = $nombres;
        }

        if ($proceso->hechos) {
            $partes[] = mb_substr(strip_tags($proceso->hechos), 0, 300);
        }

        return implode(' ', $partes);
    }

    /**
     * Obtener historial de procesos disciplinarios del trabajador
     */
    private function obtenerHistorialProcesos(Trabajador $trabajador, int $procesoActualId): array
    {
        $procesos = ProcesoDisciplinario::where('trabajador_id', $trabajador->id)
            ->where('id', '!=', $procesoActualId)
            ->where('estado', '!=', 'archivado')
            ->orderBy('created_at', 'desc')
            ->get();

        return $procesos->map(function ($proceso) {
            return [
                'fecha' => $proceso->created_at->format('Y-m-d'),
                'hechos' => strip_tags($proceso->hechos),
                // COMENTADO: Artículos legales - Ahora se usan Sanciones Laborales
                // 'articulos' => $proceso->articulos_legales_texto ?? 'No especificado',
                'sanciones' => $proceso->sanciones_laborales_texto ?? 'No especificado',
                'sancion' => $proceso->tipo_sancion ?? 'Sin sanción emitida',
                'estado' => $proceso->estado,
            ];
        })->toArray();
    }

    /**
     * Obtener los motivos de descargos seleccionados con detalle
     */
    private function obtenerMotivosDescargosDetalle(ProcesoDisciplinario $proceso): string
    {
        // Motivos normalizados: conductas del RIT (texto) o catálogo antiguo (IDs).
        $motivos = $proceso->motivosDescargosNormalizados();

        if (empty($motivos)) {
            return "No se han seleccionado motivos de descargos del reglamento interno.\n";
        }

        $detalle = "";
        foreach ($motivos as $index => $m) {
            $numero    = $index + 1;
            $tipoFalta = strtoupper($m['gravedad'] ?? 'grave');

            $detalle .= "{$numero}. [{$tipoFalta}] {$m['nombre']}\n";
            if (!empty($m['medida'])) {
                $detalle .= "   Medida contemplada en el RIT: {$m['medida']}\n";
            }
            if (($m['tipo'] ?? '') === 'suspension' && !empty($m['dias'])) {
                $detalle .= "   Días de suspensión según reglamento: {$m['dias']}\n";
            }
            if (!empty($m['reincidencia'])) {
                $detalle .= "   NOTA: Este motivo es una REINCIDENCIA (no es la primera vez)\n";
            }

            $detalle .= "\n";
        }

        return $detalle;
    }

    /**
     * Obtener contexto de descargos si existen
     */
    private function obtenerContextoDescargos(ProcesoDisciplinario $proceso): string
    {
        $diligencia = $proceso->diligenciaDescargo;

        if (!$diligencia) {
            return 'No se han realizado descargos aún.';
        }

        $preguntas = $diligencia->preguntas()
            ->with('respuesta')
            ->ordenadas()
            ->get();

        if ($preguntas->isEmpty()) {
            return 'No hay descargos registrados.';
        }

        $contexto = '';
        foreach ($preguntas as $index => $pregunta) {
            $respuesta = $pregunta->respuesta?->respuesta ?? 'Sin respuesta';
            $contexto .= ($index + 1) . ". {$pregunta->pregunta}\n   Respuesta: {$respuesta}\n\n";
        }

        return $contexto;
    }

    /**
     * Construir prompt para análisis de sanciones
     */
    private function construirPromptAnalisisSancion(
        ProcesoDisciplinario $proceso,
        Trabajador $trabajador,
        $empresa,
        array $historialProcesos,
        string $contextoDescargos,
        array $sancionesRIT = [],
        string $contextoRITRag = '',
        string $contextoCST = '',
        string $contextoJurisprudencia = '',
        string $contextoPruebas = ''
    ): string {
        $hechosTexto = strip_tags($proceso->hechos);
        $sancionesLaborales = $proceso->sanciones_laborales_texto ?? 'No especificado';
        $cantidadProcesosPrevios = count($historialProcesos);

        // Obtener los motivos de descargos seleccionados con detalle
        $motivosDescargosDetalle = $this->obtenerMotivosDescargosDetalle($proceso);

        // Verificar si hay "otro motivo"
        $otroMotivo = $proceso->otro_motivo_descargos;
        $tieneOtroMotivo = !empty($otroMotivo);

        $historialTexto = '';
        if ($cantidadProcesosPrevios > 0) {
            $historialTexto = "El trabajador tiene {$cantidadProcesosPrevios} proceso(s) disciplinario(s) previo(s):\n\n";
            foreach ($historialProcesos as $index => $proc) {
                $historialTexto .= ($index + 1) . ". Fecha: {$proc['fecha']}\n";
                $historialTexto .= "   Hechos: {$proc['hechos']}\n";
                // COMENTADO: Artículos legales - Ahora se usan Sanciones Laborales
                // $historialTexto .= "   Artículos: {$proc['articulos']}\n";
                $historialTexto .= "   Sanciones del reglamento incumplidas: {$proc['sanciones']}\n";
                $historialTexto .= "   Sanción aplicada: {$proc['sancion']}\n";
                $historialTexto .= "   Estado: {$proc['estado']}\n\n";
            }
        } else {
            $historialTexto = "Este es el PRIMER proceso disciplinario del trabajador (sin antecedentes previos).\n\n";
        }

        // Construir sección de otro motivo
        $seccionOtroMotivo = '';
        if ($tieneOtroMotivo) {
            $seccionOtroMotivo = "\n\nOTRO MOTIVO ADICIONAL (REQUIERE ANÁLISIS ESPECIAL):\n";
            $seccionOtroMotivo .= "El empleador seleccionó \"Otro\" como motivo adicional y describió lo siguiente:\n";
            $seccionOtroMotivo .= "\"{$otroMotivo}\"\n\n";
            $seccionOtroMotivo .= "IMPORTANTE: Debes analizar este motivo adicional y:\n";
            $seccionOtroMotivo .= "1. Determinar si es una falta LEVE o GRAVE\n";
            $seccionOtroMotivo .= "2. Recomendar qué tipo de sanción aplicaría para este motivo específico\n";
            $seccionOtroMotivo .= "3. Si es grave y aplica suspensión, indicar los días MÁXIMOS de suspensión que el RIT permite explícitamente\n";
            $seccionOtroMotivo .= "4. Proporcionar una justificación clara para ayudar al cliente a tomar la mejor decisión\n";
        }

        // Construir sección del RIT de la empresa
        $seccionRIT = '';

        // Caso A: fragmentos RAG del documento subido (texto real del RIT)
        if (!empty($contextoRITRag)) {
            $seccionRIT  = "\n═══════════════════════════════════════════════════════════════════\n";
            $seccionRIT .= "EXTRACTOS DEL REGLAMENTO INTERNO DE {$empresa->nombre_completo} (RAG):\n";
            $seccionRIT .= "═══════════════════════════════════════════════════════════════════\n";
            $seccionRIT .= $contextoRITRag . "\n";
            $seccionRIT .= "INSTRUCCIÓN: Estos son fragmentos reales del RIT de la empresa. Úsalos para\n";
            $seccionRIT .= "determinar qué conductas son faltas, qué sanciones contempla y sus límites.\n";
            $seccionRIT .= "No sugieras sanciones que el RIT no prevea explícitamente.\n";

        // Caso B: datos estructurados del wizard (construido_ia)
        } elseif (!empty($sancionesRIT['faltas_leves']) || !empty($sancionesRIT['faltas_graves'])) {
            $seccionRIT  = "\n═══════════════════════════════════════════════════════════════════\n";
            $seccionRIT .= "RÉGIMEN DISCIPLINARIO DEL RIT DE {$empresa->nombre_completo}:\n";
            $seccionRIT .= "═══════════════════════════════════════════════════════════════════\n";

            if (!empty($sancionesRIT['faltas_leves'])) {
                $seccionRIT .= "FALTAS LEVES definidas en el RIT:\n";
                foreach ($sancionesRIT['faltas_leves'] as $f) {
                    $seccionRIT .= "  - {$f}\n";
                }
            }
            if (!empty($sancionesRIT['faltas_graves'])) {
                $seccionRIT .= "FALTAS GRAVES definidas en el RIT:\n";
                foreach ($sancionesRIT['faltas_graves'] as $f) {
                    $seccionRIT .= "  - {$f}\n";
                }
            }
            if (!empty($sancionesRIT['sanciones'])) {
                $seccionRIT .= "SANCIONES CONTEMPLADAS en el RIT:\n";
                foreach ($sancionesRIT['sanciones'] as $s) {
                    $seccionRIT .= "  - {$s}\n";
                }
            }
            $seccionRIT .= "INSTRUCCIÓN: Las sanciones disponibles para tu recomendación final deben respetar\n";
            $seccionRIT .= "lo que el RIT de la empresa contempla. No sugiera sanciones que el RIT no prevea.\n";
        }

        // Construir bloque de contexto CST proporcionado
        $seccionCST = '';
        if (!empty($contextoCST)) {
            $seccionCST  = "\n═══════════════════════════════════════════════════════════════════\n";
            $seccionCST .= "CONTEXTO LEGAL CST - ARTÍCULOS APLICABLES (fuente oficial SUIN-Juriscol):\n";
            $seccionCST .= "═══════════════════════════════════════════════════════════════════\n";
            $seccionCST .= $contextoCST . "\n";
            $seccionCST .= "PROHIBICIÓN ABSOLUTA: Solo puedes citar artículos del CST que aparezcan\n";
            $seccionCST .= "TEXTUALMENTE en el bloque anterior. Nunca menciones artículos que no estén\n";
            $seccionCST .= "aquí aunque los conozcas de tu entrenamiento. Si necesitas un artículo que\n";
            $seccionCST .= "no aparece en el contexto, escribe únicamente \"según el CST\" sin número.\n";
            $seccionCST .= "═══════════════════════════════════════════════════════════════════\n";
        }

        // Construir bloque de jurisprudencia curada (modelo Jurisprudencia)
        $seccionJurisprudencia = '';
        if (!empty($contextoJurisprudencia)) {
            $seccionJurisprudencia  = "\n═══════════════════════════════════════════════════════════════════\n";
            $seccionJurisprudencia .= "JURISPRUDENCIA APLICABLE (extractos curados por el equipo jurídico):\n";
            $seccionJurisprudencia .= "═══════════════════════════════════════════════════════════════════\n";
            $seccionJurisprudencia .= $contextoJurisprudencia . "\n";
            $seccionJurisprudencia .= "PROHIBICIÓN ABSOLUTA: Solo puedes citar sentencias que aparezcan en este\n";
            $seccionJurisprudencia .= "bloque, con su referencia EXACTA (ej.: 'Sentencia T-1040/2006'). Nunca cites\n";
            $seccionJurisprudencia .= "una sentencia que no esté aquí aunque la conozcas de tu entrenamiento. Usa\n";
            $seccionJurisprudencia .= "estas tesis para reforzar la proporcionalidad, el fuero y el debido proceso.\n";
            $seccionJurisprudencia .= "═══════════════════════════════════════════════════════════════════\n";
        }

        // Análisis multimodal de las pruebas que adjuntó el trabajador
        $seccionPruebas = '';
        if (!empty($contextoPruebas)) {
            $seccionPruebas  = "\n═══════════════════════════════════════════════════════════════════\n";
            $seccionPruebas .= "ANÁLISIS DE PRUEBAS APORTADAS POR EL TRABAJADOR (lectura de los adjuntos):\n";
            $seccionPruebas .= "═══════════════════════════════════════════════════════════════════\n";
            $seccionPruebas .= $contextoPruebas . "\n";
            $seccionPruebas .= "INSTRUCCIÓN: Toma en cuenta este análisis de pruebas. Si las pruebas SOSTIENEN la\n";
            $seccionPruebas .= "justificación del trabajador, inclínate a 'no_sancionar' (o 'condicionada' si aún\n";
            $seccionPruebas .= "falta verificar autenticidad). Si la CONTRADICEN o son INSUFICIENTES, la falta se\n";
            $seccionPruebas .= "mantiene. RECUERDA SIEMPRE en el mensaje que la autenticidad debe verificarse con\n";
            $seccionPruebas .= "la fuente (EPS, tránsito, aseguradora). La decisión final es del funcionario.\n";
            $seccionPruebas .= "═══════════════════════════════════════════════════════════════════\n";
        }

        // Fuero / estabilidad reforzada registrado en la ficha del trabajador
        $seccionFuero = '';
        $fueroLabels  = method_exists($trabajador, 'tiposFueroLabels') ? $trabajador->tiposFueroLabels() : [];
        if (!empty($fueroLabels)) {
            $seccionFuero  = "\n═══════════════════════════════════════════════════════════════════\n";
            $seccionFuero .= "FUERO / ESTABILIDAD LABORAL REFORZADA REGISTRADA EN LA FICHA:\n";
            $seccionFuero .= "═══════════════════════════════════════════════════════════════════\n";
            foreach ($fueroLabels as $fl) {
                $seccionFuero .= "  - {$fl}\n";
            }
            if (!empty($trabajador->fuero_nota)) {
                $seccionFuero .= "Nota: {$trabajador->fuero_nota}\n";
            }
            $seccionFuero .= "INSTRUCCIÓN: Este trabajador TIENE fuero registrado. En alerta_fuero pon\n";
            $seccionFuero .= "requiere_verificacion=true, describe el/los fuero(s) en \"indicios\" y advierte que\n";
            $seccionFuero .= "la terminación exige el procedimiento de levantamiento de fuero (permiso del\n";
            $seccionFuero .= "Ministerio del Trabajo o autorización judicial, según el caso).\n";
        } else {
            $seccionFuero  = "\nFUERO REGISTRADO: ninguno en la ficha del trabajador. Aun así, si los hechos o\n";
            $seccionFuero .= "descargos sugieren un posible fuero, adviértelo; y ante cualquier TERMINACIÓN marca\n";
            $seccionFuero .= "alerta_fuero.requiere_verificacion=true por precaución.\n";
        }

        return <<<PROMPT
Eres un abogado laboralista colombiano experto en procesos disciplinarios. Tu rol es OBJETIVO E IMPARCIAL: tu deber es proteger el debido proceso, no justificar una sanción. NO asumas que el caso debe terminar en sanción. Si los descargos desvirtúan la falta, la prueba es insuficiente o la conducta no está tipificada en el RIT, debes decirlo y recomendar NO sancionar.

Identifica la(s) alternativa(s) de sanción legalmente admisibles basándote EXCLUSIVAMENTE en estas fuentes, en este orden de prioridad, y prepara una recomendación para la persona autorizada en la empresa que tomará la decisión final:

1. EL REGLAMENTO INTERNO DE TRABAJO (RIT) DE LA EMPRESA - fuente primaria: define qué conductas son faltas y qué sanciones contempla.
2. EL CÓDIGO SUSTANTIVO DEL TRABAJO (CST) - solo los artículos del bloque "CONTEXTO LEGAL CST" más abajo. Nunca inventes números de artículos.
3. EL HISTORIAL DISCIPLINARIO DEL TRABAJADOR - reincidencia, agravantes y atenuantes.

INSTRUCCIÓN CRÍTICA (anti-invención): No inventes rangos de días ni categorías de faltas. Deriva TODO del RIT de esta empresa y de los artículos del CST proporcionados. Si el RIT dice "suspensión hasta 8 días", no sugieras 30. Si el RIT no contempla terminación, no la sugieras.

RANGO DE OPCIONES (MUY IMPORTANTE): el sistema NO decide por la empresa; le PRESENTA OPCIONES para que ELLA elija. Por regla general ofrece un RANGO de 2 o 3 sanciones jurídicamente defendibles y proporcionales (ordenadas de la más laxa a la más severa), siempre dentro de lo que el RIT y el CST permiten. Da UNA sola opción ÚNICAMENTE en casos extremos donde solo una sea defendible (p. ej. acoso sexual probado → solo terminación; o una falta levísima y aislada → solo llamado).
La categoría (leve/grave/muy_grave) NO determina mecánicamente la sanción: una falta LEVE especialmente seria puede justificar una suspensión corta (no solo un llamado); una falta GRAVE de primera vez puede ir de suspensión a terminación según su gravedad concreta, el impacto y los agravantes/atenuantes; una falta MUY GRAVE (acoso sexual, violencia, etc.) puede dar lugar a terminación con justa causa aun siendo la primera vez. Sopesa gravedad concreta, impacto, reincidencia, atenuantes y descargos - no solo la etiqueta.

GARANTISMO (obligatorio antes de recomendar cualquier sanción): evalúa y reporta en "verificacion_garantias":
- TIPICIDAD/LEGALIDAD: ¿la conducta está tipificada como falta en el RIT? No se sanciona lo que no está tipificado.
- DEBIDO PROCESO: citación previa, derecho a ser oído y a aportar pruebas (Art. 115 CST si aparece en el contexto). Un defecto de procedimiento anula la sanción.
- INMEDIATEZ: la sanción debe ser oportuna; advierte si los hechos podrían estar caducos.
- NON BIS IN IDEM: no sancionar dos veces el mismo hecho.
- PROPORCIONALIDAD y gradualidad de la medida.
- SUFICIENCIA PROBATORIA: distingue hechos constatables de opiniones; si la prueba es débil, adviértelo.

FUERO / ESTABILIDAD LABORAL REFORZADA (obligatorio): no dispones de datos del trabajador sobre fuero. Si la información (hechos o descargos) sugiere posible fuero -maternidad/lactancia, sindical, salud o discapacidad, prepensionado, acoso laboral (Ley 1010 de 2006)- ALÉRTALO en "alerta_fuero". Y SIEMPRE que se evalúe TERMINACIÓN, marca requiere_verificacion=true: terminar a un trabajador aforado sin permiso del Ministerio del Trabajo o autorización judicial es NULO (reintegro e indemnización).

LEGALIDAD DE LA MULTA: la multa solo procede para faltas de puntualidad o asistencia y con el tope del Art. 113 CST. NO la sugieras para otras conductas aunque el RIT la mencione.

Identifica quién tiene potestad disciplinaria para cada sanción según el RIT. Si el RIT no especifica, indica "No especificado en el RIT".

INFORMACIÓN DEL PROCESO:
- Empresa: {$empresa->nombre_completo}
- Trabajador: {$trabajador->nombre_completo}
- Cargo: {$trabajador->cargo}
{$seccionFuero}
HECHOS DEL CASO ACTUAL:
{$hechosTexto}
{$seccionRIT}
{$seccionCST}
{$seccionJurisprudencia}
{$seccionPruebas}
═══════════════════════════════════════════════════════════════════
MOTIVOS DE LOS DESCARGOS SELECCIONADOS:
═══════════════════════════════════════════════════════════════════
{$motivosDescargosDetalle}

RESUMEN SANCIONES LABORALES INCUMPLIDAS:
{$sancionesLaborales}
{$seccionOtroMotivo}
═══════════════════════════════════════════════════════════════════

DESCARGOS DEL TRABAJADOR:
{$contextoDescargos}

HISTORIAL DEL TRABAJADOR:
{$historialTexto}

PROCESO DE ANÁLISIS:
1. Clasifica la conducta como LEVE GRAVE o MUY GRAVE según lo que el RIT de la empresa define. Si el RIT no tiene esa conducta, usa el CST como referencia.
2. Verifica si hay reincidencia en el historial - agrava la sanción conforme al RIT y los artículos del CST proporcionados en el bloque CONTEXTO LEGAL CST.
3. Evalúa los descargos del trabajador - considera atenuantes y argumentos de defensa.
4. De las sanciones que el RIT contempla, ofrece el RANGO de las que sean jurídicamente defendibles y proporcionales para este caso (normalmente 2 o 3, de la más laxa a la más severa) para que la empresa elija; una sola opción solo en casos extremos. Si el RIT no aporta datos, aplica solo lo que los artículos del CST proporcionados permiten.
5. Para suspensiones: indica únicamente días dentro del rango que el RIT establece, respetando los límites que establece el bloque CONTEXTO LEGAL CST proporcionado.

Responde EXACTAMENTE en este formato JSON (sin código markdown, sin texto adicional):
{
  "gravedad": "leve|grave|muy_grave",
  "es_reincidencia": true/false,
  "justificacion": "Por qué la conducta es leve, grave o muy grave, citando el RIT o CST aplicable",
  "sanciones_disponibles": ["llamado_atencion", "suspension", "multa", "terminacion"],
  "sancion_recomendada": "llamado_atencion|suspension|multa|terminacion",
  "dias_suspension_max_rit": null,
  "razonamiento_legal": "Fundamento en el RIT de la empresa y el CST. Citar artículo o capítulo del RIT si aplica",
  "consideraciones_especiales": "Historial, descargos del trabajador, atenuantes o agravantes relevantes",
  "alerta_fuero": {
    "requiere_verificacion": true/false,
    "indicios": "Pistas en los hechos o descargos de un posible fuero (maternidad/lactancia, sindical, salud o discapacidad, prepensionado, acoso Ley 1010 de 2006), o 'Sin indicios en la información disponible'",
    "recomendacion": "Acción concreta para el empleador, p. ej.: 'Verifique el fuero antes de cualquier terminación; si aplica, requiere permiso del Ministerio del Trabajo o autorización judicial'"
  },
  "verificacion_garantias": {
    "tipicidad": {"estado": "cumple|riesgo|no_determinable", "nota": "¿La conducta está tipificada como falta en el RIT?"},
    "debido_proceso": {"estado": "cumple|riesgo|no_determinable", "nota": "Citación, derecho a ser oído y a aportar pruebas"},
    "inmediatez": {"estado": "cumple|riesgo|no_determinable", "nota": "La sanción es oportuna; los hechos no están caducos"},
    "non_bis_in_idem": {"estado": "cumple|riesgo|no_determinable", "nota": "No se sanciona dos veces el mismo hecho"},
    "proporcionalidad": {"estado": "cumple|riesgo|no_determinable", "nota": "Sanción proporcional y gradual a la falta"},
    "suficiencia_probatoria": {"estado": "cumple|riesgo|no_determinable", "nota": "Hechos constatables frente a opiniones"}
  },
  "motivos_analizados": [
    {
      "motivo": "Nombre del motivo",
      "tipo_falta": "leve|grave|muy_grave",
      "sancion_asociada": "llamado_atencion|suspension|multa|terminacion",
      "observacion": "Análisis breve de este motivo específico"
    }
  ],
  "analisis_otro_motivo": {
    "aplica": true/false,
    "descripcion_analizada": "Descripción del otro motivo",
    "tipo_falta_determinado": "leve|grave|muy_grave",
    "sancion_recomendada": "llamado_atencion|suspension|multa|terminacion",
    "dias_suspension_max_rit": null,
    "justificacion": "Análisis de este motivo según RIT y CST"
  },
  "autoridad_sancion": {
    "llamado_atencion": "texto indicando qué cargo/área puede autorizar esta sanción según el RIT (ej: 'Jefe inmediato con aprobación de RRHH'), o 'No especificado en el RIT' si no está claro",
    "suspension": "texto indicando qué cargo/área puede autorizar suspensiones según el RIT, o 'No especificado en el RIT' si no está claro",
    "multa": "Solo si el RIT contempla multa: texto indicando qué cargo/área puede autorizarla y el monto o porcentaje máximo definido en el RIT, o omitir esta clave si el RIT no la contempla",
    "terminacion": "texto indicando qué cargo/área puede autorizar terminaciones de contrato según el RIT o el CST, o 'No especificado en el RIT' si no está claro"
  },
  "recomendacion_final": {
    "estado_recomendacion": "sancionar|condicionada|no_sancionar",
    "requiere_sancion": true/false,
    "sanciones_sugeridas": ["llamado_atencion", "suspension"],
    "sancion_principal": "llamado_atencion|suspension|multa|terminacion",
    "dias_suspension": null,
    "confianza": "alta|media|baja",
    "mensaje_para_decision": "Mensaje para el empleador explicando la recomendación, sus fundamentos y las opciones disponibles",
    "bases_juridicas": {
      "llamado_atencion": "Argumentación específica para el llamado de atención: artículo del RIT o del CST que lo soporta y por qué es la sanción proporcional al caso",
      "suspension": "Argumentación específica para la suspensión: artículo del RIT o del CST proporcionado que la permite, duración recomendada y proporcionalidad con la falta",
      "multa": "Solo si multa está en sanciones_sugeridas: artículo o capítulo del RIT que la contempla, monto o porcentaje definido y proporcionalidad con la falta",
      "terminacion": "Argumentación específica para terminación: causal exacta del CST proporcionado que aplica y por qué los hechos la configuran"
    }
  },
  "razones_no_recomendadas": {
    "llamado_atencion": "Solo si llamado_atencion NO está en sanciones_sugeridas: explicar concretamente por qué sería insuficiente o inadecuado para este caso específico, citando la gravedad de los hechos o el historial del trabajador",
    "suspension": "Solo si suspension NO está en sanciones_sugeridas: explicar concretamente por qué sería desproporcionada o inapropiada para este caso, citando la levedad de la falta o el contexto del trabajador",
    "multa": "Solo si multa está en sanciones_disponibles PERO NO en sanciones_sugeridas: explicar por qué aplicar multa no sería lo más adecuado en este caso concreto",
    "terminacion": "Solo si terminacion NO está en sanciones_sugeridas: explicar concretamente por qué sería excesiva o no configura justa causa según los hechos y el RIT o CST aplicable",
    "no_sancion": "SIEMPRE incluir este campo: evalúa de forma OBJETIVA si NO sancionar es una opción válida en este caso (descargos que exoneran, prueba insuficiente, falta no tipificada en el RIT o defecto del debido proceso) o, por el contrario, por qué no lo sería. No presupongas que sancionar es lo correcto"
  }
}

REGLAS ESTRICTAS:
- sanciones_sugeridas: ofrece un RANGO de opciones jurídicamente válidas y proporcionales para que la empresa decida. POR REGLA GENERAL incluye 2 o 3 (ordenadas de la más laxa a la más severa). Incluye UNA SOLA opción únicamente en casos extremos donde solo esa sea defendible (p. ej. acoso sexual probado → solo terminación; falta levísima aislada → solo llamado). Va VACÍO [] solo si lo correcto es NO sancionar (descargos que exoneran, prueba insuficiente o falta no tipificada). No incluir "no_sancion". Ejemplo: para una falta grave de primera vez sin agravantes extremos, incluir suspension y terminacion (y llamado_atencion si el RIT lo permite para esa falta) para que la empresa elija.
- bases_juridicas: incluir SOLO las sanciones que estén en sanciones_sugeridas. Cada texto (máximo 100 palabras) debe argumentar específicamente esa sanción citando el artículo concreto del RIT o del CST que la sustenta. No repetir el contenido de razonamiento_legal; este campo es la base jurídica puntual de cada opción.
- sancion_principal: el tipo que más se ajusta al caso, debe estar dentro de sanciones_sugeridas.
- sanciones_disponibles: incluye SOLO las sanciones que el RIT contempla. Sin RIT, aplica lo que permite el CST según la gravedad. "multa" solo si el RIT la define explícitamente con monto o porcentaje; de lo contrario, no la incluyas.
- dias_suspension_max_rit: número entero con el MÁXIMO de días que el RIT contempla EXPLÍCITAMENTE para la suspensión aplicable. Si el RIT no especifica días concretos, usa el límite que establezca el artículo correspondiente en el bloque CONTEXTO LEGAL CST proporcionado. Si no aplica suspensión, pon null. NUNCA inventes un valor que no esté en el RIT ni en el bloque CONTEXTO LEGAL CST proporcionado.
- dias_suspension (recomendacion_final): número concreto dentro del rango 1..dias_suspension_max_rit que mejor se ajuste al caso, o null si no hay suspensión.
- La gravedad es "leve", "grave" o "muy_grave" - no hay subcategorías ni niveles. La clasificación la define el RIT.
- Confianza "alta": el RIT clasifica explícitamente esta conducta. "media": se infiere del RIT. "baja": no hay datos del RIT, se aplica solo el CST.
- En "motivos_analizados": incluye CADA motivo seleccionado con su análisis individual.
- Si hay "otro motivo": analisis_otro_motivo.aplica=true y completa TODOS sus campos.
- Si NO hay "otro motivo": analisis_otro_motivo.aplica=false y los demás campos son null.
- razones_no_recomendadas: incluir una clave por CADA sanción que NO esté en sanciones_sugeridas. "no_sancion" SIEMPRE debe aparecer. Para multa: incluir SOLO si está en sanciones_disponibles pero NO en sanciones_sugeridas; si el RIT no contempla multa, no incluir esta clave. Para llamado_atencion, suspension y terminacion: incluir SOLO si NO están en sanciones_sugeridas. Cada texto (máximo 80 palabras), lenguaje claro y directo sin tecnicismos.
- estado_recomendacion (recomendacion_final): elige UNO. "sancionar" = procede aplicar al menos una sanción AHORA (da el rango). "condicionada" = hay una justificación o prueba ALEGADA pero sin verificar (p. ej. un accidente o incapacidad): IGUAL ofrece el RANGO proporcional que aplicaría SI la falta se confirma, y explica la condición en el mensaje. "no_sancionar" = los descargos YA exoneran o no hubo falta (sin opciones).
- COHERENCIA OBLIGATORIA: "no_sancionar" → sanciones_sugeridas=[], sancion_principal=null, dias_suspension=null. "condicionada" y "sancionar" → SIEMPRE incluye el RANGO proporcional en sanciones_sugeridas (2 o 3 opciones de la más laxa a la más severa; una sola solo en casos extremos). NUNCA pongas un llamado de atención como ÚNICA opción para una falta grave o muy grave reincidente: eso es desproporcionado. Si tu mensaje condiciona la decisión a verificar pruebas, el estado es "condicionada" (con su rango), NO "sancionar" ni "no_sancionar". En "condicionada", mensaje_para_decision debe decir claramente: "estas opciones aplican si, tras verificar las pruebas, la falta se mantiene; si se confirma la justificación, no procede sanción".
- alerta_fuero: requiere_verificacion DEBE ser true siempre que "terminacion" esté en sanciones_disponibles o sanciones_sugeridas, o cuando haya indicios de fuero. En "indicios" no afirmes un fuero que no conste; describe solo la pista o pon "Sin indicios en la información disponible".
- verificacion_garantias: completa SIEMPRE las seis garantías con estado "cumple", "riesgo" o "no_determinable". Usa "no_determinable" cuando la información no permita concluir (no inventes que se cumplió un trámite que no consta). Si alguna está en "riesgo", refléjalo también en consideraciones_especiales.
- verificacion_garantias.nota: UNA frase corta y CONCRETA (entre 15 y 30 palabras), en lenguaje simple para una persona NO abogada de recursos humanos. Debe decir algo ÚTIL y específico de ESTE caso: qué se cumplió, qué falta o qué debe hacer la empresa. NO uses definiciones ni principios abstractos (mal: "la proporcionalidad no puede evaluarse sin comprobar la falta"); di la consecuencia práctica (bien: "Aún no se sabe si hubo falta; primero hay que revisar las pruebas que pidió el trabajador"). PROHIBIDO citar números de artículos o tecnicismos (eso va en razonamiento_legal y bases_juridicas). Mismo estilo directo en las seis.
- Citas precisas: toda cita debe incluir el localizador exacto - número de artículo del CST (del bloque proporcionado) y, para el RIT, el capítulo/artículo/numeral específico. Si no puedes ubicar el localizador exacto, dilo expresamente ("el RIT no precisa el numeral").
- Máximo 150 palabras por campo de texto (salvo razones_no_recomendadas: 80 palabras). No uses saltos de línea dentro de strings JSON.
- Genera SOLO el JSON, sin markdown ni texto fuera del objeto JSON.
PROMPT;
    }

    /**
     * Llama a la API de Google Gemini con fallback automático entre modelos activos.
     * Si el modelo principal devuelve 503/404, prueba con el siguiente.
     * Integra GeminiCircuitBreaker para no martillar la API cuando está caída.
     *
     * Modelos activos (abril 2026). gemini-1.5-* y gemini-2.0-* están deprecados.
     */
    private function llamarGemini(string $prompt): string
    {
        if (GeminiCircuitBreaker::isOpen()) {
            throw new \Exception('Gemini no disponible temporalmente (circuit breaker abierto)');
        }

        $config = config('services.ia.gemini', []);
        $apiKey = $config['api_key'] ?? config('services.ia.' . config('services.ia.provider', 'gemini') . '.api_key');

        $modeloPrincipal = $config['model'] ?? 'gemini-2.5-flash';
        $modelos = array_unique(array_filter([
            $modeloPrincipal,
            'gemini-2.5-flash',
            'gemini-2.5-flash-lite',
        ]));

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.3,
                'maxOutputTokens' => max((int) ($config['max_tokens'] ?? 4096), 16384),
                'topP' => 0.95,
            ],
        ];

        $response = null;

        foreach ($modelos as $modelo) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelo}:generateContent?key={$apiKey}";

            try {
                $response = Http::withHeaders(['Content-Type' => 'application/json'])
                    ->timeout(90)
                    ->post($url, $payload);
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                Log::warning("IAAnalisisSancion: timeout/conexión en {$modelo}, intentando siguiente modelo", [
                    'error' => $e->getMessage(),
                ]);
                GeminiCircuitBreaker::recordFailure($modelo);
                $response = null;
                continue;
            }

            if (in_array($response->status(), [503, 404])) {
                Log::warning("IAAnalisisSancion: Gemini {$response->status()} en {$modelo}, intentando siguiente modelo");
                GeminiCircuitBreaker::recordFailure($modelo);
                continue;
            }

            break;
        }

        if ($response === null) {
            throw new \Exception('Todos los modelos Gemini fallaron por timeout o error de red');
        }

        if (!$response->successful()) {
            GeminiCircuitBreaker::recordFailure($modeloPrincipal);
            throw new \Exception('Error en API Gemini: ' . $response->body());
        }

        $responseData = $response->json();

        if (!isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
            throw new \Exception('Respuesta de Gemini sin contenido válido');
        }

        // Advertir si Gemini truncó la respuesta por límite de tokens
        $finishReason = $responseData['candidates'][0]['finishReason'] ?? null;
        if ($finishReason === 'MAX_TOKENS') {
            Log::warning('IAAnalisisSancion: respuesta truncada por MAX_TOKENS - considera aumentar maxOutputTokens o reducir el prompt', [
                'modelo' => $modelo,
                'finish_reason' => $finishReason,
            ]);
        }

        GeminiCircuitBreaker::recordSuccess();

        return $responseData['candidates'][0]['content']['parts'][0]['text'];
    }

    /**
     * Parsear la respuesta de la IA en formato JSON
     */
    private function parsearAnalisisIA(string $analisisTexto): array
    {
        // Limpiar el texto para obtener solo el JSON
        $analisisTexto = trim($analisisTexto);
        $analisisTexto = preg_replace('/```json\s*/', '', $analisisTexto);
        $analisisTexto = preg_replace('/```\s*$/', '', $analisisTexto);
        $analisisTexto = preg_replace('/```/', '', $analisisTexto);

        // Escapar caracteres de control dentro de strings JSON
        // (Gemini retorna newlines literales dentro de valores string que json_decode rechaza)
        $analisisTexto = $this->escaparControlesEnStringsJson($analisisTexto);

        try {
            $analisis = json_decode($analisisTexto, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                // Intentar reparar JSON truncado y reintentar
                $reparado = $this->repararJsonTruncado($analisisTexto);
                $analisis = json_decode($reparado, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception('Error al parsear JSON: ' . json_last_error_msg());
                }

                Log::info('JSON de IA reparado exitosamente (respuesta truncada)');
            }

            // Validar campos mínimos requeridos
            if (!isset($analisis['gravedad']) || !isset($analisis['sanciones_disponibles'])) {
                throw new \Exception('Respuesta de IA con estructura inválida (faltan campos gravedad/sanciones_disponibles)');
            }

            // Rellenar campos opcionales que pueden haberse truncado
            $defaults = $this->obtenerOpcionesPorDefecto();
            foreach (['sancion_recomendada', 'justificacion', 'razonamiento_legal', 'consideraciones_especiales', 'motivos_analizados', 'analisis_otro_motivo', 'recomendacion_final', 'autoridad_sancion', 'dias_suspension_max_rit', 'es_reincidencia', 'alerta_fuero', 'verificacion_garantias'] as $campo) {
                if (!isset($analisis[$campo])) {
                    $analisis[$campo] = $defaults[$campo] ?? null;
                }
            }

            // Rango de días 1..N según el MÁXIMO que contempla el RIT de la empresa.
            // El selector ofrece todo el rango permitido por el RIT; la IA recomienda
            // un valor dentro de ese rango, pero la empresa puede elegir hasta el tope.
            $maxDias = is_int($analisis['dias_suspension_max_rit']) && $analisis['dias_suspension_max_rit'] > 0
                ? $analisis['dias_suspension_max_rit']
                : null;
            $analisis['dias_suspension_sugeridos'] = $maxDias ? range(1, $maxDias) : [];

            $rf = $analisis['recomendacion_final'] ?? [];
            $estado = $rf['estado_recomendacion'] ?? null;

            // Compatibilidad: el antiguo 'esperar_pruebas' ahora es 'condicionada'.
            if ($estado === 'esperar_pruebas') {
                $estado = 'condicionada';
            }

            // Red de seguridad: si el modelo dice 'sancionar' (o no marca estado) pero
            // el mensaje condiciona la decisión a verificar pruebas, es 'condicionada'.
            // NO se ocultan las opciones: se mostrarán como rango proporcional + aviso,
            // para que la empresa SIEMPRE vea opciones (directriz de dirección).
            // Se normaliza a ASCII para que los acentos no rompan el match.
            if (in_array($estado, ['sancionar', null], true)) {
                $msg = \Illuminate\Support\Str::ascii(mb_strtolower($rf['mensaje_para_decision'] ?? ''));
                $frasesCondicional = [
                    'antes de tomar cualquier decision sancionatoria',
                    'antes de tomar una decision sancionatoria',
                    'antes de considerar cualquier sancion',
                    'antes de imponer cualquier sancion',
                    'solo despues de esta verificacion',
                    'solo despues de la verificacion',
                    'una vez se verifiquen las pruebas',
                    'una vez evaluadas estas pruebas',
                    'podria desvirtuarse',
                    'podria atenuarse',
                    'sujeto a verificacion',
                    'verifique las pruebas',
                    'verificar las pruebas',
                ];
                foreach ($frasesCondicional as $frase) {
                    if (mb_strpos($msg, $frase) !== false) {
                        $estado = 'condicionada';
                        break;
                    }
                }
            }

            $analisis['recomendacion_final']['estado_recomendacion'] = $estado ?: 'sancionar';

            // Solo "no_sancionar" oculta las opciones. "condicionada" y "sancionar"
            // conservan el RANGO de sanciones (la empresa siempre ve opciones).
            $ocultarOpciones = ($estado === 'no_sancionar')
                || (($rf['requiere_sancion'] ?? null) === false && $estado !== 'condicionada');
            if ($ocultarOpciones) {
                $analisis['recomendacion_final']['estado_recomendacion'] = 'no_sancionar';
                $analisis['recomendacion_final']['sanciones_sugeridas'] = [];
                $analisis['recomendacion_final']['sancion_principal'] = null;
                $analisis['recomendacion_final']['dias_suspension'] = null;
                $analisis['recomendacion_final']['requiere_sancion'] = false;
            }

            return $analisis;

        } catch (\Exception $e) {
            Log::warning('Error al parsear análisis de IA', [
                'error' => $e->getMessage(),
                'respuesta_ia' => mb_substr($analisisTexto, 0, 500),
            ]);

            // Re-propagar para que analizarYSugerirSanciones retorne success:false
            throw $e;
        }
    }

    /**
     * Escapar caracteres de control (newlines, tabs) dentro de strings JSON.
     * json_decode rechaza caracteres de control literales (0x00-0x1F) dentro de strings.
     */
    private function escaparControlesEnStringsJson(string $json): string
    {
        $resultado = '';
        $enString = false;
        $escape = false;
        $len = strlen($json);

        for ($i = 0; $i < $len; $i++) {
            $char = $json[$i];
            $ord = ord($char);

            if ($escape) {
                $resultado .= $char;
                $escape = false;
                continue;
            }

            if ($char === '\\' && $enString) {
                $resultado .= $char;
                $escape = true;
                continue;
            }

            if ($char === '"') {
                $enString = !$enString;
                $resultado .= $char;
                continue;
            }

            if ($enString && $ord < 32) {
                switch ($ord) {
                    case 10: $resultado .= '\\n'; break;
                    case 13: $resultado .= '\\r'; break;
                    case 9:  $resultado .= '\\t'; break;
                    default: $resultado .= ' '; break;
                }
            } else {
                $resultado .= $char;
            }
        }

        return $resultado;
    }

    /**
     * Intentar reparar un JSON truncado cerrando strings y estructuras abiertas.
     */
    private function repararJsonTruncado(string $json): string
    {
        $json = rtrim($json);

        // Determinar si estamos dentro de un string al final del texto
        $enString = false;
        $escape = false;
        $pilas = [];

        for ($i = 0; $i < strlen($json); $i++) {
            $char = $json[$i];

            if ($escape) {
                $escape = false;
                continue;
            }

            if ($char === '\\' && $enString) {
                $escape = true;
                continue;
            }

            if ($char === '"') {
                $enString = !$enString;
                continue;
            }

            if (!$enString) {
                if ($char === '{' || $char === '[') {
                    $pilas[] = $char;
                } elseif ($char === '}' && !empty($pilas) && end($pilas) === '{') {
                    array_pop($pilas);
                } elseif ($char === ']' && !empty($pilas) && end($pilas) === '[') {
                    array_pop($pilas);
                }
            }
        }

        // Si terminó dentro de un string, cerrarlo
        if ($enString) {
            $json .= '"';
        }

        // Cerrar todas las estructuras abiertas (arrays y objetos)
        while (!empty($pilas)) {
            $abierto = array_pop($pilas);
            $json .= ($abierto === '{') ? '}' : ']';
        }

        return $json;
    }

    /**
     * Obtener opciones por defecto en caso de error
     */
    private function obtenerOpcionesPorDefecto(): array
    {
        return [
            'gravedad' => 'grave',
            'es_reincidencia' => false,
            'justificacion' => 'Análisis manual requerido - el sistema no pudo determinar automáticamente la gravedad.',
            'sanciones_disponibles' => ['llamado_atencion', 'suspension', 'terminacion'],
            'sancion_recomendada' => 'llamado_atencion',
            'dias_suspension_max_rit' => null,
            'dias_suspension_sugeridos' => [],
            'razonamiento_legal' => 'Se requiere revisión manual del caso para determinar la sanción apropiada.',
            'consideraciones_especiales' => 'El análisis automático no estuvo disponible. Se recomienda revisar manualmente los hechos, motivos seleccionados y el historial del trabajador.',
            'motivos_analizados' => [],
            'analisis_otro_motivo' => [
                'aplica' => false,
                'descripcion_analizada' => null,
                'tipo_falta_determinado' => null,
                'sancion_recomendada' => null,
                'dias_suspension_max_rit' => null,
                'justificacion' => null,
            ],
            'autoridad_sancion' => [],
            'alerta_fuero' => [
                'requiere_verificacion' => true,
                'indicios' => 'No determinado por el análisis automático.',
                'recomendacion' => 'Verifique si el trabajador tiene fuero o estabilidad laboral reforzada (maternidad, sindical, salud/discapacidad, prepensionado, acoso) antes de imponer cualquier sanción, en especial la terminación.',
            ],
            'verificacion_garantias' => [
                'tipicidad' => ['estado' => 'no_determinable', 'nota' => 'Requiere revisión manual.'],
                'debido_proceso' => ['estado' => 'no_determinable', 'nota' => 'Requiere revisión manual.'],
                'inmediatez' => ['estado' => 'no_determinable', 'nota' => 'Requiere revisión manual.'],
                'non_bis_in_idem' => ['estado' => 'no_determinable', 'nota' => 'Requiere revisión manual.'],
                'proporcionalidad' => ['estado' => 'no_determinable', 'nota' => 'Requiere revisión manual.'],
                'suficiencia_probatoria' => ['estado' => 'no_determinable', 'nota' => 'Requiere revisión manual.'],
            ],
            'recomendacion_final' => [
                'estado_recomendacion' => 'sancionar',
                'requiere_sancion' => false,
                'sanciones_sugeridas' => ['llamado_atencion'],
                'sancion_principal' => 'llamado_atencion',
                'dias_suspension' => null,
                'confianza' => 'baja',
                'mensaje_para_decision' => 'El análisis automático no estuvo disponible. Se recomienda revisar manualmente el caso antes de tomar una decisión. Considere los hechos, los motivos seleccionados, el historial del trabajador y los descargos presentados.',
            ],
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // MOTORES V6 (prompt del jefe) - capas de validación POSTERIORES a la
    // recomendación ya generada por analizarYSugerirSanciones(). Cada motor
    // hace UNA sola cosa (evidencia, conflictos, congruencia, explicabilidad,
    // simulación judicial, precedentes, uniformidad, calidad documental) y
    // nunca reemplaza ni decide la sanción - solo la audita.
    //
    // Lenguaje corregido respecto al documento original: el texto del jefe
    // usaba repetidamente "decisión disciplinaria" / "emitir la decisión" /
    // "medida disciplinaria que impone el sistema", dando a entender que la
    // IA decide o impone la sanción. Se reemplazó sistemáticamente por
    // "recomendación" / "sanción recomendada" / "preparar una recomendación
    // para la persona autorizada que tomará la decisión" - la empresa sigue
    // siendo quien decide, tal como ya lo dice IAAnalisisSancionService en su
    // prompt principal ("el sistema NO decide por la empresa; le PRESENTA
    // OPCIONES para que ELLA elija").
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Ejecuta los 8 motores V6 en secuencia sobre una recomendación ya generada.
     * Cada motor corre de forma independiente - si uno falla, se registra el
     * error y se continúa con los demás (una validación caída no debe tumbar
     * las otras 7). Devuelve un array con la clave de cada motor y su resultado
     * (o null + 'error' si ese motor específico falló).
     *
     * ADVERTENCIA DE LATENCIA: esto son 8 llamadas adicionales a Gemini,
     * ejecutadas de forma síncrona una tras otra. analizarYSugerirSanciones()
     * ya se llama de forma síncrona y bloqueante desde un Action de Filament
     * (ver ProcesoDisciplinarioResource.php) - agregar estas 8 llamadas AL
     * MISMO flujo haría que el botón "Emitir Sanción" tarde mucho más.
     * Por eso este método NO se invoca automáticamente desde
     * analizarYSugerirSanciones(): debe cablearse aparte (botón separado,
     * job en cola, o llamada explícita) según se decida.
     */
    public function ejecutarValidacionesV6(ProcesoDisciplinario $proceso, array $analisisSancion): array
    {
        $contexto = $this->construirContextoV6($proceso, $analisisSancion);

        $motores = [
            'ponderacion_evidencia'       => 'construirPromptPonderacionEvidenciaV6',
            'resolucion_conflictos'       => 'construirPromptResolucionConflictosV6',
            'congruencia_juridica'        => 'construirPromptCongruenciaJuridicaV6',
            'explicabilidad'              => 'construirPromptExplicabilidadV6',
            'simulacion_judicial'         => 'construirPromptSimulacionJudicialV6',
            'precedentes_internos'        => 'construirPromptPrecedentesInternosV6',
            'uniformidad_disciplinaria'   => 'construirPromptUniformidadDisciplinariaV6',
            'calidad_documental'          => 'construirPromptCalidadDocumentalV6',
        ];

        $resultados = [];
        foreach ($motores as $clave => $metodo) {
            try {
                $prompt = $this->{$metodo}($contexto);
                $respuesta = $this->llamarGemini($prompt);
                $resultados[$clave] = $this->parsearJsonV6($respuesta);
            } catch (\Throwable $e) {
                Log::warning("IAAnalisisSancion: motor V6 '{$clave}' falló", [
                    'proceso_id' => $proceso->id,
                    'error'      => $e->getMessage(),
                ]);
                $resultados[$clave] = ['error' => $e->getMessage()];
            }
        }

        return $resultados;
    }

    /**
     * Bloque de contexto compartido por los 8 motores V6: hechos, la
     * recomendación ya generada por analizarYSugerirSanciones(), y el
     * historial del trabajador (para precedentes/uniformidad).
     */
    private function construirContextoV6(ProcesoDisciplinario $proceso, array $analisisSancion): string
    {
        $trabajador = $proceso->trabajador;
        $empresa    = $proceso->empresa;
        $hechosTexto = strip_tags($proceso->hechos);
        $recomendacionJson = json_encode($analisisSancion, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $historialProcesos = $this->obtenerHistorialProcesos($trabajador, $proceso->id);
        $historialTexto = '';
        foreach ($historialProcesos as $index => $proc) {
            $historialTexto .= ($index + 1) . ". Fecha: {$proc['fecha']} - Hechos: {$proc['hechos']} - Sanción aplicada: {$proc['sancion']}\n";
        }
        if ($historialTexto === '') {
            $historialTexto = '(sin procesos disciplinarios previos)';
        }

        // Mismas fuentes que usó analizarYSugerirSanciones() para redactar la
        // recomendación - sin esto, motores como Explicabilidad/Congruencia
        // marcaban como "no trazable" cada cita al RIT o al CST que la
        // recomendación SÍ sustentaba correctamente, solo que en un contexto
        // que estos 8 motores nunca recibían.
        [$sancionesRIT, $contextoRITRag] = $this->obtenerContextoRIT($empresa, $proceso);
        $contextoCST            = $this->obtenerContextoCST($proceso);
        $contextoJurisprudencia = $this->obtenerContextoJurisprudencia($proceso);
        $contextoDescargos      = $this->obtenerContextoDescargos($proceso);

        $ritTexto = $contextoRITRag ?: (
            !empty($sancionesRIT) ? json_encode($sancionesRIT, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : '(sin RIT disponible)'
        );

        return <<<CONTEXTO
==================================================
EXPEDIENTE DEL CASO
==================================================
Trabajador: {$trabajador->nombre_completo}
Cargo: {$trabajador->cargo}

Hechos investigados:
{$hechosTexto}

Descargos del trabajador:
{$contextoDescargos}

==================================================
NORMATIVA DISPONIBLE (misma fuente que usó la recomendación - para verificar
que cada cita sea trazable, NO para reinterpretarla con conocimiento externo)
==================================================
REGLAMENTO INTERNO DE {$empresa->nombre_completo}:
{$ritTexto}

CÓDIGO SUSTANTIVO DEL TRABAJO (artículos aplicables):
{$contextoCST}

JURISPRUDENCIA APLICABLE:
{$contextoJurisprudencia}

==================================================
RECOMENDACIÓN YA GENERADA (a validar, no a decidir de nuevo)
==================================================
{$recomendacionJson}

==================================================
ANTECEDENTES DISCIPLINARIOS DEL TRABAJADOR
==================================================
{$historialTexto}

==================================================
REGLA DE REDACCIÓN PARA TODAS LAS LISTAS DE HALLAZGOS (obligatoria)
==================================================
Quien lee esto es una persona de Recursos Humanos, no un desarrollador ni un
abogado técnico. Va a leer como máximo 10 segundos por hallazgo. Cada hallazgo
de cualquier lista (incongruencias, alertas, debilidades, riesgos, errores,
advertencias, fallas_explicabilidad, etc.):
- Máximo 2 hallazgos por lista - reporta solo el o los más graves; si hay
  más de 2 problemas reales, elige los 2 que más cambiarían la decisión y
  descarta el resto (no los resumas al final, simplemente omítelos).
- Cada hallazgo en UNA sola oración corta (máximo 25 palabras), como si se
  lo explicaras hablando a la persona, sin tecnicismos.
- Cada hallazgo debe decir: (1) qué falta o qué no cuadra, y (2) por qué le
  importa a la empresa (ej. "el trabajador dijo que avisó a su jefe pero eso
  no quedó verificado, así que la sanción podría caerse si él lo alega ante
  un juez").
- PROHIBIDO citar los títulos en mayúsculas de este documento (son
  instrucciones internas tuyas, nunca nombres que el lector conozca) -
  jamás escribas frases como "esto rompe la Congruencia Fáctica y
  Probatoria", "viola el Control de Expansión" o "afecta la Trazabilidad".
  Si necesitas explicar por qué algo es un problema, dilo con tus propias
  palabras de sentido común, no citando el nombre de la regla.
- PROHIBIDO usar códigos internos como identificador del hallazgo (ej. no
  empieces con "C001 -", "H1:", etc.).
- PROHIBIDO mencionar nombres de campos JSON (ej. "sancion_recomendada",
  "verificacion_garantias.tipicidad.nota") o rutas técnicas.
- PROHIBIDO usar corchetes, comillas simples, snake_case o sintaxis de
  código en el texto del hallazgo.
- Si necesitas comparar dos datos que no coinciden entre sí (por ejemplo,
  dos partes de la recomendación que se contradicen), nunca uses una
  palabra genérica como "esto" para reemplazar el dato que no puedes
  nombrar - en su lugar, describe directamente el CONTENIDO real de cada
  uno en palabras simples (ej. "en una parte del análisis se sugiere una
  suspensión, pero en otra parte se habla de un llamado de atención para
  el mismo caso" - nunca "la clave X indica esto, mientras que Y indica
  esto").
- Si no hay ningún hallazgo relevante, devuelve la lista vacía [] - nunca
  fuerces hallazgos menores solo para llenar espacio.
CONTEXTO;
    }

    /**
     * MOTOR DE PONDERACIÓN DE EVIDENCIA V6 - pesa la fuerza probatoria de
     * cada elemento del expediente. No requirió cambios de lenguaje: ya
     * dice "Nunca determina responsabilidad" (una prohibición, no una
     * afirmación de que la IA decide).
     */
    private function construirPromptPonderacionEvidenciaV6(string $contexto): string
    {
        return <<<PROMPT
Eres el Motor de Ponderación de Evidencia.
Este motor se ejecutará obligatoriamente antes del Análisis de Responsabilidad.
Su única función consiste en valorar objetivamente la fuerza probatoria de cada elemento del expediente.
Nunca determina responsabilidad.
Nunca modifica el expediente.
Nunca genera preguntas.
Nunca sustituye el criterio jurídico.
Únicamente mide la calidad probatoria de la evidencia.
==================================================
PRINCIPIO GENERAL
==================================================
Toda evidencia posee un peso probatorio diferente.
El sistema nunca deberá tratar todas las pruebas como si tuvieran el mismo valor.
==================================================
FUENTE ÚNICA
==================================================
Analizar únicamente evidencia documental, tecnológica, física, declaraciones, reconocimientos,
registros empresariales y evidencia incorporada válidamente al expediente. Nunca utilizar
información externa.
==================================================
EVALUACIÓN INDIVIDUAL
==================================================
Cada evidencia deberá analizarse independientemente. Nunca heredar credibilidad de otra evidencia.
==================================================
CRITERIOS DE VALORACIÓN
==================================================
Evaluar pertinencia, confiabilidad, autenticidad, consistencia, corroboración, objetividad,
integridad, trazabilidad.
PERTINENCIA: ¿la evidencia demuestra directamente un hecho material? Clasificar MUY_ALTA, ALTA,
MEDIA, BAJA, NULA.
CONFIABILIDAD: ¿existen razones objetivas para confiar en esta evidencia? Nunca utilizar intuición.
AUTENTICIDAD: ¿la evidencia puede considerarse auténtica conforme al expediente? Si existe duda
objetiva, reducir su peso.
CORROBORACIÓN: ¿la evidencia es respaldada por otras pruebas independientes? Si múltiples pruebas
independientes coinciden, incrementar su fuerza probatoria.
==================================================
AISLAMIENTO
==================================================
Una evidencia aislada nunca incrementará automáticamente su valor. Cada prueba deberá sostenerse
por sus propios méritos.
==================================================
CONFLICTOS
==================================================
Si dos pruebas se contradicen, nunca decidir inmediatamente cuál prevalece - remitir el conflicto
al Motor de Resolución de Conflictos Probatorios.
==================================================
CONFESIONES
==================================================
Analizar espontaneidad, claridad, consistencia, corroboración. Nunca asumir que una confesión
elimina automáticamente la necesidad de valorar el resto del expediente.
==================================================
PESO PROBATORIO
==================================================
Clasificar únicamente MUY_ALTO, ALTO, MEDIO, BAJO, NULO. Nunca utilizar porcentajes.
==================================================
REGLA DE ACUMULACIÓN
==================================================
Múltiples evidencias independientes podrán fortalecer una conclusión. Nunca duplicar
artificialmente el peso de una misma fuente.
==================================================
VALIDACIÓN FINAL
==================================================
Antes de responder verificar: toda evidencia fue evaluada individualmente, no existen
valoraciones subjetivas ni inferencias externas, toda clasificación es consistente con el
expediente, ninguna prueba recibió peso arbitrario.
==================================================
SALIDA
==================================================
Responder ÚNICAMENTE con este JSON:
{
  "evidencias": [],
  "peso_global": "ALTO"
}

{$contexto}
PROMPT;
    }

    /**
     * MOTOR DE RESOLUCIÓN DE CONFLICTOS PROBATORIOS V6.
     */
    private function construirPromptResolucionConflictosV6(string $contexto): string
    {
        return <<<PROMPT
Eres el Motor de Resolución de Conflictos Probatorios.
Este motor se ejecutará automáticamente cuando dos o más elementos probatorios relevantes resulten incompatibles entre sí.
Su única función consiste en determinar si el conflicto probatorio puede resolverse objetivamente utilizando exclusivamente el expediente.
Nunca crea evidencia. Nunca elimina evidencia. Nunca modifica declaraciones.
Nunca determina responsabilidad disciplinaria.
Únicamente evalúa conflictos probatorios.
==================================================
PRINCIPIO GENERAL
==================================================
La existencia de evidencia contradictoria no implica automáticamente que una de ellas sea falsa.
El sistema deberá intentar explicar objetivamente la contradicción antes de asignar mayor valor
a cualquiera de las pruebas.
==================================================
IDENTIFICACIÓN Y CLASIFICACIÓN
==================================================
Detectar conflictos entre declaraciones, documentos, registros electrónicos, pruebas técnicas,
confesiones, informes, evidencia física, cronología. Nunca comparar elementos irrelevantes.
Clasificar cada conflicto: CRÍTICO, MATERIAL, SECUNDARIO, IRRELEVANTE. Solo los conflictos
CRÍTICOS y MATERIALES podrán afectar la recomendación disciplinaria.
==================================================
ANÁLISIS INDEPENDIENTE
==================================================
Evaluar cada evidencia por separado. Nunca asumir que una prueba pierde valor únicamente porque
otra la contradice. Cada elemento deberá conservar su valoración individual.
==================================================
POSIBLES EXPLICACIONES
==================================================
Antes de concluir que existe un conflicto real, evaluar objetivamente: error de memoria,
diferencia de percepción, error documental, desfase temporal, información incompleta, lenguaje
ambiguo, diferencia terminológica, contexto distinto. Si cualquiera explica razonablemente la
diferencia, no clasificar como conflicto material.
==================================================
CORROBORACIÓN Y COHERENCIA
==================================================
Determinar cuál evidencia posee mayor respaldo independiente - nunca utilizar intuición ni
experiencia previa, únicamente evidencia incorporada válidamente al expediente. Cada evidencia
deberá ser internamente consistente (coherencia interna) y compatible con el resto del expediente
(coherencia externa) - la incompatibilidad aislada nunca será suficiente para descartarla.
==================================================
CONFLICTOS NO RESUELTOS
==================================================
Si el expediente no permite resolver objetivamente el conflicto, mantener ambas hipótesis activas
y reducir automáticamente el nivel de confianza de la recomendación. Nunca resolver el conflicto
por probabilidad.
==================================================
PROHIBICIONES Y MOTIVACIÓN
==================================================
Nunca favorecer automáticamente al empleador, al trabajador, la prueba documental, la confesión o
la evidencia tecnológica - toda evidencia deberá evaluarse bajo exactamente los mismos criterios.
Toda preferencia probatoria deberá poder justificarse exclusivamente mediante corroboración,
consistencia, autenticidad, integridad, trazabilidad - nunca mediante opiniones.
==================================================
VALIDACIÓN FINAL
==================================================
Antes de emitir el resultado verificar: todos los conflictos fueron identificados, se evaluaron
explicaciones alternativas, no existen preferencias arbitrarias, toda decisión probatoria puede
justificarse objetivamente, los conflictos no resueltos permanecen identificados.
==================================================
SALIDA
==================================================
Responder ÚNICAMENTE con este JSON:
{
  "conflictos": [],
  "conflictos_resueltos": [],
  "conflictos_pendientes": [],
  "impacto": "BAJO"
}

{$contexto}
PROMPT;
    }

    /**
     * MOTOR DE CONGRUENCIA JURÍDICA V6 - lenguaje corregido: "emitir
     * decisión" -> "preparar recomendación"; "la decisión" -> "la
     * recomendación" en todas las referencias al resultado que audita.
     */
    private function construirPromptCongruenciaJuridicaV6(string $contexto): string
    {
        return <<<PROMPT
Eres el Motor de Congruencia Jurídica.
Este motor se ejecutará obligatoriamente antes de preparar cualquier recomendación disciplinaria y antes de generar cualquier documento final.
Su única función consiste en verificar que exista congruencia absoluta entre los hechos investigados, la evidencia, el análisis, la recomendación y la sanción propuesta.
Nunca modifica el expediente. Nunca modifica las pruebas. Nunca cambia la recomendación.
Únicamente detecta incongruencias jurídicas.
==================================================
PRINCIPIO GENERAL
==================================================
Toda recomendación deberá derivarse exclusivamente de los hechos efectivamente investigados y
acreditados. Ninguna recomendación podrá sustentarse en hechos no investigados. Ninguna sanción
propuesta podrá fundamentarse en hechos no acreditados.
==================================================
CONGRUENCIA FÁCTICA Y PROBATORIA
==================================================
Cada hecho utilizado en la recomendación deberá aparecer previamente en la investigación, el
interrogatorio, el acta y el análisis - si un hecho aparece únicamente en la recomendación,
clasificar como incongruencia crítica. Cada afirmación relevante deberá estar respaldada por
evidencia incorporada válidamente al expediente - nunca aceptar afirmaciones sin soporte
probatorio.
==================================================
CONGRUENCIA JURÍDICA
==================================================
Toda sanción propuesta deberá derivarse exclusivamente de los hechos acreditados, las normas
suministradas y el Reglamento Interno de Trabajo. Nunca utilizar fundamentos jurídicos distintos.
==================================================
CONGRUENCIA DOCUMENTAL Y TEMPORAL
==================================================
Verificar consistencia entre acta, informe técnico, análisis, recomendación y carta final - todos
deberán describir exactamente los mismos hechos materiales. Los hechos utilizados para
fundamentar la recomendación deberán haber ocurrido dentro de la cronología investigada - nunca
incorporar eventos posteriores ni alterar la secuencia cronológica.
==================================================
CONGRUENCIA DE RESPONSABILIDAD Y DE LA SANCIÓN
==================================================
La responsabilidad disciplinaria únicamente podrá fundamentarse sobre conductas investigadas,
acreditadas y discutidas durante el debido proceso - nunca ampliar el alcance durante la
recomendación. Toda sanción propuesta deberá corresponder exclusivamente a los hechos acreditados,
la gravedad determinada y la normativa suministrada - nunca justificar una sanción utilizando
hechos accesorios.
==================================================
HECHOS DESCARTADOS Y NO INVESTIGADOS
==================================================
Todo hecho descartado durante la investigación queda prohibido como fundamento de cualquier
recomendación futura. Todo hecho que nunca haya sido objeto de investigación formal nunca podrá
aparecer en el análisis, la recomendación, la carta ni el informe.
==================================================
CONTROL DE EXPANSIÓN
==================================================
Responder internamente: ¿la recomendación contiene afirmaciones que nunca fueron investigadas?
¿la motivación introduce argumentos nuevos? ¿la carta amplía el alcance del análisis? Si cualquiera
responde SI, clasificar como incongruencia crítica.
==================================================
TRAZABILIDAD
==================================================
Cada fundamento de la recomendación deberá poder seguir la cadena: pregunta → respuesta → acta →
análisis → recomendación → documento final. Si la cadena se rompe, existe incongruencia.
==================================================
VALIDACIÓN FINAL
==================================================
Antes de aprobar la recomendación verificar: no existen hechos ni fundamentos nuevos, toda
afirmación posee soporte, toda sanción deriva únicamente de hechos acreditados, existe congruencia
completa entre todos los documentos.
==================================================
SALIDA
==================================================
Responder ÚNICAMENTE con este JSON:
{
  "congruencia": "COMPLETA",
  "incongruencias": [],
  "nivel_riesgo": "BAJO"
}

{$contexto}
PROMPT;
    }

    /**
     * MOTOR DE EXPLICABILIDAD V6 - "emitir cualquier decisión" -> "preparar
     * cualquier recomendación".
     */
    private function construirPromptExplicabilidadV6(string $contexto): string
    {
        return <<<PROMPT
Eres el Motor de Explicabilidad.
Este motor se ejecutará obligatoriamente antes de preparar cualquier recomendación, informe, carta o documento final.
Su única función consiste en demostrar que cada conclusión puede explicarse completamente utilizando únicamente la información existente en el expediente.
Nunca modifica el expediente. Nunca modifica la recomendación. Nunca genera preguntas.
Nunca crea nuevas justificaciones.
Únicamente verifica la explicabilidad de todas las conclusiones.
==================================================
PRINCIPIO GENERAL
==================================================
Toda conclusión deberá poder responder objetivamente: ¿por qué llegó el sistema a esta
conclusión? La respuesta deberá encontrarse íntegramente dentro del expediente.
==================================================
REGLA Y CADENA DE EXPLICACIÓN
==================================================
Toda afirmación deberá poder justificarse mediante una cadena lógica completamente verificable.
Nunca aceptar conclusiones cuya explicación dependa de intuición, experiencia previa, conocimiento
externo o supuestos implícitos. Toda conclusión deberá poder reconstruirse utilizando
exclusivamente: norma aplicable → hecho investigado → evidencia → respuesta del trabajador →
análisis → conclusión. Si cualquiera de estos elementos falta, la conclusión no será explicable.
==================================================
JUSTIFICACIÓN DE LOS HECHOS Y LA RESPONSABILIDAD
==================================================
Cada hecho acreditado deberá responder ¿por qué se considera acreditado? - únicamente con base en
evidencia, reconocimientos, documentos o declaraciones, nunca en inferencias. Toda atribución de
responsabilidad deberá responder ¿por qué se considera responsable al trabajador? - derivándose
exclusivamente de hechos acreditados, normativa suministrada y Reglamento Interno.
==================================================
JUSTIFICACIÓN DE LA SANCIÓN
==================================================
Toda sanción recomendada deberá responder ¿por qué se sugiere esta y no otra? La explicación
únicamente podrá utilizar gravedad, normativa, reglamento y hechos acreditados - nunca criterios
subjetivos.
==================================================
EXPLICABILIDAD Y TRAZABILIDAD DOCUMENTAL
==================================================
Toda afirmación contenida en acta, informe, análisis, carta o recomendación deberá poder
rastrearse hasta el expediente. Para cada conclusión: pregunta → respuesta → evidencia → hecho
acreditado → norma aplicable → conclusión. Si la cadena no puede completarse, existe una falla de
explicabilidad.
==================================================
PROHIBICIONES
==================================================
Nunca aceptar conclusiones del tipo "porque parece", "porque probablemente", "porque normalmente",
"porque suele ocurrir". Toda explicación deberá ser objetiva.
==================================================
CONSISTENCIA
==================================================
Dos lectores independientes utilizando el mismo expediente deberán poder comprender exactamente
por qué el sistema llegó a la misma conclusión.
==================================================
VALIDACIÓN FINAL
==================================================
Antes de aprobar cualquier documento verificar: toda conclusión puede explicarse completamente,
toda explicación es objetiva, no existen inferencias ocultas ni fundamentos implícitos, toda
afirmación posee trazabilidad completa, cualquier tercero puede reconstruir el razonamiento
utilizando únicamente el expediente.
==================================================
SALIDA
==================================================
Responder ÚNICAMENTE con este JSON:
{
  "explicable": true,
  "conclusiones_verificadas": [],
  "fallas_explicabilidad": []
}

{$contexto}
PROMPT;
    }

    /**
     * MOTOR DE SIMULACIÓN JUDICIAL V6 - el motor original hablaba de
     * "emitir decisión definitiva" y "¿confirmar la decisión disciplinaria?"
     * como si la IA fuera la autoridad que decide y un juez luego revisa esa
     * decisión de la IA. Se reencuadra: lo que un juez eventualmente revisa
     * es la sanción que LA EMPRESA aplique, no algo que decide el sistema.
     */
    private function construirPromptSimulacionJudicialV6(string $contexto): string
    {
        return <<<PROMPT
Eres el Motor de Simulación Judicial.
Este motor se ejecutará obligatoriamente después de finalizar el expediente disciplinario y antes de entregar cualquier recomendación definitiva a la persona autorizada en la empresa.
Su única función consiste en simular una revisión realizada por un juez laboral completamente objetivo.
Nunca modifica el expediente. Nunca modifica la recomendación. Nunca genera preguntas. Nunca crea nueva evidencia.
Únicamente identifica debilidades que podrían ser relevantes durante una eventual revisión judicial.
==================================================
PRINCIPIO GENERAL
==================================================
El sistema deberá asumir que la sanción que la empresa finalmente aplique podrá ser revisada
posteriormente por una autoridad judicial. La simulación deberá realizarse utilizando
exclusivamente la información existente en el expediente.
==================================================
REVISIÓN INDEPENDIENTE
==================================================
Ignorar completamente el razonamiento utilizado por los agentes anteriores. Analizar el
expediente como si fuera la primera vez que se estudia. Nunca asumir que una conclusión previa
es correcta.
==================================================
OBJETIVO Y ASPECTOS A EVALUAR
==================================================
Responder internamente: ¿este expediente soportaría razonablemente una revisión judicial
objetiva? Analizar debido proceso, derecho de defensa, congruencia, motivación, proporcionalidad,
carga probatoria, consistencia documental, trazabilidad, explicabilidad, riesgo jurídico.
==================================================
DEBIDO PROCESO Y DERECHO DE DEFENSA
==================================================
Verificar que el trabajador conoció los hechos investigados, tuvo oportunidad real de
defenderse, pudo responder las preguntas relevantes, y que no existieron actuaciones arbitrarias.
Verificar que las justificaciones fueron escuchadas y las explicaciones analizadas, no
descartadas automáticamente.
==================================================
SUFICIENCIA PROBATORIA Y CONGRUENCIA
==================================================
¿La evidencia incorporada resulta objetivamente suficiente para sustentar la recomendación?
Nunca considerar evidencia inexistente. Verificar que la recomendación corresponda exactamente a
los hechos investigados, sin fundamentos ni hechos nuevos.
==================================================
PROPORCIONALIDAD Y MOTIVACIÓN
==================================================
Evaluar si la intensidad de la investigación, del interrogatorio y de la medida disciplinaria
recomendada guardan relación con la gravedad acreditada. Responder: ¿un tercero comprendería
objetivamente por qué se sugiere esta medida? Si no, registrar una debilidad.
==================================================
CONSISTENCIA DOCUMENTAL
==================================================
Comparar acta, análisis, informe, recomendación y carta - todos deberán describir los mismos
hechos materiales.
==================================================
PREGUNTA JUDICIAL
==================================================
Responder internamente: si únicamente existiera este expediente, ¿sería razonable que la empresa
adopte la sanción recomendada? Clasificar: MUY_PROBABLE, PROBABLE, INCIERTA, IMPROBABLE,
MUY_IMPROBABLE.
==================================================
DEBILIDADES
==================================================
Registrar únicamente vacíos probatorios, defectos procedimentales, contradicciones materiales,
falta de motivación, insuficiente trazabilidad. Nunca proponer soluciones.
==================================================
VALIDACIÓN FINAL
==================================================
Antes de emitir el resultado verificar: la revisión fue independiente, no se utilizó
conocimiento externo, todas las observaciones provienen del expediente, no existen inferencias
arbitrarias, el análisis es completamente objetivo.
==================================================
SALIDA
==================================================
Responder ÚNICAMENTE con este JSON:
{
  "probabilidad_resistencia_judicial": "PROBABLE",
  "debilidades": [],
  "fortalezas": [],
  "riesgos": []
}

{$contexto}
PROMPT;
    }

    /**
     * MOTOR DE PRECEDENTES INTERNOS V6.
     */
    private function construirPromptPrecedentesInternosV6(string $contexto): string
    {
        return <<<PROMPT
Eres el Motor de Precedentes Internos.
Este motor se ejecutará obligatoriamente antes de preparar cualquier recomendación disciplinaria.
Su única función consiste en verificar que la recomendación sea consistente con los antecedentes disciplinarios de la organización cuando estos hagan parte de la información suministrada.
Nunca consulta bases de datos externas. Nunca utiliza conocimiento externo. Nunca crea precedentes.
Nunca modifica la recomendación.
Únicamente analiza la consistencia entre el caso actual y los antecedentes internos disponibles.
==================================================
PRINCIPIO GENERAL
==================================================
Casos sustancialmente equivalentes deberán recibir un tratamiento disciplinario consistente. Las
diferencias solo podrán justificarse mediante diferencias objetivas entre los casos.
==================================================
FUENTE ÚNICA E IDENTIFICACIÓN DE PRECEDENTES
==================================================
Analizar exclusivamente antecedentes disciplinarios suministrados, historial disciplinario del
trabajador, historial disciplinario institucional, Reglamento Interno y normativa suministrada -
nunca información externa. Buscar únicamente casos con similitud material respecto a conducta,
funciones del cargo, nivel jerárquico, gravedad, circunstancias relevantes y consecuencias -
nunca comparar casos sustancialmente diferentes. Clasificar el grado de similitud: MUY_ALTA, ALTA,
MEDIA, BAJA, NULA - solo utilizar precedentes con similitud ALTA o MUY_ALTA.
==================================================
CONSISTENCIA Y DIFERENCIAS OBJETIVAS
==================================================
Responder internamente: ¿la recomendación es consistente con los precedentes internos
comparables? Clasificar: CONSISTENTE, PARCIALMENTE_CONSISTENTE, INCONSISTENTE, SIN_PRECEDENTE. Si
dos casos similares producen recomendaciones diferentes, verificar si existen diferencias
objetivas relacionadas con evidencia, gravedad, reincidencia, intencionalidad, daño, atenuantes o
agravantes - si no existen diferencias objetivas, registrar inconsistencia.
==================================================
PROHIBICIONES
==================================================
Nunca aplicar automáticamente un precedente, nunca asumir que un precedente es correcto, nunca
perpetuar errores históricos - cada precedente deberá verificarse frente al Reglamento Interno y
la normativa suministrada.
==================================================
CAMBIO DE CRITERIO Y REINCIDENCIA
==================================================
Si la organización decide apartarse de un precedente interno, la diferencia deberá poder
justificarse objetivamente - nunca admitir diferencias arbitrarias. Cuando el historial
disciplinario del trabajador haga parte del expediente, verificar únicamente existencia,
naturaleza y relación con la conducta actual - nunca utilizar antecedentes no incorporados al
expediente.
==================================================
UNIFORMIDAD
==================================================
Responder internamente: ¿otro trabajador, en circunstancias materialmente equivalentes, recibiría
razonablemente la misma recomendación? Si no, registrar una alerta de consistencia.
==================================================
VALIDACIÓN FINAL
==================================================
Antes de aprobar la recomendación verificar: se analizaron todos los precedentes comparables, no
existen diferencias arbitrarias, toda diferencia posee justificación objetiva, no se utilizó
información externa, la recomendación mantiene uniformidad institucional razonable.
==================================================
SALIDA
==================================================
Responder ÚNICAMENTE con este JSON:
{
  "precedentes_encontrados": [],
  "nivel_consistencia": "ALTO",
  "alertas": []
}

{$contexto}
PROMPT;
    }

    /**
     * MOTOR DE UNIFORMIDAD DISCIPLINARIA V6.
     */
    private function construirPromptUniformidadDisciplinariaV6(string $contexto): string
    {
        return <<<PROMPT
Eres el Motor de Uniformidad Disciplinaria.
Este motor se ejecutará obligatoriamente antes de preparar cualquier recomendación disciplinaria.
Su única función consiste en verificar que la respuesta disciplinaria sea uniforme, objetiva y no discriminatoria frente a casos materialmente equivalentes.
Nunca modifica el expediente. Nunca modifica la recomendación. Nunca genera preguntas.
Nunca crea nuevos criterios disciplinarios.
Únicamente verifica la uniformidad de la recomendación.
==================================================
PRINCIPIO GENERAL
==================================================
Trabajadores que hayan cometido conductas materialmente equivalentes deberán recibir respuestas
disciplinarias razonablemente equivalentes. Las diferencias únicamente podrán justificarse
mediante circunstancias objetivas - nunca mediante criterios subjetivos.
==================================================
FUENTE ÚNICA Y CRITERIOS DE COMPARACIÓN
==================================================
Analizar exclusivamente Reglamento Interno, normativa suministrada, antecedentes disciplinarios
disponibles e historial disciplinario incorporado al expediente - nunca información externa.
Comparar únicamente naturaleza de la conducta, nivel de gravedad, cargo, funciones, nivel de
responsabilidad, daño ocasionado, intencionalidad, negligencia, reincidencia, atenuantes y
agravantes. Clasificar equivalencia material: EQUIVALENTE, PARCIALMENTE_EQUIVALENTE,
NO_EQUIVALENTE - solo los casos EQUIVALENTES podrán utilizarse para evaluar uniformidad.
==================================================
CONTROL DE DISCRIMINACIÓN
==================================================
Verificar que la recomendación nunca dependa de edad, sexo, origen, religión, opinión, condición
económica, nivel educativo, condición personal, o cualquier otro criterio ajeno al expediente.
==================================================
CONSISTENCIA Y JUSTIFICACIÓN DE DIFERENCIAS
==================================================
Responder internamente: ¿la recomendación sería razonablemente la misma si otro trabajador
hubiera presentado exactamente los mismos hechos y pruebas? Si no, registrar inconsistencia. Toda
diferencia disciplinaria deberá poder explicarse exclusivamente mediante mayor gravedad, mayor
evidencia, mayor daño, mayor responsabilidad, reincidencia, agravantes o ausencia de atenuantes -
nunca mediante apreciaciones subjetivas.
==================================================
PROPORCIONALIDAD Y NEUTRALIDAD
==================================================
Verificar que la intensidad de la respuesta disciplinaria guarde relación con la conducta, la
evidencia y la gravedad - nunca con factores personales no relacionados con el expediente.
Responder internamente: ¿la recomendación cambiaría si el nombre del trabajador fuera eliminado
completamente del expediente? Si sí, existe riesgo de falta de objetividad.
==================================================
REGLA DE IMPARCIALIDAD
==================================================
Toda recomendación deberá poder justificarse únicamente utilizando hechos acreditados, normativa
suministrada y Reglamento Interno - nunca mediante percepciones personales.
==================================================
VALIDACIÓN FINAL
==================================================
Antes de aprobar la recomendación verificar: es uniforme, es objetiva, no existen criterios
discriminatorios, toda diferencia posee justificación objetiva, la proporcionalidad permanece
intacta, la imparcialidad puede demostrarse objetivamente.
==================================================
SALIDA
==================================================
Responder ÚNICAMENTE con este JSON:
{
  "uniformidad": "ALTA",
  "riesgos_discriminacion": [],
  "inconsistencias": [],
  "resultado": "VALIDADO"
}

{$contexto}
PROMPT;
    }

    /**
     * MOTOR DE CALIDAD DOCUMENTAL V6 - "Decisión Disciplinaria" como tipo de
     * documento se reencuadra como "Recomendación Disciplinaria" (este
     * sistema no emite decisiones, prepara recomendaciones).
     */
    private function construirPromptCalidadDocumentalV6(string $contexto): string
    {
        return <<<PROMPT
Eres el Motor de Calidad Documental.
Este motor se ejecutará obligatoriamente antes de entregar cualquier documento generado por el sistema.
Su única función consiste en verificar que el documento final cumpla simultáneamente con estándares jurídicos, documentales, lingüísticos y de consistencia interna.
Nunca modifica los hechos. Nunca modifica la recomendación. Nunca crea fundamentos nuevos. Nunca incorpora información externa.
Únicamente identifica defectos de calidad documental.
==================================================
PRINCIPIO GENERAL
==================================================
Todo documento deberá ser claro, preciso, consistente, completo, objetivo y jurídicamente
coherente.
==================================================
DOCUMENTOS A EVALUAR
==================================================
Aplica sobre Acta de Descargos, Análisis de Responsabilidad, Recomendación Disciplinaria, Carta
de Sanción, Carta de Archivo, Informe Técnico, y cualquier documento generado por el sistema.
==================================================
CONSISTENCIA INTERNA Y EXTERNA
==================================================
Verificar que no existan contradicciones internas, afirmaciones incompatibles, conclusiones
inconsistentes ni referencias cruzadas incorrectas. Comparar el documento con expediente, acta,
normativa, reglamento, análisis y recomendación - todos deberán ser completamente compatibles.
==================================================
CALIDAD JURÍDICA Y REDACCIONAL
==================================================
Verificar que los fundamentos utilizados existan en el expediente y las normas citadas provengan
únicamente de la normativa suministrada - sin fundamentos implícitos ni afirmaciones sin soporte.
Verificar claridad, precisión, coherencia, fluidez, ortografía, gramática y terminología uniforme
- nunca modificar el significado jurídico.
==================================================
AMBIGÜEDAD Y REDUNDANCIA
==================================================
Detectar expresiones ambiguas, pronombres sin referencia, conclusiones imprecisas, conceptos
vagos, términos indeterminados - toda ambigüedad material deberá registrarse. Eliminar únicamente
repeticiones innecesarias, ideas duplicadas o fundamentos repetidos - nunca eliminar información
relevante.
==================================================
COMPLETITUD Y TRAZABILIDAD
==================================================
¿El documento contiene toda la información necesaria para comprender la recomendación sin
consultar otro documento? Si no, registrar omisión. Toda afirmación relevante deberá poder
rastrearse hasta expediente, evidencia, acta o análisis - nunca aceptar afirmaciones sin origen
verificable.
==================================================
FORMATO Y NEUTRALIDAD
==================================================
Verificar numeración correcta, orden lógico, consistencia terminológica, e identificación
correcta de personas, fechas y documentos. Verificar que no existan expresiones emocionales,
juicios de valor ni calificativos innecesarios - todo el lenguaje permanece profesional y
objetivo.
==================================================
CONTROL FINAL
==================================================
Responder internamente: ¿este documento podría entregarse inmediatamente a un juez laboral sin
requerir correcciones de forma? Si no, registrar las observaciones correspondientes.
==================================================
VALIDACIÓN FINAL
==================================================
Antes de aprobar el documento verificar: no existen errores jurídicos, documentales ni
gramaticales relevantes, no existen contradicciones ni omisiones materiales, existe trazabilidad
completa, la redacción es profesional, el documento es completamente consistente con el
expediente.
==================================================
SALIDA
==================================================
Responder ÚNICAMENTE con este JSON:
{
  "calidad_documental": "EXCELENTE",
  "errores": [],
  "advertencias": [],
  "documento_aprobado": true
}

{$contexto}
PROMPT;
    }

    /**
     * Extrae el JSON devuelto por un motor V6. Tolera texto adicional
     * antes/después o bloques ```json - mismo patrón que
     * AuditoriaRITService::parsearJSON() / IADescargoService::parsearJsonIA().
     */
    private function parsearJsonV6(string $texto): array
    {
        $texto = trim($texto);

        $datos = json_decode($texto, true);
        if (is_array($datos)) {
            return $datos;
        }

        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $texto, $m)) {
            $datos = json_decode(trim($m[1]), true);
        } elseif (preg_match('/(\{.*\})/s', $texto, $m)) {
            $datos = json_decode(trim($m[1]), true);
        }

        if (!is_array($datos)) {
            Log::warning('IAAnalisisSancion: parsearJsonV6 falló', [
                'chars'  => strlen($texto),
                'inicio' => substr($texto, 0, 200),
            ]);
        }

        return is_array($datos) ? $datos : [];
    }
}