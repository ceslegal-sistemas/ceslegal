<?php

namespace App\Services;

use App\Models\DiligenciaDescargo;
use App\Models\PreguntaDescargo;
use App\Models\RespuestaDescargo;
use App\Models\TrazabilidadIADescargo;
use App\Models\ArticuloLegal;
use App\Services\BibliotecaLegalService;
use App\Services\ReglamentoInternoService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IADescargoService
{
    protected string $provider;
    protected array $config;

    // Límite máximo de preguntas totales por diligencia
    const LIMITE_MAXIMO_PREGUNTAS = 30;

    // Preguntas estándar iniciales
    const PREGUNTAS_INICIALES = [
        /* 0 */ '¿Va a asistir acompañado(a) por alguien?',
        /* 1 */ '¿Qué relación tiene esa persona con usted?',
        /* 2 */ 'Acompañante: indique su nombre completo y en qué calidad asiste a esta diligencia (apoyo moral, representante sindical, apoderado, testigo u otro).',
        /* 3 */ '¿Trabaja usted para una empresa contratista o tercero diferente a {empresa}?',
        /* 4 */ '¿Cuál es el nombre de esa empresa contratista o tercero?',
    ];

    // Mapa de dependencias entre preguntas iniciales: índice_hijo => índice_padre
    // Si la respuesta al padre contiene "no", las hijas se auto-responden "No aplica"
    const DEPENDENCIAS_INICIALES = [
        1 => 0,   // relación acompañante  → ¿va acompañado?
        2 => 0,   // identificación acomp. → ¿va acompañado?
        4 => 3,   // nombre contratista    → ¿trabaja para contratista?
    ];

    // Preguntas estándar de cierre
    const PREGUNTAS_CIERRE = [
        '¿Le avisó esta situación a su jefe directo?',
        '¿Ha estado antes en descargos?',
    ];

    public function __construct()
    {
        $this->provider = config('services.ia.provider', 'openai');
        $this->config = config("services.ia.{$this->provider}", []);
    }

    /**
     * Genera preguntas dinámicas basadas en la respuesta del trabajador
     *
     * @param PreguntaDescargo $preguntaRespondida
     * @param RespuestaDescargo $respuesta
     * @return array
     */
    public function generarPreguntasDinamicas(PreguntaDescargo $preguntaRespondida, RespuestaDescargo $respuesta): array
    {
        $diligencia = $preguntaRespondida->diligenciaDescargo;
        $proceso = $diligencia->proceso;

        // Verificar que no se exceda el límite máximo de preguntas
        $totalPreguntasActuales = $diligencia->preguntas()->count();
        if ($totalPreguntasActuales >= self::LIMITE_MAXIMO_PREGUNTAS) {
            Log::warning('No se pueden generar más preguntas dinámicas - límite alcanzado', [
                'diligencia_id' => $diligencia->id,
                'total_preguntas' => $totalPreguntasActuales,
                'limite_maximo' => self::LIMITE_MAXIMO_PREGUNTAS,
            ]);
            return [];
        }

        $contexto = $this->construirContexto($diligencia);
        $prompt = $this->construirPromptGeneracionPreguntas($contexto, $preguntaRespondida, $respuesta);

        try {
            $respuestaIA = $this->llamarIA($prompt);

            $this->registrarTrazabilidad(
                $diligencia->id,
                $prompt,
                $respuestaIA,
                'generacion_preguntas'
            );

            // Calcular cuántas preguntas dinámicas se pueden agregar sin exceder el límite
            $preguntasDisponibles = self::LIMITE_MAXIMO_PREGUNTAS - $totalPreguntasActuales;
            $limitePreguntasDinamicas = min(2, $preguntasDisponibles);

            // Limitar preguntas dinámicas según el espacio disponible
            $nuevasPreguntas = $this->parsearRespuestaIA($respuestaIA, $limitePreguntasDinamicas);

            if (count($nuevasPreguntas) > 0) {
                return $this->guardarNuevasPreguntas($diligencia, $nuevasPreguntas, $preguntaRespondida->id);
            }

            return [];
        } catch (\Exception $e) {
            Log::error('Error al generar preguntas dinámicas con IA', [
                'pregunta_id' => $preguntaRespondida->id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Construye el contexto del proceso disciplinario
     */
    protected function construirContexto(DiligenciaDescargo $diligencia): array
    {
        $proceso   = $diligencia->proceso;
        $empresaId = $proceso->empresa_id ?? $proceso->trabajador?->empresa_id ?? null;

        // Artículos legales con texto completo (no solo título)
        $articulosLegales = [];
        if (!empty($proceso->articulos_legales_ids)) {
            $articulosLegales = ArticuloLegal::whereIn('id', $proceso->articulos_legales_ids)
                ->get()
                ->map(function ($art) {
                    $texto    = $art->getRawOriginal('texto_completo') ?? $art->descripcion ?? '';
                    $extracto = $texto ? "\n   Texto: " . mb_substr($texto, 0, 500) : '';
                    return "{$art->codigo}: {$art->titulo}{$extracto}";
                })
                ->toArray();
        }

        // Solo preguntas YA RESPONDIDAS para el razonamiento contextual
        $preguntasYRespuestas = $diligencia->preguntas()
            ->with('respuesta')
            ->activas()
            ->whereHas('respuesta')
            ->get()
            ->map(function ($pregunta) {
                return [
                    'pregunta' => $pregunta->pregunta,
                    'respuesta' => $pregunta->respuesta->respuesta,
                    'es_ia'     => $pregunta->es_generada_por_ia,
                ];
            })
            ->toArray();

        // Lista completa de preguntas (incluyendo pendientes) solo para anti-repetición
        $todasLasPreguntas = $diligencia->preguntas()
            ->activas()
            ->pluck('pregunta')
            ->toArray();

        // Contexto del RIT de la empresa y normas relevantes por RAG
        $ritContexto = $empresaId ? $this->obtenerContextoRIT($empresaId) : '';
        $normasRag   = $this->buscarNormasRelevantes($proceso->hechos ?? '', $empresaId, limite: 3);

        return [
            'hechos'              => $proceso->hechos,
            'articulos_legales'   => $articulosLegales,
            'preguntas_respuestas'=> $preguntasYRespuestas,
            'todas_las_preguntas' => $todasLasPreguntas,
            'trabajador'          => $proceso->trabajador->nombre_completo,
            'cargo'               => $proceso->trabajador->cargo,
            'rit_contexto'        => $ritContexto,
            'normas_rag'          => $normasRag,
        ];
    }

    /**
     * Construye el prompt para la generación de preguntas
     */
    protected function construirPromptGeneracionPreguntas(
        array $contexto,
        PreguntaDescargo $preguntaRespondida,
        RespuestaDescargo $respuesta
    ): string {
        $articulosText = empty($contexto['articulos_legales'])
            ? 'No especificados'
            : implode("\n- ", $contexto['articulos_legales']);

        $preguntasRespuestasText = '';
        foreach ($contexto['preguntas_respuestas'] as $pr) {
            $tipo = $pr['es_ia'] ? '[IA]' : '[Inicial]';
            $preguntasRespuestasText .= "\n{$tipo} P: {$pr['pregunta']}\n   R: {$pr['respuesta']}\n";
        }

        $ritBloque   = !empty($contexto['rit_contexto'])
            ? "\nREGLAMENTO INTERNO DE LA EMPRESA (extracto relevante):\n{$contexto['rit_contexto']}\n"
            : '';
        $normasBloque = !empty($contexto['normas_rag'])
            ? "\nNORMAS LEGALES RECUPERADAS (RIT, CST, jurisprudencia — cita solo estas):\n{$contexto['normas_rag']}\n"
            : '';

        // Lista completa de todas las preguntas del formulario (para anti-repetición)
        $todasText = '';
        foreach ($contexto['todas_las_preguntas'] as $i => $p) {
            $todasText .= ($i + 1) . '. ' . $p . "\n";
        }

        return <<<PROMPT
Eres un abogado especialista en derecho laboral colombiano, con enfoque estrictamente garantista conforme al Art. 29 de la Constitución Política y al Art. 115 del CST (modificado por Ley 2466 de 2025).

════════════════════════════════════════════════════════
MARCO JURÍDICO — PRINCIPIOS IRRENUNCIABLES
════════════════════════════════════════════════════════
• Presunción de inocencia — los hechos son PRESUNTOS; el trabajador no ha sido hallado culpable.
• Derecho a la defensa y a la contradicción (Art. 29 C.P.) — la diligencia existe para que él/ella explique su versión, no para acusarlo.
• Dignidad humana — ninguna pregunta puede intimidar, coaccionar ni humillar.
• Imparcialidad — no se asume culpabilidad; solo se recoge información objetiva.
• In dubio pro disciplinado — ante duda, se favorece al trabajador.
Fundamento: Sentencia T-239/2021, SL1861-2024, C-1270/2000 (Corte Constitucional y Corte Suprema).

════════════════════════════════════════════════════════
CONTEXTO DEL PROCESO
════════════════════════════════════════════════════════
Trabajador: {$contexto['trabajador']}
Cargo: {$contexto['cargo']}

Hechos presuntos (versión del empleador — aún no probados):
{$contexto['hechos']}

Artículos presuntamente incumplidos:
- {$articulosText}
{$ritBloque}{$normasBloque}
Preguntas ya respondidas (orden cronológico):
{$preguntasRespuestasText}

ÚLTIMA PREGUNTA RESPONDIDA:
{$preguntaRespondida->pregunta}

RESPUESTA DEL TRABAJADOR:
{$respuesta->respuesta}

TODAS LAS PREGUNTAS DEL FORMULARIO (para evitar repetición):
{$todasText}

════════════════════════════════════════════════════════
PREGUNTAS ABSOLUTAMENTE PROHIBIDAS
════════════════════════════════════════════════════════
1. SUGESTIVAS O CAPCIOSAS — inducen la respuesta o confunden al trabajador.
   ✗ "¿Verdad que actuó de forma negligente?" → ✓ "¿Qué ocurrió desde su punto de vista?"

2. ACUSATORIAS O PREJUZGADORAS — dan por hecho la culpa antes de que se defienda.
   ✗ "¿Por qué cometió esa falta?" → ✓ "¿Qué nos puede contar sobre lo que pasó?"

3. IMPERTINENTES — sin relación directa con los hechos que motivaron la citación.

4. SOBRE VIDA PRIVADA — aspectos personales sin incidencia en el hecho investigado.

5. QUE VIOLEN LA DIGNIDAD — buscan intimidar, presionar o humillar al trabajador.

6. SOBRE AUTOEVALUACIÓN O CUMPLIMIENTO DE FUNCIONES.
   ✗ "¿Usted sigue las instrucciones de su jefe?" / "¿Cumple con sus deberes?"
   Razón: nadie admite incumplimientos; no tienen valor probatorio.

════════════════════════════════════════════════════════
CUÁNDO GENERAR UNA PREGUNTA ADICIONAL
════════════════════════════════════════════════════════
Solo genera UNA pregunta si se cumplen SIMULTÁNEAMENTE las tres condiciones:
1. La respuesta del trabajador abre un aspecto relevante de su defensa que aún no ha podido explicar.
2. Esa aclaración puede beneficiar al trabajador o es necesaria para un expediente completo y justo.
3. No existe ya en la lista completa una pregunta que cubra ese mismo punto.

NUNCA preguntes si:
• La respuesta ya es completa o coherente (aunque sea desfavorable al trabajador).
• Ya existe una pregunta en la lista que cubre ese tema (aunque no haya sido respondida aún).
• La pregunta busca confirmar culpabilidad en vez de dar espacio para la defensa.
• La respuesta es sobre datos básicos (cargo, empresa, jefe, acompañante).

EN CASO DE DUDA → responde NO_REQUIERE.
Es mejor no preguntar que vulnerar el debido proceso.

════════════════════════════════════════════════════════
FORMATO
════════════════════════════════════════════════════════
• Lenguaje SENCILLO — sin términos jurídicos.
• Pregunta BREVE, ABIERTA y NEUTRA — máximo 2 líneas.
• NUNCA reformules una pregunta ya existente en la lista.

Si hay una pregunta válida:
PREGUNTA_1: [texto]

Si no se requiere:
NO_REQUIERE
PROMPT;
    }

    /**
     * Llama a la API de IA según el proveedor configurado.
     * Reintenta automáticamente hasta 2 veces en errores transitorios (503, 429).
     */
    protected function llamarIA(string $prompt): string
    {
        $maxReintentos = 2;
        $ultimoError   = null;

        for ($intento = 0; $intento <= $maxReintentos; $intento++) {
            try {
                if ($intento > 0) {
                    Log::warning("Reintentando llamada a IA (intento {$intento}/{$maxReintentos})", [
                        'provider'     => $this->provider,
                        'error_previo' => substr($ultimoError->getMessage(), 0, 200),
                    ]);
                    sleep($intento); // 1s en el 2.º intento, 2s en el 3.º
                }

                if ($this->provider === 'openai')    return $this->llamarOpenAI($prompt);
                if ($this->provider === 'anthropic') return $this->llamarAnthropic($prompt);
                if ($this->provider === 'gemini')    return $this->llamarGemini($prompt);

                throw new \Exception("Proveedor de IA no soportado: {$this->provider}");
            } catch (\Exception $e) {
                $ultimoError = $e;
                $msj = $e->getMessage();

                // Solo reintentar para errores transitorios de servidor
                $esTransitorio = str_contains($msj, '503')
                    || str_contains($msj, '429')
                    || str_contains($msj, 'UNAVAILABLE')
                    || str_contains($msj, 'overloaded');

                if (!$esTransitorio) {
                    throw $e; // Error permanente — no tiene sentido reintentar
                }
            }
        }

        throw $ultimoError;
    }

    /**
     * Llama a la API de OpenAI
     */
    protected function llamarOpenAI(string $prompt): string
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->config['api_key'],
            'Content-Type' => 'application/json',
        ])->timeout(30)->post('https://api.openai.com/v1/chat/completions', [
            'model' => $this->config['model'],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Eres un abogado laboral experto en procesos disciplinarios en Colombia. Respondes de forma concisa y profesional pero entendible para cualquier persona.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => $this->config['max_tokens'],
            'temperature' => 0.7,
        ]);

        if (!$response->successful()) {
            throw new \Exception("Error en API OpenAI: " . $response->body());
        }

        return $response->json('choices.0.message.content');
    }

    /**
     * Llama a la API de Anthropic (Claude)
     */
    protected function llamarAnthropic(string $prompt): string
    {
        $response = Http::withHeaders([
            'x-api-key' => $this->config['api_key'],
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json',
        ])->timeout(30)->post('https://api.anthropic.com/v1/messages', [
            'model' => $this->config['model'],
            'max_tokens' => $this->config['max_tokens'],
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
        ]);

        if (!$response->successful()) {
            throw new \Exception("Error en API Anthropic: " . $response->body());
        }

        return $response->json('content.0.text');
    }

    /**
     * Llama a la API de Google Gemini.
     * Para generación de preguntas (tarea simple) prefiere modelos rápidos.
     * Si el modelo principal devuelve 503, hace fallback automático.
     */
    protected function llamarGemini(string $prompt): string
    {
        $apiKey = $this->config['api_key'];

        $modeloPrincipal = $this->config['model'] ?? 'gemini-1.5-flash';
        // Orden: los más estables primero. 1.5-flash y 2.0-flash son los más confiables
        // en producción. Los modelos 2.5 son experimentales y se saturan con frecuencia.
        $modelos = array_unique(array_filter([
            'gemini-1.5-flash',
            'gemini-2.0-flash',
            $modeloPrincipal,
        ]));

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => "Eres un abogado laboral experto en procesos disciplinarios en Colombia. Respondes de forma concisa y profesional pero entendible para cualquier persona.\n\n" . $prompt
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => $this->config['max_tokens'],
                'topP' => 0.95,
            ],
        ];

        $response = null;

        foreach ($modelos as $modelo) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelo}:generateContent?key={$apiKey}";

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->timeout(20)->post($url, $payload);

            // En 503/404 pasar al siguiente modelo
            if (in_array($response->status(), [503, 404])) {
                Log::warning("IADescargoService: Gemini {$response->status()} en {$modelo}, intentando siguiente modelo");
                continue;
            }

            break;
        }

        if (!$response->successful()) {
            throw new \Exception("Error en API Gemini: " . $response->body());
        }

        $responseData = $response->json();

        if (!isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
            throw new \Exception("Respuesta de Gemini sin contenido válido");
        }

        $finishReason = $responseData['candidates'][0]['finishReason'] ?? 'UNKNOWN';
        if ($finishReason === 'MAX_TOKENS') {
            Log::warning('IADescargoService: respuesta Gemini truncada por límite de tokens', [
                'max_tokens' => $this->config['max_tokens'],
            ]);
        }

        return $responseData['candidates'][0]['content']['parts'][0]['text'];
    }

    /**
     * Parsea la respuesta de la IA y extrae las preguntas
     */
    protected function parsearRespuestaIA(string $respuestaIA, ?int $limite = null): array
    {
        $respuestaIA = trim($respuestaIA);

        if (str_contains($respuestaIA, 'NO_REQUIERE') || str_contains($respuestaIA, 'NO REQUIERE')) {
            return [];
        }

        $preguntas = [];

        preg_match_all('/PREGUNTA_\d+:\s*(.+?)(?=PREGUNTA_\d+:|$)/s', $respuestaIA, $matches);

        if (!empty($matches[1])) {
            foreach ($matches[1] as $pregunta) {
                $preguntaLimpia = trim($pregunta);

                // Validar que la pregunta no esté vacía
                if (empty($preguntaLimpia)) {
                    continue;
                }

                // Si no termina con ?, agregar el signo de interrogación
                // if (!str_ends_with($preguntaLimpia, '?')) {
                //     $preguntaLimpia .= '?';
                //     Log::info('Pregunta de IA corregida (agregado signo ?)', [
                //         'pregunta_original' => trim($pregunta),
                //         'pregunta_corregida' => $preguntaLimpia,
                //     ]);
                // }

                // Validar longitud mínima (al menos 20 caracteres)
                if (strlen($preguntaLimpia) < 20) {
                    Log::warning('Pregunta de IA descartada por ser demasiado corta', [
                        'pregunta' => $preguntaLimpia,
                        'longitud' => strlen($preguntaLimpia),
                    ]);
                    continue;
                }

                $preguntas[] = $preguntaLimpia;
            }
        }

        return $limite !== null ? array_slice($preguntas, 0, $limite) : $preguntas;
    }

    /**
     * Guarda las nuevas preguntas generadas por la IA ANTES de las preguntas de cierre
     */
    protected function guardarNuevasPreguntas(
        DiligenciaDescargo $diligencia,
        array $preguntas,
        int $preguntaPadreId
    ): array {
        $preguntasGuardadas = [];

        // Obtener las preguntas de cierre (las últimas 3 preguntas estándar)
        $preguntasCierre = $diligencia->preguntas()
            ->whereIn('pregunta', self::PREGUNTAS_CIERRE)
            ->orderBy('orden')
            ->get();

        if ($preguntasCierre->isNotEmpty()) {
            // Insertar ANTES de las preguntas de cierre
            $ordenInsercion = $preguntasCierre->first()->orden;

            // Incrementar el orden de las preguntas de cierre para hacer espacio
            foreach ($preguntasCierre as $index => $preguntaCierre) {
                $preguntaCierre->update([
                    'orden' => $ordenInsercion + count($preguntas) + $index
                ]);
            }

            // Insertar las nuevas preguntas en el espacio liberado
            foreach ($preguntas as $index => $preguntaTexto) {
                $pregunta = PreguntaDescargo::create([
                    'diligencia_descargo_id' => $diligencia->id,
                    'pregunta' => $preguntaTexto,
                    'orden' => $ordenInsercion + $index,
                    'es_generada_por_ia' => true,
                    'pregunta_padre_id' => $preguntaPadreId,
                    'estado' => 'activa',
                ]);

                $preguntasGuardadas[] = $pregunta;
            }
        } else {
            // Si no hay preguntas de cierre, usar el orden máximo
            $ultimoOrden = $diligencia->preguntas()->max('orden') ?? 0;

            foreach ($preguntas as $index => $preguntaTexto) {
                $pregunta = PreguntaDescargo::create([
                    'diligencia_descargo_id' => $diligencia->id,
                    'pregunta' => $preguntaTexto,
                    'orden' => $ultimoOrden + $index + 1,
                    'es_generada_por_ia' => true,
                    'pregunta_padre_id' => $preguntaPadreId,
                    'estado' => 'activa',
                ]);

                $preguntasGuardadas[] = $pregunta;
            }
        }

        return $preguntasGuardadas;
    }

    /**
     * Registra la trazabilidad de la llamada a la IA
     */
    protected function registrarTrazabilidad(
        int $diligenciaId,
        string $prompt,
        string $respuesta,
        string $tipo
    ): void {
        TrazabilidadIADescargo::create([
            'diligencia_descargo_id' => $diligenciaId,
            'prompt_enviado' => $prompt,
            'respuesta_recibida' => $respuesta,
            'tipo' => $tipo,
            'metadata' => [
                'provider' => $this->provider,
                'model' => $this->config['model'],
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Genera todas las preguntas del proceso (estándar + IA + cierre)
     */
    public function generarPreguntasCompletas(DiligenciaDescargo $diligencia, int $cantidadPreguntasIA = 1): array
    {
        $preguntasCreadas = [];

        // Calcular límite de preguntas IA para no exceder el máximo
        $cantidadPreguntasIniciales = count(self::PREGUNTAS_INICIALES);
        $cantidadPreguntasCierre = count(self::PREGUNTAS_CIERRE);

        // Ajustar cantidad de preguntas IA si excede el límite
        $maximoPreguntasIA = self::LIMITE_MAXIMO_PREGUNTAS - $cantidadPreguntasIniciales - $cantidadPreguntasCierre;
        $cantidadPreguntasIA = min($cantidadPreguntasIA, $maximoPreguntasIA);

        Log::info('Generando preguntas completas', [
            'diligencia_id' => $diligencia->id,
            'preguntas_iniciales' => $cantidadPreguntasIniciales,
            'preguntas_ia_solicitadas' => $cantidadPreguntasIA,
            'preguntas_cierre' => $cantidadPreguntasCierre,
            'total_estimado' => $cantidadPreguntasIniciales + $cantidadPreguntasIA + $cantidadPreguntasCierre,
            'limite_maximo' => self::LIMITE_MAXIMO_PREGUNTAS,
        ]);

        // 1. Crear preguntas estándar iniciales
        $preguntasCreadas = array_merge(
            $preguntasCreadas,
            $this->crearPreguntasEstandar($diligencia, self::PREGUNTAS_INICIALES, 1, 'inicial')
        );

        // 2. Generar preguntas específicas con IA (solo si no excede el límite)
        if ($cantidadPreguntasIA > 0) {
            $preguntasIA = $this->generarPreguntasIA($diligencia, $cantidadPreguntasIA);
            $preguntasCreadas = array_merge($preguntasCreadas, $preguntasIA);
        }

        // 3. Crear preguntas de cierre
        $ordenInicio = count($preguntasCreadas) + 1;
        $preguntasCreadas = array_merge(
            $preguntasCreadas,
            $this->crearPreguntasEstandar($diligencia, self::PREGUNTAS_CIERRE, $ordenInicio, 'cierre')
        );

        return $preguntasCreadas;
    }

    /**
     * Crea preguntas estándar (no generadas por IA)
     */
    protected function crearPreguntasEstandar(
        DiligenciaDescargo $diligencia,
        array $preguntas,
        int $ordenInicio,
        string $tipo
    ): array {
        $preguntasGuardadas = [];

        $empresaNombre = $diligencia->proceso?->empresa?->razon_social ?? 'la empresa que lo cita';

        foreach ($preguntas as $index => $preguntaTexto) {
            $preguntaPadreId = null;
            if ($tipo === 'inicial' && isset(self::DEPENDENCIAS_INICIALES[$index])) {
                $padreIndex = self::DEPENDENCIAS_INICIALES[$index];
                $preguntaPadreId = $preguntasGuardadas[$padreIndex]->id ?? null;
            }

            $preguntaTexto = str_replace('{empresa}', $empresaNombre, $preguntaTexto);

            $pregunta = PreguntaDescargo::create([
                'diligencia_descargo_id' => $diligencia->id,
                'pregunta' => $preguntaTexto,
                'orden' => $ordenInicio + $index,
                'es_generada_por_ia' => false,
                'pregunta_padre_id' => $preguntaPadreId,
                'estado' => 'activa',
            ]);

            $preguntasGuardadas[] = $pregunta;
        }

        return $preguntasGuardadas;
    }

    /**
     * Genera preguntas específicas con IA basadas en los hechos del proceso
     */
    public function generarPreguntasIA(DiligenciaDescargo $diligencia, int $cantidadPreguntas = 2): array
    {
        $proceso   = $diligencia->proceso;
        $empresaId = $proceso->empresa_id ?? $proceso->trabajador?->empresa_id ?? null;

        // Artículos legales con texto completo
        $articulosLegales = [];
        if (!empty($proceso->articulos_legales_ids)) {
            $articulosLegales = ArticuloLegal::whereIn('id', $proceso->articulos_legales_ids)
                ->get()
                ->map(function ($art) {
                    $texto    = $art->getRawOriginal('texto_completo') ?? $art->descripcion ?? '';
                    $extracto = $texto ? "\n   Texto: " . mb_substr($texto, 0, 500) : '';
                    return "{$art->codigo}: {$art->titulo}{$extracto}";
                })
                ->toArray();
        }

        $articulosText = empty($articulosLegales)
            ? 'No especificados'
            : implode("\n\n- ", $articulosLegales);

        // Contexto del RIT de la empresa
        $ritContexto = $empresaId ? $this->obtenerContextoRIT($empresaId) : '';
        $ritBloque   = $ritContexto
            ? "\nREGLAMENTO INTERNO DE LA EMPRESA (extracto relevante para estos hechos):\n{$ritContexto}\n"
            : "\nNOTA: Esta empresa no tiene RIT cargado. Aplica el Código Sustantivo del Trabajo.\n";

        // Normas relevantes por RAG (RIT + CST + jurisprudencia)
        $normasRag   = $this->buscarNormasRelevantes($proceso->hechos ?? '', $empresaId, limite: 3);
        $normasBloque = $normasRag
            ? "\nNORMAS Y JURISPRUDENCIA RELEVANTES (recuperadas de la base de datos):\n{$normasRag}\n"
            : '';

        $prompt = <<<PROMPT
Eres un abogado laboral experto en procesos disciplinarios colombianos, con enfoque estrictamente garantista del debido proceso conforme al Art. 29 de la Constitución Política y al Art. 115 del Código Sustantivo del Trabajo (modificado por la Ley 2466 de 2025).

════════════════════════════════════════════════════════
MARCO JURÍDICO OBLIGATORIO
════════════════════════════════════════════════════════
Principios que rigen esta diligencia (Art. 115 CST + jurisprudencia constitucional):
• Presunción de inocencia — el trabajador NO ha sido hallado culpable de nada.
• Derecho a la defensa y a la contradicción — la diligencia es para que él/ella explique su versión.
• Dignidad humana — ninguna pregunta puede humillar, intimidar ni coaccionar.
• Imparcialidad — no se asume culpabilidad; se recoge información de forma objetiva.
• In dubio pro disciplinado — ante duda, se favorece al trabajador.
• Proporcionalidad — las preguntas deben ser pertinentes y directamente relacionadas con los hechos.

Fundamento jurídico: Sentencia T-239/2021 (Corte Constitucional), SL1861-2024 (Corte Suprema), C-1270/2000.

════════════════════════════════════════════════════════
OBJETIVO DE LA DILIGENCIA — NO ES UN INTERROGATORIO
════════════════════════════════════════════════════════
La diligencia de descargos es el espacio para que el TRABAJADOR ejerza su derecho de defensa.
Su finalidad es:
✓ Escuchar la versión del trabajador de forma objetiva.
✓ Verificar qué ocurrió realmente.
✓ Identificar si hubo justificación, autorización, fuerza mayor u otro eximente.
✓ Dar al trabajador la oportunidad de presentar pruebas, testigos o documentos a su favor.

NO se trata de acusar, confirmar culpabilidad ni presionar al trabajador para que admita hechos.

════════════════════════════════════════════════════════
CONTEXTO DEL PROCESO
════════════════════════════════════════════════════════
Trabajador: {$proceso->trabajador->nombre_completo}
Cargo: {$proceso->trabajador->cargo}

Hechos presuntos (versión del empleador — aún no probados):
{$proceso->hechos}

Artículos presuntamente incumplidos:
- {$articulosText}
{$ritBloque}{$normasBloque}
════════════════════════════════════════════════════════
PREGUNTAS ABSOLUTAMENTE PROHIBIDAS
════════════════════════════════════════════════════════
Nunca generes ninguna pregunta de los siguientes tipos:

1. SUGESTIVAS O CAPCIOSAS — inducen la respuesta o confunden al trabajador para que admita una falta.
   ✗ NUNCA: "¿Verdad que usted actuó de forma negligente?"
   ✗ NUNCA: "¿Reconoce que no cumplió con su deber?"
   ✓ CORRECTO: "¿Qué sucedió ese día desde su punto de vista?"

2. ACUSATORIAS O PREJUZGADORAS — dan por hecho la culpabilidad antes de que el trabajador se defienda.
   ✗ NUNCA: "¿Por qué cometió esa falta?"
   ✗ NUNCA: "¿Sabía usted que lo que hizo estaba prohibido y lo hizo de todas formas?"
   ✓ CORRECTO: "¿Qué puede contarnos sobre lo que ocurrió?"

3. IMPERTINENTES O IRRELEVANTES — sin relación directa con los hechos que motivaron la citación.
   ✗ NUNCA: Preguntas sobre otras situaciones pasadas no relacionadas con el hecho actual.

4. SOBRE VIDA PRIVADA — aspectos personales sin incidencia en el desempeño laboral o la falta investigada.
   ✗ NUNCA: Preguntas sobre situación familiar, creencias, vida fuera del trabajo.

5. QUE VIOLEN LA DIGNIDAD O EL DEBIDO PROCESO — buscan intimidar, coaccionar o humillar.
   ✗ NUNCA: Preguntas que presionen, amenacen o pongan al trabajador en situación de inferioridad.

6. SOBRE AUTOEVALUACIÓN DE DESEMPEÑO O CUMPLIMIENTO DE FUNCIONES.
   ✗ NUNCA: "¿Usted cumple con sus funciones?" / "¿Sigue las instrucciones de su jefe?"
   Razón: nadie admite incumplimientos voluntariamente; no tienen valor probatorio.

════════════════════════════════════════════════════════
INSTRUCCIONES PARA GENERAR LAS PREGUNTAS
════════════════════════════════════════════════════════
Genera {$cantidadPreguntas} preguntas abiertas, neutrales y breves que:
• Permitan al trabajador explicar su versión de los hechos con sus propias palabras.
• Indaguen sobre circunstancias atenuantes, justificaciones o contexto que pueda alegar.
• Exploren si hubo autorización, aviso previo, fuerza mayor u otro eximente válido.
• Den espacio para que presente pruebas, testigos o documentos a su favor.
• Sean directamente pertinentes a los hechos presuntos descritos arriba.

LENGUAJE SENCILLO — sin tecnicismos jurídicos:
✗ "¿Tenía conocimiento de las disposiciones del reglamento?" → ✓ "¿Conocía esa regla de la empresa?"
✗ "¿Cuál fue el móvil de su actuación?" → ✓ "¿Por qué pasó eso?"
✗ "¿Informó oportunamente a su superior jerárquico?" → ✓ "¿Le avisó a su jefe?"

BREVEDAD: máximo 2 oraciones por pregunta. Si aplica al RIT o a una norma, menciónala brevemente.

════════════════════════════════════════════════════════
FORMATO DE RESPUESTA (obligatorio)
════════════════════════════════════════════════════════
PREGUNTA_1: [texto]
PREGUNTA_2: [texto]
...
PREGUNTA_{$cantidadPreguntas}: [texto]
PROMPT;

        try {
            $respuestaIA = $this->llamarIA($prompt);

            $this->registrarTrazabilidad(
                $diligencia->id,
                $prompt,
                $respuestaIA,
                'generacion_preguntas'
            );

            // No limitar las preguntas iniciales para obtener todas las generadas
            $preguntas = $this->parsearRespuestaIA($respuestaIA, $cantidadPreguntas);

            // Insertar las preguntas IA ANTES de las preguntas de cierre.
            // Las últimas N preguntas estándar (es_generada_por_ia = false) son el cierre.
            $cantidadCierre = count(self::PREGUNTAS_CIERRE);

            $preguntasCierre = $diligencia->preguntas()
                ->where('es_generada_por_ia', false)
                ->orderBy('orden', 'desc')
                ->limit($cantidadCierre)
                ->get();

            if ($preguntasCierre->count() === $cantidadCierre) {
                // Hay preguntas de cierre: empujarlas hacia abajo y colocar IA antes de ellas
                $ordenInsercion = $preguntasCierre->min('orden');

                PreguntaDescargo::where('diligencia_descargo_id', $diligencia->id)
                    ->where('orden', '>=', $ordenInsercion)
                    ->increment('orden', count($preguntas));

                $ordenInicial = $ordenInsercion;
            } else {
                // Sin preguntas de cierre: añadir al final
                $ordenInicial = ($diligencia->preguntas()->max('orden') ?? 0) + 1;
            }

            return $this->guardarPreguntasIA($diligencia, $preguntas, $ordenInicial);
        } catch (\Exception $e) {
            Log::error('Error al generar preguntas iniciales con IA', [
                'diligencia_id' => $diligencia->id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Guarda las preguntas generadas por IA
     */
    protected function guardarPreguntasIA(DiligenciaDescargo $diligencia, array $preguntas, int $ordenInicial = 1): array
    {
        $preguntasGuardadas = [];

        foreach ($preguntas as $index => $preguntaTexto) {
            $pregunta = PreguntaDescargo::create([
                'diligencia_descargo_id' => $diligencia->id,
                'pregunta' => $preguntaTexto,
                'orden' => $ordenInicial + $index,
                'es_generada_por_ia' => true,
                'pregunta_padre_id' => null,
                'estado' => 'activa',
            ]);

            $preguntasGuardadas[] = $pregunta;
        }

        return $preguntasGuardadas;
    }

    // ── RAG — RIT y jurisprudencia ────────────────────────────────────────────

    /**
     * Recupera un extracto relevante del RIT de la empresa para inyectar en el prompt.
     */
    private function obtenerContextoRIT(int $empresaId): string
    {
        try {
            $texto = app(ReglamentoInternoService::class)->getTextoReglamento($empresaId);
            if ($texto) {
                return mb_substr($texto, 0, 8000);
            }
        } catch (\Exception $e) {
            Log::warning('IADescargoService::obtenerContextoRIT error', ['error' => $e->getMessage()]);
        }
        return '';
    }

    /**
     * Recupera las normas más relevantes (RIT empresa + CST + jurisprudencia + RITs de referencia)
     * usando similitud coseno sobre embeddings Gemini.
     *
     * @return string Bloque de texto listo para inyectar en el prompt. Vacío si no hay embeddings.
     */
    private function buscarNormasRelevantes(string $texto, ?int $empresaId = null, int $limite = 3): string
    {
        if (empty(trim($texto))) {
            return '';
        }

        try {
            $queryEmbedding = $this->obtenerEmbeddingTexto($texto);
            if (!$queryEmbedding) {
                return '';
            }

            // Buscar en: artículos de la empresa + universales (CST, jurisprudencia, RIT referencia)
            $articulos = ArticuloLegal::whereNotNull('embedding')
                ->activos()
                ->paraEmpresa($empresaId)
                ->get();

            if ($articulos->isEmpty()) {
                return '';
            }

            $scored = [];
            foreach ($articulos as $articulo) {
                $emb = $articulo->embedding;
                if (!is_array($emb) || empty($emb)) {
                    continue;
                }
                $scored[] = [
                    'articulo' => $articulo,
                    'score'    => $this->cosineSimilarity($queryEmbedding, $emb),
                ];
            }

            if (empty($scored)) {
                return '';
            }

            usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

            $top = array_filter(
                array_slice($scored, 0, $limite),
                fn($s) => $s['score'] >= 0.50
            );

            if (empty($top)) {
                return '';
            }

            $lineas = [];
            foreach ($top as $item) {
                $art      = $item['articulo'];
                $textoArt = $art->getRawOriginal('texto_completo') ?? $art->descripcion ?? '';
                $fuente   = $art->fuente ? " — {$art->fuente}" : '';
                $lineas[] = "[{$art->codigo}{$fuente}] {$art->titulo}";
                if ($textoArt) {
                    $lineas[] = mb_substr($textoArt, 0, 600);
                }
                $lineas[] = '';
            }

            $resultado = trim(implode("\n", $lineas));

            // Enriquecer con la Biblioteca Legal (sentencias, doctrina, CST en PDF)
            try {
                $fragmentosBiblioteca = app(BibliotecaLegalService::class)
                    ->buscarFragmentos($texto, limite: 4, umbral: 0.60);
                if (!empty($fragmentosBiblioteca)) {
                    $resultado = $resultado
                        ? $resultado . "\n\n" . $fragmentosBiblioteca
                        : $fragmentosBiblioteca;
                }
            } catch (\Throwable $e) {
                Log::warning('IADescargoService: biblioteca RAG error', ['error' => $e->getMessage()]);
            }

            return $resultado;
        } catch (\Exception $e) {
            Log::warning('IADescargoService::buscarNormasRelevantes', ['error' => $e->getMessage()]);
            return '';
        }
    }

    /**
     * Genera el embedding vectorial de un texto (RETRIEVAL_QUERY) usando Gemini.
     */
    private function obtenerEmbeddingTexto(string $texto): ?array
    {
        $apiKey = config('services.ia.gemini.api_key')
            ?? config('services.gemini.api_key')
            ?? ($this->provider === 'gemini' ? ($this->config['api_key'] ?? null) : null);

        if (!$apiKey) {
            return null;
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-001:embedContent?key={$apiKey}";

        try {
            $response = Http::timeout(10)->post($url, [
                'content'  => ['parts' => [['text' => mb_substr($texto, 0, 8000)]]],
                'taskType' => 'RETRIEVAL_QUERY',
            ]);

            if (!$response->successful()) {
                return null;
            }

            $values = $response->json('embedding.values');
            return is_array($values) && !empty($values) ? $values : null;
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Calcula la similitud coseno entre dos vectores de la misma dimensión.
     */
    private function cosineSimilarity(array $a, array $b): float
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

        $denom = sqrt($magA) * sqrt($magB);
        return $denom > 0.0 ? (float) ($dot / $denom) : 0.0;
    }
}
