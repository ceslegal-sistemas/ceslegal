<?php

namespace App\Services;

use App\Models\DiligenciaDescargo;
use App\Models\PreguntaDescargo;
use App\Models\RespuestaDescargo;
use App\Models\TrazabilidadIADescargo;
use App\Models\ArticuloLegal;
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
        '¿Va a asistir acompañado(a) por alguien?',
        '¿Qué relación tiene esa persona con usted?',
        '¿Para qué empresa trabaja usted?',
        '¿Cuál es su cargo en la empresa?',
        '¿Qué tareas realiza en ese cargo?',
        '¿Conoce el reglamento interno de la empresa?',
        '¿Quién es su jefe directo?',
        '¿Usted cumple con las funciones de su cargo?',
        '¿Sigue las instrucciones que le da su jefe?',
        '¿Sabe por qué fue citado(a) a estos descargos?',
    ];

    // Preguntas estándar de cierre
    const PREGUNTAS_CIERRE = [
        '¿Le avisó esta situación a su jefe directo?',
        '¿Ha estado antes en descargos?',
        '¿Sabe que no cumplir con sus obligaciones de trabajo puede traerle sanciones?',
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
        $proceso = $diligencia->proceso;

        $articulosLegales = [];
        if (!empty($proceso->articulos_legales_ids)) {
            $articulosLegales = ArticuloLegal::whereIn('id', $proceso->articulos_legales_ids)
                ->get()
                ->map(fn($art) => "{$art->codigo}: {$art->titulo}")
                ->toArray();
        }

        // Incluir TODAS las preguntas activas (respondidas y pendientes)
        // para que la IA no repita preguntas que ya están en cola sin responder
        $preguntasYRespuestas = $diligencia->preguntas()
            ->with('respuesta')
            ->activas()
            ->get()
            ->map(function ($pregunta) {
                $respuesta = $pregunta->respuesta?->respuesta ?? '[PENDIENTE — aún no respondida]';
                return [
                    'pregunta' => $pregunta->pregunta,
                    'respuesta' => $respuesta,
                    'es_ia'     => $pregunta->es_generada_por_ia,
                ];
            })
            ->toArray();

        return [
            'hechos'              => $proceso->hechos,
            'articulos_legales'   => $articulosLegales,
            'preguntas_respuestas'=> $preguntasYRespuestas,
            'trabajador'          => $proceso->trabajador->nombre_completo,
            'cargo'               => $proceso->trabajador->cargo,
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

        return <<<PROMPT
Eres un abogado especialista en derecho laboral con enfasis y experiencia en procesos disciplinarios y descargos en Colombia.

CONTEXTO DEL PROCESO:

Trabajador: {$contexto['trabajador']}
Cargo: {$contexto['cargo']}

Hechos del proceso:
{$contexto['hechos']}

Artículos legales presuntamente incumplidos:
- {$articulosText}

Preguntas realizadas y respuestas del trabajador:
{$preguntasRespuestasText}

ÚLTIMA PREGUNTA RESPONDIDA:
{$preguntaRespondida->pregunta}

RESPUESTA DEL TRABAJADOR:
{$respuesta->respuesta}

INSTRUCCIONES:
Bajo el artículo 115 del Código Sustantivo del Trabajo, los descargos son el espacio para que el trabajador ejerza su derecho de defensa — NO un interrogatorio. Tu rol es garantizar el debido proceso, no acumular pruebas contra el trabajador.

Solo genera UNA pregunta adicional si se cumplen SIMULTÁNEAMENTE estas tres condiciones:
1. La respuesta del trabajador contradice directamente un hecho documentado en el proceso.
2. Esa contradicción es materialmente relevante para determinar la falta (no es un detalle menor).
3. No existe ya una pregunta pendiente o respondida que cubra ese mismo punto.

NUNCA generes una pregunta si:
• La respuesta es coherente con los hechos, aunque sea desfavorable para el trabajador.
• Ya se preguntó algo similar (ni con otras palabras).
• La pregunta marcada [PENDIENTE] cubre el mismo tema.
• Solo quieres confirmar o ampliar algo que el trabajador ya explicó.
• La respuesta es sobre datos básicos (cargo, empresa, jefe, acompañante).

En caso de duda, responde NO_REQUIERE. Es mejor no preguntar que molestar al trabajador con preguntas de relleno.

REGLAS DE FORMATO:
• Lenguaje SENCILLO — sin términos jurídicos.
• Pregunta BREVE y DIRECTA — máximo 2 líneas.
• NUNCA reformules una pregunta anterior.

FORMATO DE RESPUESTA:
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

        // Cadena de modelos: preferir flash-lite (más rápido y barato para generar preguntas),
        // luego el modelo configurado, y por último 1.5-flash como fallback estable.
        $modeloPrincipal = $this->config['model'] ?? 'gemini-2.5-flash';
        $modelos = array_unique(array_filter([
            'gemini-2.5-flash-lite',
            $modeloPrincipal,
            'gemini-1.5-flash',
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

            // En 503 pasar al siguiente modelo
            if ($response->status() === 503) {
                Log::warning("IADescargoService: Gemini 503 en {$modelo}, intentando siguiente modelo");
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

        foreach ($preguntas as $index => $preguntaTexto) {
            // Para preguntas iniciales, la pregunta 2 (índice 1) depende de la pregunta 1 (índice 0)
            // "¿Qué relación tiene esa persona con usted?" depende de "¿Va a asistir acompañado(a) por alguien?"
            $preguntaPadreId = null;
            if ($tipo === 'inicial' && $index === 1) {
                // La pregunta padre es la primera pregunta creada (índice 0)
                $preguntaPadreId = $preguntasGuardadas[0]->id ?? null;
            }

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
    protected function generarPreguntasIA(DiligenciaDescargo $diligencia, int $cantidadPreguntas = 2): array
    {
        $proceso = $diligencia->proceso;

        $articulosLegales = [];
        if (!empty($proceso->articulos_legales_ids)) {
            $articulosLegales = ArticuloLegal::whereIn('id', $proceso->articulos_legales_ids)
                ->get()
                ->map(fn($art) => "{$art->codigo}: {$art->titulo}")
                ->toArray();
        }

        $articulosText = empty($articulosLegales)
            ? 'No especificados'
            : implode("\n- ", $articulosLegales);

        $prompt = <<<PROMPT
Eres un abogado laboral experto en procesos disciplinarios en Colombia.

CONTEXTO DEL PROCESO:

Trabajador: {$proceso->trabajador->nombre_completo}
Cargo: {$proceso->trabajador->cargo}

Hechos del proceso:
{$proceso->hechos}

Artículos legales presuntamente incumplidos:
- {$articulosText}

INSTRUCCIONES:
Genera {$cantidadPreguntas} preguntas iniciales para que el trabajador presente sus descargos.

Las preguntas deben:
- MUY IMPORTANTE: Usa lenguaje SENCILLO y CLARO que cualquier trabajador pueda entender fácilmente. Evita términos jurídicos complejos o palabras rebuscadas.
- Ser breves y directas
- Ser específicas y neutrales
- Permitir al trabajador explicar su versión de los hechos con sus propias palabras
- Indagar sobre circunstancias, motivaciones y contexto
- Dirigidas a esclarecer el hecho que dio origen a la diligencia de descargos con una tendencia de juzgamiento a favor del empleador y en contra del trabajador.

EJEMPLOS DE LENGUAJE CLARO:
❌ "¿Tenía conocimiento de las disposiciones del reglamento?"
✅ "¿Conocía las reglas de la empresa?"

❌ "¿Cuál fue el móvil de su actuación?"
✅ "¿Por qué hizo eso?"

❌ "¿Informó oportunamente a su superior jerárquico?"
✅ "¿Le avisó a tiempo a su jefe?"

❌ "¿Efectuó debidamente sus labores?"
✅ "¿Hizo bien su trabajo?"

FORMATO DE RESPUESTA:
PREGUNTA_1: [texto]
PREGUNTA_2: [texto]
...
PREGUNTA_{$cantidadPreguntas}: [texto]

Si no se requieren preguntas, responde:
NO_REQUIERE
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

            // Calcular el orden inicial (después de las preguntas estándar)
            $ordenInicial = $diligencia->preguntas()->max('orden') ?? 0;
            $ordenInicial += 1;

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
}
