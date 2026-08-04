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
     * - Subido (DOCX/PDF):      $sancionesRIT = conductas YA EXTRAÍDAS y cacheadas en
     *   reglamentos_internos.sanciones_extraidas (misma fuente que usa "Mi Reglamento
     *   Interno" y la tabla de sanciones de la citación, vía
     *   ReglamentoInternoService::extraerSancionesParaEmail()) - NUNCA vuelve a leer el
     *   documento completo con IA en este flujo; si no hay caché, extraerSancionesParaEmail()
     *   ya se encarga de extraer UNA vez y persistirlo para todos los flujos futuros.
     *   Solo si esa extracción viene vacía (RIT atípico donde no se detectó nada) se cae
     *   al RAG sobre el texto completo como último respaldo.
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

            // Documento subido: reutilizar las conductas ya extraídas/cacheadas
            // (mismas que se ven en "Mi Reglamento Interno") - sin volver a leer
            // ni re-procesar el RIT completo en este flujo.
            $sanciones = $service->extraerSancionesParaEmail($rit);
            if (!empty($sanciones['faltas_leves']) || !empty($sanciones['faltas_graves']) || !empty($sanciones['faltas_muy_graves'])) {
                return [$sanciones, ''];
            }

            // Respaldo: la extracción cacheada vino vacía (RIT atípico) - RAG sobre
            // el texto completo como último recurso, igual que antes de este cambio.
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
        $query = 'sanción disciplinaria debido proceso despido justa causa estabilidad laboral reforzada fuero'
            . ' proporcionalidad reincidencia descargos'
            . ' ' . ($proceso->sanciones_laborales_texto ?? '')
            . ' ' . mb_substr(strip_tags($proceso->hechos ?? ''), 0, 300);

        return app(\App\Services\JurisprudenciaService::class)->buscarContexto($query);
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
            // NUNCA agregar aquí una nota de "reincidencia" basada en $m['reincidencia']:
            // para el catálogo antiguo (SancionLaboral) ese campo viene de esReincidencia()
            // = "sancion_padre_id !== null || orden_reincidencia !== null" - es un dato del
            // CATÁLOGO (esta fila describe el escalón "2da vez" de una escala predefinida
            // para ESTE TIPO de falta, ver ConfigurarReincidenciasSanciones), NO si el
            // trabajador real ya reincidió. Un caso real (William Leal) mostró el análisis
            // marcando "Reincidencia" y justificando la gravedad con eso, mientras el
            // historial real (obtenerHistorialProcesos() más abajo en el prompt) decía
            // correctamente "PRIMER proceso disciplinario" - una contradicción detectada
            // por el propio motor V6 de Coherencia del Caso. La única fuente válida de
            // reincidencia real es el historial de procesos anteriores del trabajador.

            $detalle .= "\n";
        }

        return $detalle;
    }

    /**
     * Obtener contexto de descargos si existen
     */
    private function obtenerContextoDescargos(ProcesoDisciplinario $proceso): string
    {
        $procesal = $this->obtenerContextoProcedimental($proceso);

        $diligencia = $proceso->diligenciaDescargo;

        if (!$diligencia) {
            return $procesal . 'No se han realizado descargos aún.';
        }

        $preguntas = $diligencia->preguntas()
            ->with('respuesta')
            ->ordenadas()
            ->get();

        if ($preguntas->isEmpty()) {
            return $procesal . 'No hay descargos registrados.';
        }

        $contexto = '';
        foreach ($preguntas as $index => $pregunta) {
            $respuesta = $pregunta->respuesta?->respuesta ?? 'Sin respuesta';
            $contexto .= ($index + 1) . ". {$pregunta->pregunta}\n   Respuesta: {$respuesta}\n\n";
        }

        return $procesal . $contexto;
    }

    /**
     * Hechos PROCESALES que la plataforma ya conoce con certeza (a diferencia
     * de los hechos SUSTANTIVOS del caso, que sí requieren análisis). Todo
     * proceso disciplinario de LUPE pasa por un flujo guiado: se envía
     * una citación formal, se registra si fue leída, y el estado del proceso
     * ('descargos_realizados'/'descargos_no_realizados') indica con certeza
     * si el trabajador ejerció o no su derecho a ser oído. Antes esta
     * información NUNCA llegaba al prompt, así que la IA marcaba
     * "debido_proceso" como "no determinable" o "riesgo" aunque el sistema
     * SÍ sabía la respuesta - un usuario real lo notó ("se supone que si
     * está para emitir sanción fue porque el trabajador ya realizó los
     * descargos o no los realizó en la fecha indicada").
     */
    private function obtenerContextoProcedimental(ProcesoDisciplinario $proceso): string
    {
        $lineas = [];

        $tracking = $proceso->ultimo_tracking_citacion;
        if ($tracking && $tracking->enviado_en) {
            $lineas[] = 'El trabajador SÍ fue citado formalmente el ' . $tracking->enviado_en->format('d/m/Y')
                . ' (comunicación de apertura enviada por la plataforma con los hechos y el motivo).';
            $lineas[] = $proceso->citacionFueLeida()
                ? 'La citación fue abierta/leída por el trabajador.'
                : 'No hay confirmación de apertura del correo de la citación (esto no invalida la citación en sí, solo no hay lectura confirmada).';
        } elseif ($proceso->fecha_descargos_programada) {
            $lineas[] = 'Se programó fecha de descargos (' . $proceso->fecha_descargos_programada->format('d/m/Y')
                . '), pero no hay registro de envío de la citación por la plataforma.';
        }

        if ($proceso->estado === 'descargos_realizados') {
            $lineas[] = 'El trabajador SÍ ejerció su derecho a ser oído: completó la diligencia de descargos dentro del proceso guiado de la plataforma (ver sus respuestas más abajo).';
        } elseif ($proceso->estado === 'descargos_no_realizados') {
            $lineas[] = 'El trabajador fue citado y tuvo la oportunidad de ejercer su derecho a ser oído y aportar pruebas, pero NO asistió ni respondió dentro del plazo fijado (ver fecha_descargos_programada). Esto no es un defecto de procedimiento de la empresa - es la propia inasistencia del trabajador.';
        }

        if (empty($lineas)) {
            return '';
        }

        return "HECHOS PROCESALES YA VERIFICADOS POR LA PLATAFORMA (no requieren verificación adicional - úsalos tal cual para evaluar debido_proceso, no respondas \"no determinable\" si esta información los confirma):\n"
            . implode("\n", array_map(fn($l) => "- {$l}", $lineas))
            . "\n\n";
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

        // Caso B: datos estructurados (wizard, o RIT subido con conductas ya
        // extraídas/cacheadas - misma fuente que "Mi Reglamento Interno")
        } elseif (!empty($sancionesRIT['faltas_leves']) || !empty($sancionesRIT['faltas_graves']) || !empty($sancionesRIT['faltas_muy_graves'])) {
            $seccionRIT  = "\n═══════════════════════════════════════════════════════════════════\n";
            $seccionRIT .= "RÉGIMEN DISCIPLINARIO DEL RIT DE {$empresa->nombre_completo}:\n";
            $seccionRIT .= "═══════════════════════════════════════════════════════════════════\n";

            if (!empty($sancionesRIT['faltas_leves'])) {
                $seccionRIT .= "FALTAS LEVES definidas en el RIT:\n";
                foreach ($sancionesRIT['faltas_leves'] as $f) {
                    $seccionRIT .= "  - {$f}\n";
                }
                if (!empty($sancionesRIT['sancion_leve'])) {
                    $seccionRIT .= "  Sanción para faltas leves: {$sancionesRIT['sancion_leve']}\n";
                }
            }
            if (!empty($sancionesRIT['faltas_graves'])) {
                $seccionRIT .= "FALTAS GRAVES definidas en el RIT:\n";
                foreach ($sancionesRIT['faltas_graves'] as $f) {
                    $seccionRIT .= "  - {$f}\n";
                }
                if (!empty($sancionesRIT['sancion_grave'])) {
                    $seccionRIT .= "  Sanción para faltas graves: {$sancionesRIT['sancion_grave']}\n";
                }
            }
            if (!empty($sancionesRIT['faltas_muy_graves'])) {
                $seccionRIT .= "FALTAS MUY GRAVES definidas en el RIT:\n";
                foreach ($sancionesRIT['faltas_muy_graves'] as $f) {
                    $seccionRIT .= "  - {$f}\n";
                }
                if (!empty($sancionesRIT['sancion_muy_grave'])) {
                    $seccionRIT .= "  Sanción para faltas muy graves: {$sancionesRIT['sancion_muy_grave']}\n";
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
- DEBIDO PROCESO: citación previa, derecho a ser oído y a aportar pruebas (Art. 115 CST si aparece en el contexto). Un defecto de procedimiento anula la sanción. Si el bloque "HECHOS PROCESALES YA VERIFICADOS POR LA PLATAFORMA" (dentro de DESCARGOS DEL TRABAJADOR, más abajo) confirma la citación y la diligencia (o la inasistencia del trabajador pese a estar citado), da por cumplidos esos pasos - NO marques "no_determinable" ni adviertas falta de información sobre pasos que ese bloque ya confirma.
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
    private function llamarGemini(string $prompt, ?int $maxTokensOverride = null): string
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
                // AUDITORÍA: el piso de 16384 se mantiene por defecto porque esta
                // función también genera el análisis principal (JSON grande, con
                // garantías, motivos_analizados, bases_juridicas, etc.) y la
                // corrección completa en corregirRecomendacionConHallazgosV6() -
                // ambos con antecedentes reales de truncamiento (ver el warning de
                // MAX_TOKENS más abajo). $maxTokensOverride permite que llamadas
                // con salida sabidamente pequeña (ej. consolidarHallazgosV6, o el
                // reintento en serie de un motor V6 individual) pidan un
                // presupuesto proporcional en vez de heredar el piso grande.
                'maxOutputTokens' => $maxTokensOverride ?? max((int) ($config['max_tokens'] ?? 4096), 16384),
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
            'sancion_recomendada' => null,
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
                // AUDITORÍA: antes decía estado_recomendacion=>'sancionar' (que según
                // las REGLAS ESTRICTAS del prompt principal significa "procede aplicar
                // al menos una sanción AHORA") con sancion_principal=>'llamado_atencion',
                // pese a que este es el fallback de un fallo TOTAL del análisis (ningún
                // motor corrió). Eso sesgaba el caso con menos información posible hacia
                // "proceder" en vez de "pausar". Ahora usa 'condicionada' - el estado más
                // cercano semánticamente a "pendiente de verificación" - sin marcar
                // ninguna sanción como principal, para que no se confunda con una
                // recomendación real.
                'estado_recomendacion' => 'condicionada',
                'requiere_sancion' => false,
                'sanciones_sugeridas' => ['llamado_atencion', 'suspension', 'terminacion'],
                'sancion_principal' => null,
                'dias_suspension' => null,
                'confianza' => 'baja',
                'mensaje_para_decision' => 'El análisis automático no estuvo disponible: NINGUNA de estas opciones ha sido evaluada todavía. No proceda con ninguna sanción hasta completar una revisión manual completa de los hechos, el RIT, el CST, el historial del trabajador y sus descargos.',
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
     * Las 8 llamadas se disparan EN PARALELO (Http::pool(), ver
     * llamarGeminiEnParalelo()) en vez de una tras otra - en serie tardaban
     * varios minutos, lo que se sentía como una espera larga en vez de una
     * revisión "en tiempo real". En paralelo, el tiempo total es el de la
     * llamada más lenta de las 8 (típicamente bajo un minuto), no la suma
     * de las 8. Si alguna falla en el intento paralelo (con el modelo
     * principal), se reintenta en serie con el cascade completo de modelos
     * de llamarGemini() antes de darla por perdida.
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

        $prompts = [];
        foreach ($motores as $clave => $metodo) {
            $prompts[$clave] = $this->{$metodo}($contexto);
        }

        $respuestas = $this->llamarGeminiEnParalelo($prompts);

        $resultados = [];
        foreach ($motores as $clave => $metodo) {
            try {
                $respuesta = $respuestas[$clave] ?? null;
                if ($respuesta === null) {
                    // Falló en el intento paralelo (solo probó el modelo
                    // principal) - reintentar en serie con el cascade completo.
                    // Mismo presupuesto reducido que llamarGeminiEnParalelo(),
                    // porque sigue siendo uno de los 8 prompts de salida pequeña.
                    $respuesta = $this->llamarGemini($prompts[$clave], 4096);
                }
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
     * Orquesta el ciclo COMPLETO de revisión adicional: corre los 8 motores,
     * decide si hace falta corregir (motores en "riesgo"), corrige una vez y
     * re-evalúa sobre la versión corregida, y consolida los hallazgos en
     * puntos únicos. Es la misma lógica que antes vivía solo dentro de
     * EjecutarValidacionesV6Job::handle() - se extrajo aquí para poder
     * llamarla tanto desde el job (uso en cola) como de forma SÍNCRONA desde
     * ProcesoDisciplinarioResource (para que el modal "Emitir Sanción" abra
     * ya con la recomendación final, sin una segunda espera adentro con el
     * checklist "procesando"). El llamador es responsable de persistir el
     * resultado en el proceso (esta función no toca la BD).
     *
     * @return array{estado:string,resultados:array,analisisFinal:array,analisisOriginal:?array,motivoCorreccion:?string,puntosClave:array}
     */
    public function ejecutarRevisionCompletaV6(ProcesoDisciplinario $proceso, array $analisisSancion): array
    {
        $resultados = $this->ejecutarValidacionesV6($proceso, $analisisSancion);

        $todosFallaron = collect($resultados)->every(fn($r) => isset($r['error']));

        $analisisFinal    = $analisisSancion;
        $analisisOriginal = null;
        $motivoCorreccion = null;

        // La corrección automática SÍ se conserva (es la que de verdad protege
        // - ajusta la recomendación cuando un motor detecta algo grave), pero
        // ya NO se re-corren los 8 motores sobre la versión corregida ni se
        // consolidan los hallazgos con Gemini: esas dos llamadas (hasta 9 más)
        // solo pulían el checklist para mostrarlo, no cambiaban la recomendación
        // en sí. El checklist que se muestra abajo queda con el chequeo
        // ORIGINAL (evidencia de qué disparó la corrección); el aviso "esta
        // recomendación se ajustó automáticamente" explica el cambio arriba.
        if (!$todosFallaron) {
            $filas = $this->evaluarMotoresV6($resultados);
            $hallazgosGraves = array_filter($filas, fn($f) => $f['estado'] === 'riesgo');

            if (!empty($hallazgosGraves)) {
                try {
                    $corregido = $this->corregirRecomendacionConHallazgosV6($proceso, $analisisSancion, $hallazgosGraves);

                    if (!empty($corregido) && !empty($corregido['resumen_correccion'])) {
                        $analisisOriginal = $analisisSancion;
                        $motivoCorreccion = $corregido['resumen_correccion'];
                        $analisisFinal    = $corregido;

                        Log::info('IAAnalisisSancionService: recomendación corregida automáticamente', [
                            'proceso_id'     => $proceso->id,
                            'motores_graves' => array_keys($hallazgosGraves),
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::warning('IAAnalisisSancionService: falló la corrección automática, se conserva la recomendación original', [
                        'proceso_id' => $proceso->id,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }
        }

        return [
            'estado'            => $todosFallaron ? 'error' : 'completado',
            'resultados'        => $resultados,
            'analisisFinal'     => $analisisFinal,
            'analisisOriginal'  => $analisisOriginal,
            'motivoCorreccion'  => $motivoCorreccion,
            'puntosClave'       => [],
        ];
    }

    /**
     * Dispara varios prompts EN PARALELO contra Gemini (solo el modelo
     * principal - sin el cascade de respaldo de llamarGemini(), que es
     * inherentemente secuencial por prompt). Devuelve [clave => texto] para
     * los que respondieron bien y [clave => null] para los que fallaron
     * (el llamador decide si los reintenta en serie con el cascade completo).
     */
    private function llamarGeminiEnParalelo(array $prompts): array
    {
        if (empty($prompts)) {
            return [];
        }

        if (GeminiCircuitBreaker::isOpen()) {
            return array_fill_keys(array_keys($prompts), null);
        }

        $config = config('services.ia.gemini', []);
        $apiKey = $config['api_key'] ?? config('services.ia.' . config('services.ia.provider', 'gemini') . '.api_key');
        $modelo = $config['model'] ?? 'gemini-2.5-flash';
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelo}:generateContent?key={$apiKey}";
        // AUDITORÍA - optimización de tokens: antes esto forzaba un PISO de 16384
        // tokens de salida para los 8 motores V6, aunque cada uno solo produce un
        // JSON pequeño (máximo 2 hallazgos de ≤25 palabras por la REGLA DE
        // REDACCIÓN, más 1-4 campos cortos - ver construirContextoV6()). Con
        // Gemini 2.5 los tokens de "pensamiento" también se cobran dentro de
        // maxOutputTokens, así que un techo innecesariamente alto puede inducir
        // más tokens facturados sin mejorar la calidad de una salida que ya está
        // acotada por el propio prompt. Se baja a un techo de 4096 (todavía con
        // margen amplio sobre lo que realmente necesitan estas 8 llamadas en
        // paralelo), respetando igual un valor menor si viene en config.
        $maxTokens = min((int) ($config['max_tokens'] ?? 2048), 4096);

        $claves = array_keys($prompts);

        $respuestasPool = Http::pool(fn (\Illuminate\Http\Client\Pool $pool) => array_map(
            fn (string $clave) => $pool->as($clave)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->timeout(90)
                ->post($url, [
                    'contents' => [['parts' => [['text' => $prompts[$clave]]]]],
                    'generationConfig' => [
                        'temperature' => 0.3,
                        'maxOutputTokens' => $maxTokens,
                        'topP' => 0.95,
                    ],
                ]),
            $claves
        ));

        $resultado = [];
        foreach ($claves as $clave) {
            $resp = $respuestasPool[$clave] ?? null;

            if ($resp instanceof \Throwable || $resp === null) {
                GeminiCircuitBreaker::recordFailure($modelo);
                $resultado[$clave] = null;
                continue;
            }

            if (!$resp->successful()) {
                GeminiCircuitBreaker::recordFailure($modelo);
                $resultado[$clave] = null;
                continue;
            }

            $texto = $resp->json('candidates.0.content.parts.0.text');
            if (!is_string($texto) || $texto === '') {
                GeminiCircuitBreaker::recordFailure($modelo);
                $resultado[$clave] = null;
                continue;
            }

            GeminiCircuitBreaker::recordSuccess();
            $resultado[$clave] = $texto;
        }

        return $resultado;
    }

    /**
     * Configuración de los 6 motores V6 relevantes para Recursos Humanos
     * (explicabilidad y calidad_documental quedan fuera: son auditoría
     * interna del modelo, no información accionable para decidir una
     * sanción - ver validaciones-v6-resumen.blade.php). Único lugar donde
     * se define qué campo y qué valores hacen que un motor esté "bien" o
     * "mal", para que el job (decide si corrige la recomendación) y la
     * vista (decide qué ícono mostrar) nunca queden desincronizados.
     */
    public function metaVerificacionesV6(): array
    {
        // AUDITORÍA: se agregó 'inciertos' en cada motor - el valor intermedio
        // de su propia escala (MEDIO/INCIERTA/MEDIA/PARCIALMENTE_CONSISTENTE)
        // que antes no estaba ni en 'buenos' ni en 'malos' y por lo tanto caía
        // silenciosamente en estado 'atencion', el único estado que NUNCA
        // dispara la corrección automática en evaluarMotoresV6(). Como la
        // corrección solo puede suavizar la recomendación (nunca agravarla,
        // ver corregirRecomendacionConHallazgosV6), es seguro tratar también
        // los valores inciertos como disparadores de revisión.
        // También se corrigió 'precedentes_internos': el prompt le pedía al
        // modelo clasificar nivel_consistencia como CONSISTENTE /
        // PARCIALMENTE_CONSISTENTE / INCONSISTENTE / SIN_PRECEDENTE, pero acá
        // se comparaba contra ALTO/BAJO (una escala de similitud, no de
        // consistencia) - el campo nunca podía calificar como 'ok' ni 'riesgo'
        // de forma confiable. Ver también construirPromptPrecedentesInternosV6().
        return [
            'ponderacion_evidencia' => [
                'titulo' => 'Fuerza de las pruebas', 'icon' => 'heroicon-o-scale',
                'campo' => 'peso_global', 'buenos' => ['MUY_ALTO', 'ALTO'], 'malos' => ['BAJO', 'NULO'],
                'inciertos' => ['MEDIO'],
                'listas' => [],
            ],
            'resolucion_conflictos' => [
                'titulo' => 'Contradicciones entre las pruebas', 'icon' => 'heroicon-o-arrows-right-left',
                'campo' => 'impacto', 'buenos' => ['BAJO', 'NULO'], 'malos' => ['ALTO'],
                'inciertos' => ['MEDIO'],
                'listas' => ['conflictos_pendientes' => null],
            ],
            'congruencia_juridica' => [
                'titulo' => 'Coherencia del caso', 'icon' => 'heroicon-o-link',
                'campo' => 'nivel_riesgo', 'buenos' => ['BAJO'], 'malos' => ['ALTO'],
                'inciertos' => ['MEDIO'],
                'listas' => ['incongruencias' => null],
            ],
            'simulacion_judicial' => [
                'titulo' => 'Resistencia ante una revisión judicial', 'icon' => 'heroicon-o-building-library',
                'campo' => 'probabilidad_resistencia_judicial', 'buenos' => ['MUY_PROBABLE', 'PROBABLE'], 'malos' => ['IMPROBABLE', 'MUY_IMPROBABLE'],
                'inciertos' => ['INCIERTA'],
                'listas' => ['debilidades' => null, 'riesgos' => null],
            ],
            'precedentes_internos' => [
                'titulo' => 'Consistencia con casos anteriores', 'icon' => 'heroicon-o-archive-box',
                'campo' => 'nivel_consistencia', 'buenos' => ['CONSISTENTE', 'SIN_PRECEDENTE'], 'malos' => ['INCONSISTENTE'],
                'inciertos' => ['PARCIALMENTE_CONSISTENTE'],
                'listas' => ['alertas' => null],
            ],
            'uniformidad_disciplinaria' => [
                'titulo' => 'Trato igualitario frente a casos similares', 'icon' => 'heroicon-o-users',
                'campo' => 'uniformidad', 'buenos' => ['ALTA'], 'malos' => ['BAJA'],
                'inciertos' => ['MEDIA'],
                'listas' => ['riesgos_discriminacion' => null, 'inconsistencias' => null],
            ],
        ];
    }

    /**
     * Limpia un hallazgo (string o array) del texto crudo que devuelve
     * Gemini: si a pesar de la REGLA DE REDACCIÓN se cuela snake_case,
     * corchetes o comillas de array/JSON, se limpia para que nunca le
     * llegue a Recursos Humanos texto con forma de código.
     */
    private function limpiarTextoHallazgoV6($item): string
    {
        if (is_array($item)) {
            $texto = $item['descripcion'] ?? $item['detalle'] ?? $item['nota'] ?? implode(' - ', array_filter(array_map('strval', $item)));
        } else {
            $texto = (string) $item;
        }
        $texto = preg_replace('/\b[a-z][a-z0-9]*(_[a-z0-9]+)+\b/', ' esto ', $texto);
        $texto = str_replace(["['", "']", '["', '"]', "[", "]"], '', $texto);
        return trim(preg_replace('/\s+/', ' ', $texto));
    }

    /**
     * Evalúa los 6 motores V6 relevantes para negocio contra los resultados
     * crudos de ejecutarValidacionesV6() y devuelve, por motor: título,
     * ícono, estado ('ok'/'atencion'/'riesgo'/'na') y hallazgos ya
     * limpiados (SIN recortar - la vista decide cuántos mostrar). Usado
     * tanto por el job (para decidir si hace falta corregir la
     * recomendación) como por validaciones-v6-resumen.blade.php (para
     * mostrar el checklist) - única fuente de verdad para ambos.
     */
    public function evaluarMotoresV6(array $resultados): array
    {
        $filas = [];
        foreach ($this->metaVerificacionesV6() as $clave => $meta) {
            $r = $resultados[$clave] ?? null;
            $fallo = !is_array($r) || isset($r['error']);
            $valor = $fallo ? null : ($r[$meta['campo']] ?? null);

            $hallazgos = [];
            if (!$fallo) {
                foreach (array_keys($meta['listas']) as $listaKey) {
                    foreach (($r[$listaKey] ?? []) as $item) {
                        $hallazgos[] = $this->limpiarTextoHallazgoV6($item);
                    }
                }
            }

            if ($fallo) {
                $estado = 'na';
            } elseif (in_array($valor, $meta['malos'], true) || in_array($valor, $meta['inciertos'] ?? [], true) || count($hallazgos) > 0) {
                // AUDITORÍA: los valores 'inciertos' (el escalón intermedio de cada
                // motor, ej. MEDIO/INCIERTA/PARCIALMENTE_CONSISTENTE) ahora cuentan
                // como 'riesgo' igual que los 'malos' - antes caían en 'atencion' y
                // nunca disparaban corregirRecomendacionConHallazgosV6(), pese a ser
                // justamente el caso más ambiguo. Es seguro tratarlos así porque esa
                // corrección solo puede suavizar la recomendación, nunca agravarla.
                $estado = 'riesgo';
            } elseif (in_array($valor, $meta['buenos'], true)) {
                $estado = 'ok';
            } else {
                $estado = 'atencion';
            }

            $filas[$clave] = [
                'titulo'    => $meta['titulo'],
                'icon'      => $meta['icon'],
                'estado'    => $estado,
                'hallazgos' => $hallazgos,
            ];
        }

        return $filas;
    }

    /**
     * Los 6 motores auditan el mismo caso desde ángulos distintos (pruebas,
     * coherencia, riesgo judicial, precedentes, trato igualitario), así que
     * es normal que varios señalen el MISMO hecho de fondo con palabras
     * distintas (ej. "faltan declaraciones de testigos" puede salir en 4 de
     * los 6). Mostrar eso 4 veces agota a quien lee - se consolida en UNA
     * sola llamada a Gemini que funde los hallazgos repetidos en una lista
     * corta de puntos únicos. Devuelve [] si no hay nada que consolidar
     * (todos los motores en 'ok'/'na', o si la consolidación falla - en ese
     * caso el detalle por motor de evaluarMotoresV6() sigue disponible como
     * respaldo, nunca se pierde información).
     */
    public function consolidarHallazgosV6(array $filas): array
    {
        $hallazgosPorMotor = [];
        foreach ($filas as $fila) {
            if (in_array($fila['estado'], ['riesgo', 'atencion'], true) && !empty($fila['hallazgos'])) {
                $hallazgosPorMotor[$fila['titulo']] = $fila['hallazgos'];
            }
        }

        if (empty($hallazgosPorMotor)) {
            return [];
        }

        $texto = '';
        foreach ($hallazgosPorMotor as $titulo => $hallazgos) {
            $texto .= "- {$titulo}:\n";
            foreach ($hallazgos as $h) {
                $texto .= "    - {$h}\n";
            }
        }

        $prompt = <<<PROMPT
A continuación hay observaciones que distintas revisiones automáticas encontraron
sobre una recomendación de sanción disciplinaria. Cada revisión mira el caso desde
un ángulo distinto (pruebas, coherencia, riesgo judicial, precedentes, trato
igualitario), así que varias pueden estar diciendo, en el fondo, LO MISMO con
distintas palabras.

Tu tarea: consolida estas observaciones en una lista corta de HECHOS ÚNICOS que una
persona de Recursos Humanos debería tener en cuenta antes de confirmar la sanción,
sin repetir la misma idea más de una vez.

OBSERVACIONES:
{$texto}

REGLAS:
- Máximo 4 puntos - si dos o más observaciones dicen lo mismo, fusiónalas en una sola.
- Cada punto en UNA oración corta (máximo 25 palabras), español simple, sin
  tecnicismos ni nombres de revisiones o reglas internas.
- Ordena del punto más importante al menos importante.
- Si las observaciones ya son todas distintas entre sí, devuélvelas tal cual
  (hasta el máximo de 4, priorizando las más graves).

Responde EXACTAMENTE con este JSON:
{
  "puntos": ["primer punto", "segundo punto"]
}
Genera SOLO el JSON, sin markdown ni texto fuera del objeto.
PROMPT;

        // Salida acotada a 4 puntos de máximo 25 palabras - no necesita el piso
        // de 16384 que usa el análisis principal.
        $respuesta = $this->llamarGemini($prompt, 2048);
        $datos = $this->parsearJsonV6($respuesta);

        return is_array($datos['puntos'] ?? null)
            ? array_values(array_filter(array_map('strval', $datos['puntos'])))
            : [];
    }

    /**
     * Si evaluarMotoresV6() marcó algún motor como 'riesgo', se pide UNA
     * corrección de la recomendación original en el MISMO esquema JSON de
     * analizarYSugerirSanciones(), indicando exactamente qué hallazgos debe
     * resolver. Nunca se reemplaza la recomendación en silencio: devuelve
     * además "resumen_correccion" en español simple para mostrarlo con
     * transparencia en el modal (ver correccion_v6_motivo en el modelo).
     *
     * Se llama UNA sola vez por ciclo de análisis (no hay recursión): el
     * job re-evalúa los motores sobre la versión corregida para refrescar
     * el checklist, pero no vuelve a llamar a este método aunque el
     * resultado corregido siga marcando algo grave.
     */
    public function corregirRecomendacionConHallazgosV6(ProcesoDisciplinario $proceso, array $analisisOriginal, array $hallazgosGraves): array
    {
        $empresa = $proceso->empresa;
        [$sancionesRIT, $contextoRITRag] = $this->obtenerContextoRIT($empresa, $proceso);
        $contextoCST            = $this->obtenerContextoCST($proceso);
        $contextoJurisprudencia = $this->obtenerContextoJurisprudencia($proceso);
        $contextoDescargos      = $this->obtenerContextoDescargos($proceso);
        $ritTexto = $contextoRITRag ?: (
            !empty($sancionesRIT) ? json_encode($sancionesRIT, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : '(sin RIT disponible)'
        );

        $hallazgosTexto = '';
        foreach ($hallazgosGraves as $motor) {
            $hallazgosTexto .= "- {$motor['titulo']}:\n";
            foreach ($motor['hallazgos'] as $h) {
                $hallazgosTexto .= "    - {$h}\n";
            }
        }

        $analisisJson = json_encode($analisisOriginal, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $prompt = <<<PROMPT
Eres el mismo abogado laboralista que preparó la recomendación de sanción de abajo.
Una revisión adicional automática encontró problemas GRAVES en esa recomendación (ver
"PROBLEMAS A RESOLVER"). Tu tarea es preparar una VERSIÓN CORREGIDA de la recomendación
que resuelva esos problemas, sin inventar hechos nuevos. Sigues sin decidir la sanción -
solo preparas una recomendación para la persona autorizada en la empresa que decidirá.

REGLAS PARA LA CORRECCIÓN:
- Si un problema señala que falta verificar un hecho alegado por el trabajador (ej. una
  excusa médica no confirmada, o que la empresa no ha probado que la conducta ocurrió
  tal como se describe), la corrección debe reflejarlo en
  "recomendacion_final.estado_recomendacion" = "condicionada" (nunca "sancionar"),
  dejándolo explícito en "mensaje_para_decision".
- IMPORTANTE - distingue DOS problemas distintos y no los mezcles:
  (a) Duda sobre si la conducta OCURRIÓ o está probada (faltan pruebas, el trabajador
      lo niega, no se desvirtuaron sus descargos). Esto NO cambia qué tan grave sería
      la conducta SI se confirma - la gravedad ya fue determinada y no debes reabrirla
      aquí. En este caso, dentro del MISMO rango ya ofrecido para esa gravedad,
      inclínate hacia la opción MENOS severa (ej. entre suspensión y terminación para
      una falta grave, prioriza suspensión y/o reduce "dias_suspension" al mínimo
      defendible) - pero NUNCA agregues una sanción de una categoría de gravedad
      distinta (ej. llamado de atención) que el propio análisis original ya consideró
      desproporcionada para esta gravedad. Sería incoherente e igual de arriesgado.
  (b) Duda sobre si la gravedad misma está bien calificada (el hallazgo cuestiona que
      la conducta -de haber ocurrido- sea tan grave como se dijo, no solo si ocurrió).
      Solo en este caso puedes ampliar "sanciones_sugeridas"/"sancion_principal" hacia
      una sanción de menor entidad.
- Si un problema es tan grave que ninguna sanción sería defendible (ej. una violación
  clara e insubsanable del debido proceso, como falta de inmediatez, caducidad de la
  facultad disciplinaria, o un vicio de trámite que anularía cualquier medida), el
  estado debe pasar a "no_sancionar". EN ESE CASO, exactamente como en el análisis
  original: "sanciones_sugeridas" = [] (vacío), "sancion_principal" = null,
  "dias_suspension" = null - NUNCA dejes un tipo de sanción específico (ni siquiera
  "llamado_atencion") si el estado final es "no_sancionar". Deja la explicación
  completa en "mensaje_para_decision" y en "razones_no_recomendadas.no_sancion".
- REGLA DE CONSISTENCIA (la más importante, no la olvides): "resumen_correccion" debe
  describir EXACTAMENTE lo que cambiaste en "sancion_principal", "sanciones_sugeridas"
  y "dias_suspension" - PROHIBIDO decir que bajaste a una sanción (ej. "llamado de
  atención") si esos campos no quedaron con ese valor. Si solo tocaste
  "estado_recomendacion" y el mensaje (caso (a) sin cambiar el rango, o priorizando la
  opción menos severa dentro del mismo rango), el resumen debe describir SOLO eso -
  nunca inventes en el resumen un cambio de tipo de sanción que los campos no reflejan.
- Si un problema señala una contradicción interna (ej. dos sanciones distintas para el
  mismo caso en distintas partes del JSON), la corrección debe dejar UNA sola línea
  coherente en todo el documento.
- Si un problema señala que un hecho de la recomendación no aparece en los hechos
  investigados originales, elimina esa afirmación de la recomendación - no la sostengas.
- Nunca agraves la sanción al corregir (nunca subas de severidad) - la corrección solo
  puede mantener o suavizar la sanción ofrecida, nunca endurecerla.
- Mantén EXACTAMENTE el mismo esquema JSON de la recomendación original (las mismas
  claves, con la misma estructura).

RECOMENDACIÓN ORIGINAL (a corregir):
{$analisisJson}

PROBLEMAS GRAVES A RESOLVER (detectados por la revisión adicional):
{$hallazgosTexto}

REGLAMENTO INTERNO DE {$empresa->nombre_completo}:
{$ritTexto}

CÓDIGO SUSTANTIVO DEL TRABAJO (artículos aplicables):
{$contextoCST}

JURISPRUDENCIA APLICABLE:
{$contextoJurisprudencia}

DESCARGOS DEL TRABAJADOR:
{$contextoDescargos}

Responde EXACTAMENTE con el mismo JSON de la recomendación original (todas sus claves,
corrigiendo solo lo necesario en "recomendacion_final" y demás campos - los CAMPOS
ESTRUCTURADOS son lo que de verdad se usa, no una descripción aparte de ellos) y agrega
ADEMÁS esta clave nueva al final del objeto:
"motivo_ajuste": una frase corta (máximo 25 palabras) en español simple, sin tecnicismos,
explicando SOLO POR QUÉ hiciste (o no hiciste) el ajuste - NUNCA describas aquí qué
valores quedaron en "recomendacion_final" (eso se muestra aparte, calculado directamente
de esos campos - si lo describes aquí y no coincide con lo que de verdad pusiste en
"recomendacion_final", se genera una contradicción grave). Ejemplo correcto: "El
expediente no verificó la excusa médica que alegó el trabajador." Ejemplo INCORRECTO
(no hagas esto): "Se cambió a condicionada y se redujo a 1 día" (esto describe valores,
no el motivo).
Genera SOLO el JSON, sin markdown ni texto fuera del objeto.
PROMPT;

        $respuesta = $this->llamarGemini($prompt);
        $corregido = $this->normalizarCoherenciaNoSancionar($this->parsearAnalisisIA($respuesta));

        return $this->construirResumenCorreccionDeterministico($analisisOriginal, $corregido);
    }

    /**
     * Construye "resumen_correccion" a partir de una comparación DETERMINÍSTICA
     * (en PHP, no confiando en el modelo) entre la recomendación original y la
     * corregida. Motivo: 3 casos reales distintos mostraron el modelo describiendo
     * en texto un cambio ("se ajustó a condicionada", "se redujo a 1 día", "se bajó
     * a llamado de atención") que NO aplicó de verdad en los campos estructurados -
     * la vista y el documento final usan los campos, así que un resumen que solo
     * describe intención sin verificar la estructura real es peor que no tener
     * resumen. Aquí se calcula el diff real de "recomendacion_final" y se antepone
     * el "motivo_ajuste" (solo el porqué) que pidió el prompt. Si no hubo ningún
     * cambio estructural real, se quita "resumen_correccion" por completo - el job
     * lo interpreta como que la corrección no aplicó nada (conserva la original).
     */
    private function construirResumenCorreccionDeterministico(array $analisisOriginal, array $corregido): array
    {
        $motivo = trim((string) ($corregido['motivo_ajuste'] ?? ''));
        unset($corregido['motivo_ajuste']);

        $of = $analisisOriginal['recomendacion_final'] ?? [];
        $cf = $corregido['recomendacion_final'] ?? [];

        $etiquetasEstado = ['sancionar' => 'proceder con la sanción', 'condicionada' => 'condicionada a verificación', 'no_sancionar' => 'no sancionar'];
        $etiquetasSancion = ['llamado_atencion' => 'llamado de atención', 'suspension' => 'suspensión', 'multa' => 'multa', 'terminacion' => 'terminación de contrato'];

        $cambios = [];

        $estadoAntes = $of['estado_recomendacion'] ?? null;
        $estadoDespues = $cf['estado_recomendacion'] ?? null;
        if ($estadoAntes !== $estadoDespues) {
            $cambios[] = 'el estado pasó de "' . ($etiquetasEstado[$estadoAntes] ?? ($estadoAntes ?? 'sin definir'))
                . '" a "' . ($etiquetasEstado[$estadoDespues] ?? ($estadoDespues ?? 'sin definir')) . '"';
        }

        $principalAntes = $of['sancion_principal'] ?? null;
        $principalDespues = $cf['sancion_principal'] ?? null;
        if ($principalAntes !== $principalDespues) {
            $cambios[] = 'la sanción principal pasó de "' . ($etiquetasSancion[$principalAntes] ?? ($principalAntes ?? 'ninguna'))
                . '" a "' . ($etiquetasSancion[$principalDespues] ?? ($principalDespues ?? 'ninguna')) . '"';
        }

        $diasAntes = $of['dias_suspension'] ?? null;
        $diasDespues = $cf['dias_suspension'] ?? null;
        if ($diasAntes !== $diasDespues && ($diasAntes !== null || $diasDespues !== null)) {
            $cambios[] = 'los días de suspensión pasaron de ' . ($diasAntes ?? 'sin suspensión') . ' a ' . ($diasDespues ?? 'sin suspensión');
        }

        $confAntes = $of['confianza'] ?? null;
        $confDespues = $cf['confianza'] ?? null;
        if ($confAntes !== $confDespues) {
            $cambios[] = 'la confianza bajó de "' . ($confAntes ?? 'sin definir') . '" a "' . ($confDespues ?? 'sin definir') . '"';
        }

        if (empty($cambios)) {
            // Nada cambió realmente en la estructura - no se puede afirmar que hubo
            // corrección aunque el modelo haya devuelto un motivo. Sin
            // resumen_correccion, el job trata este ciclo como "sin corrección".
            unset($corregido['resumen_correccion']);
            return $corregido;
        }

        $textoMotivo = $motivo !== '' ? rtrim($motivo, '. ') . '. ' : '';
        $corregido['resumen_correccion'] = $textoMotivo . 'Como resultado, ' . implode('; ', $cambios) . '.';

        return $corregido;
    }

    /**
     * Red de seguridad determinística (no depender solo de que el prompt se cumpla):
     * un caso real mostró la corrección poniendo estado_recomendacion="no_sancionar"
     * pero dejando "sancion_principal": "llamado_atencion" sin vaciar - la vista
     * (emitir-sancion-analisis.blade.php) decide si mostrar "No aplicar sanción" según
     * si sanciones_sugeridas viene vacío, así que esa inconsistencia hacía que la UI
     * mostrara "La IA recomienda: Llamado de Atención" pese a recomendar no sancionar.
     * Esta es la MISMA regla de coherencia que ya exige el análisis original
     * (construirPromptAnalisisSancion) - se fuerza aquí en PHP para garantizarla
     * siempre, sin importar si el modelo la respetó en su respuesta.
     */
    private function normalizarCoherenciaNoSancionar(array $analisis): array
    {
        if (($analisis['recomendacion_final']['estado_recomendacion'] ?? null) === 'no_sancionar') {
            $analisis['recomendacion_final']['sanciones_sugeridas'] = [];
            $analisis['recomendacion_final']['sancion_principal']   = null;
            $analisis['recomendacion_final']['dias_suspension']     = null;
            $analisis['recomendacion_final']['requiere_sancion']    = false;

            // "sancion_recomendada" es un campo del NIVEL SUPERIOR del análisis,
            // independiente de "recomendacion_final" - el modelo no siempre lo
            // sincroniza al corregir. ProcesoDisciplinarioResource lo usa como
            // ÚLTIMO respaldo para decidir qué sanción mostrar como principal
            // cuando "recomendacion_final.sancion_principal" viene vacío - si no
            // se limpia aquí también, la vista sigue mostrando "La IA recomienda:
            // [el tipo viejo]" pese a que la recomendación real es no sancionar.
            // Caso real detectado: Lorena Conde (mostraba "Llamado de Atención"
            // como principal con estado_recomendacion="no_sancionar").
            $analisis['sancion_recomendada'] = null;
        }

        return $analisis;
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
Eres el Motor de Ponderación de Evidencia. Se ejecuta antes del Análisis de Responsabilidad.
Única función: valorar objetivamente la fuerza probatoria de cada elemento del expediente -
nunca determina responsabilidad, nunca modifica el expediente, nunca genera preguntas.
FUENTE: solo evidencia documental, tecnológica, física, declaraciones, reconocimientos y
registros incorporados válidamente al expediente - nunca información externa.
Evalúa cada evidencia por separado, sin heredar credibilidad de otra ni incrementar su valor
automáticamente por estar aislada. Criterios: pertinencia (¿demuestra directamente un hecho
material?), confiabilidad (razones objetivas, nunca intuición), autenticidad (si hay duda
objetiva, reduce el peso), corroboración (pruebas independientes que coinciden fortalecen la
conclusión, pero nunca dupliques artificialmente el peso de una misma fuente).
Si dos pruebas se contradicen, no decidas cuál prevalece aquí - remite el conflicto al Motor de
Resolución de Conflictos Probatorios. Una confesión (evalúa espontaneidad, claridad,
consistencia, corroboración) nunca elimina automáticamente la necesidad de valorar el resto del
expediente.
ESCALA (campo "peso_global"): MUY_ALTO, ALTO, MEDIO, BAJO o NULO - nunca porcentajes.
SALIDA - responde ÚNICAMENTE este JSON:
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
Eres el Motor de Resolución de Conflictos Probatorios. Se activa cuando dos o más elementos
probatorios relevantes son incompatibles entre sí. Única función: determinar si el conflicto
puede resolverse objetivamente usando solo el expediente - nunca crea, elimina ni modifica
evidencia o declaraciones, nunca determina responsabilidad disciplinaria.
La existencia de evidencia contradictoria no implica automáticamente que una sea falsa: antes de
darle más peso a una, evalúa si la diferencia se explica razonablemente por error de memoria,
percepción, desfase temporal, información incompleta, lenguaje ambiguo o contexto distinto - si
alguna explicación es plausible, no lo clasifiques como conflicto material. Evalúa cada evidencia
por separado (una prueba nunca pierde valor solo porque otra la contradice) y determina cuál
tiene mayor respaldo independiente usando solo lo incorporado al expediente, nunca intuición.
Clasifica cada conflicto: CRÍTICO, MATERIAL, SECUNDARIO o IRRELEVANTE - solo CRÍTICO y MATERIAL
afectan la recomendación. Nunca favorezcas automáticamente al empleador, al trabajador, la prueba
documental, la confesión o la evidencia tecnológica - toda preferencia debe justificarse solo con
corroboración, consistencia, autenticidad, integridad o trazabilidad, nunca con opiniones.
Si el expediente no permite resolver el conflicto objetivamente, mantén ambas hipótesis activas y
regístralo en conflictos_pendientes - nunca lo resuelvas por probabilidad.
ESCALA (campo "impacto"): BAJO, MEDIO o ALTO, según cuánto podría cambiar la recomendación si el
conflicto queda sin resolver.
SALIDA - responde ÚNICAMENTE este JSON:
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
Eres el Motor de Congruencia Jurídica. Se ejecuta antes de preparar cualquier recomendación
disciplinaria y antes de generar cualquier documento final. Única función: verificar que exista
congruencia absoluta entre hechos investigados, evidencia, análisis, recomendación y sanción
propuesta - nunca modifica el expediente, las pruebas ni la recomendación.
Toda recomendación debe derivarse exclusivamente de hechos efectivamente investigados y
acreditados. Cada hecho usado en la recomendación debe aparecer previamente en la investigación,
el interrogatorio, el acta y el análisis - si un hecho aparece únicamente en la recomendación, es
incongruencia crítica. Toda sanción propuesta debe corresponder solo a hechos acreditados, la
gravedad determinada y la normativa suministrada (RIT y normas del contexto) - nunca a
fundamentos jurídicos distintos ni a hechos accesorios.
Verifica consistencia documental y temporal: acta, informe técnico, análisis, recomendación y
carta final deben describir exactamente los mismos hechos materiales, ocurridos dentro de la
cronología investigada - nunca incorpores eventos posteriores. Todo hecho descartado durante la
investigación queda prohibido como fundamento futuro; todo hecho nunca investigado formalmente no
puede aparecer en ningún documento. La responsabilidad nunca puede ampliarse más allá de las
conductas investigadas, acreditadas y discutidas durante el debido proceso.
Traza cada fundamento: pregunta → respuesta → acta → análisis → recomendación → documento final -
si la cadena se rompe, hay incongruencia. Responde internamente: ¿la recomendación contiene
afirmaciones nunca investigadas? ¿la motivación introduce argumentos nuevos? ¿la carta amplía el
alcance del análisis? Si alguna respuesta es SÍ, es incongruencia crítica.
ESCALA (campo "nivel_riesgo"): BAJO, MEDIO o ALTO.
SALIDA - responde ÚNICAMENTE este JSON:
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
Eres el Motor de Explicabilidad. Se ejecuta antes de preparar cualquier recomendación, informe,
carta o documento final. Única función: verificar que cada conclusión pueda explicarse
completamente con la información del expediente - nunca modifica el expediente ni la
recomendación, nunca genera preguntas ni crea nuevas justificaciones.
Toda conclusión debe poder reconstruirse siguiendo exclusivamente: norma aplicable → hecho
investigado → evidencia → respuesta del trabajador → análisis → conclusión - si falta un eslabón,
no es explicable. Nunca aceptes conclusiones que dependan de intuición, experiencia previa,
conocimiento externo o supuestos implícitos, ni frases como "porque parece", "porque
probablemente" o "porque normalmente" - toda explicación debe ser objetiva.
Cada hecho acreditado debe responder por qué se considera acreditado (solo con evidencia,
reconocimientos, documentos o declaraciones, nunca inferencias). Toda atribución de
responsabilidad debe derivarse exclusivamente de hechos acreditados, normativa suministrada y
RIT. Toda sanción recomendada debe responder por qué esta y no otra, usando solo gravedad,
normativa, reglamento y hechos acreditados - nunca criterios subjetivos.
Toda afirmación en acta, informe, análisis, carta o recomendación debe poder rastrearse hasta el
expediente siguiendo: pregunta → respuesta → evidencia → hecho acreditado → norma aplicable →
conclusión. Dos lectores independientes con el mismo expediente deben poder comprender
exactamente por qué el sistema llegó a la misma conclusión.
SALIDA - responde ÚNICAMENTE este JSON:
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
Eres el Motor de Simulación Judicial. Se ejecuta después de finalizar el expediente disciplinario
y antes de entregar cualquier recomendación definitiva a la persona autorizada en la empresa.
Única función: simular una revisión hecha por un juez laboral completamente objetivo, usando solo
lo que hay en el expediente - nunca modifica el expediente, la recomendación, ni crea evidencia
nueva, nunca genera preguntas.
Ignora el razonamiento de los agentes anteriores; analiza el expediente como si fuera la primera
vez, sin asumir que una conclusión previa es correcta. Evalúa: debido proceso y derecho de defensa
(¿el trabajador conoció los hechos, tuvo oportunidad real de defenderse, sus justificaciones
fueron escuchadas y no descartadas automáticamente?); suficiencia probatoria y congruencia (¿la
evidencia sostiene la recomendación sin hechos ni fundamentos nuevos?); proporcionalidad y
motivación (¿un tercero entendería objetivamente por qué se sugiere esta medida, dada la
intensidad de la investigación y la gravedad acreditada?); consistencia documental (acta,
análisis, informe, recomendación y carta describen los mismos hechos materiales).
Responde internamente: si únicamente existiera este expediente, ¿sería razonable que la empresa
adopte la sanción recomendada? Clasifica probabilidad_resistencia_judicial en exactamente una:
MUY_PROBABLE, PROBABLE, INCIERTA, IMPROBABLE, MUY_IMPROBABLE. Registra en "debilidades" solo
vacíos probatorios, defectos procedimentales, contradicciones materiales, falta de motivación o
insuficiente trazabilidad - nunca propongas soluciones.
SALIDA - responde ÚNICAMENTE este JSON:
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
Eres el Motor de Precedentes Internos. Se ejecuta antes de preparar cualquier recomendación
disciplinaria. Única función: verificar que la recomendación sea consistente con los antecedentes
disciplinarios de la organización cuando existan - nunca consultas fuentes externas, nunca creas
precedentes, nunca modificas la recomendación.
FUENTE: solo antecedentes disciplinarios suministrados, historial del trabajador, historial
disciplinario institucional, RIT y normativa suministrada - nunca información externa.
Casos sustancialmente equivalentes deben recibir un tratamiento consistente; las diferencias solo
se justifican por diferencias objetivas (evidencia, gravedad, reincidencia, intencionalidad, daño,
atenuantes o agravantes) - nunca por criterios arbitrarios. Compara solo casos con similitud
material en conducta, funciones, nivel jerárquico, gravedad y consecuencias, clasificando el grado
de similitud (MUY_ALTA, ALTA, MEDIA, BAJA, NULA) - usa únicamente precedentes con similitud ALTA o
MUY_ALTA. Nunca apliques un precedente automáticamente, nunca asumas que es correcto, nunca
perpetúes un error histórico: cada precedente debe verificarse contra el RIT y la normativa
suministrada. Si la organización se aparta de un precedente, esa diferencia también debe poder
justificarse objetivamente. Responde internamente: ¿otro trabajador, en circunstancias
materialmente equivalentes, recibiría razonablemente la misma recomendación? Si no, registra una
alerta.
ESCALA (campo "nivel_consistencia") - usa EXACTAMENTE una de estas cuatro palabras, sin mezclarlas
con la escala de similitud usada arriba:
CONSISTENTE - coincide con precedentes comparables sin diferencias arbitrarias.
PARCIALMENTE_CONSISTENTE - hay una diferencia frente a un precedente comparable, con alguna
justificación objetiva parcial.
INCONSISTENTE - dos casos similares reciben recomendaciones distintas sin ninguna diferencia
objetiva que lo explique.
SIN_PRECEDENTE - no hay antecedentes internos comparables disponibles.
SALIDA - responde ÚNICAMENTE este JSON:
{
  "precedentes_encontrados": [],
  "nivel_consistencia": "CONSISTENTE",
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
Eres el Motor de Uniformidad Disciplinaria. Se ejecuta antes de preparar cualquier recomendación
disciplinaria. Única función: verificar que trabajadores con conductas materialmente equivalentes
reciban respuestas disciplinarias razonablemente equivalentes - nunca modifica el expediente ni
la recomendación, nunca genera preguntas ni crea criterios disciplinarios nuevos.
FUENTE: solo RIT, normativa suministrada, antecedentes disciplinarios disponibles e historial
incorporado al expediente - nunca información externa. Compara naturaleza de la conducta,
gravedad, cargo, funciones, responsabilidad, daño, intencionalidad, negligencia, reincidencia,
atenuantes y agravantes, clasificando cada comparación como EQUIVALENTE, PARCIALMENTE_EQUIVALENTE
o NO_EQUIVALENTE - solo los casos EQUIVALENTES sirven para evaluar uniformidad. Toda diferencia
disciplinaria debe explicarse exclusivamente por mayor gravedad, evidencia, daño, responsabilidad,
reincidencia, agravantes o ausencia de atenuantes - nunca por apreciaciones subjetivas.
CONTROL DE DISCRIMINACIÓN: la recomendación nunca puede depender de edad, sexo, origen, religión,
opinión, condición económica, nivel educativo o cualquier criterio ajeno al expediente.
Responde internamente: ¿sería razonablemente la misma recomendación si otro trabajador hubiera
presentado exactamente los mismos hechos y pruebas? ¿cambiaría si se eliminara el nombre del
trabajador del expediente? Si alguna respuesta es "no"/"sí" respectivamente, hay riesgo de falta
de objetividad - regístralo.
ESCALA (campo "uniformidad"): ALTA, MEDIA o BAJA.
SALIDA - responde ÚNICAMENTE este JSON:
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
Eres el Motor de Calidad Documental. Se ejecuta antes de entregar cualquier documento generado por
el sistema (Acta de Descargos, Análisis de Responsabilidad, Recomendación Disciplinaria, Carta de
Sanción, Carta de Archivo, Informe Técnico). Única función: verificar que el documento cumpla
estándares jurídicos, documentales, lingüísticos y de consistencia interna - nunca modifica los
hechos, la recomendación, ni crea fundamentos nuevos o incorpora información externa.
Verifica que no existan contradicciones internas ni referencias cruzadas incorrectas entre
expediente, acta, normativa, reglamento, análisis y recomendación - todos deben ser compatibles.
Todo fundamento debe existir en el expediente y toda norma citada debe provenir solo de la
normativa suministrada - sin afirmaciones sin soporte. Revisa claridad, precisión, coherencia,
ortografía, gramática y terminología uniforme, sin alterar el significado jurídico.
Detecta ambigüedad material (pronombres sin referencia, conclusiones imprecisas, términos
indeterminados) y elimina solo redundancia real (repeticiones o fundamentos duplicados) - nunca
información relevante. Verifica que el documento sea autocontenido (comprensible sin consultar
otro documento) y que toda afirmación relevante sea trazable hasta expediente, evidencia, acta o
análisis. Verifica numeración correcta, orden lógico e identificación correcta de personas, fechas
y documentos; el lenguaje debe ser profesional y objetivo, sin juicios de valor.
Responde internamente: ¿este documento podría entregarse a un juez laboral sin correcciones de
forma? Si no, registra las observaciones correspondientes.
SALIDA - responde ÚNICAMENTE este JSON:
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