<?php

namespace App\Services;

use App\Models\DiligenciaDescargo;
use App\Models\PreguntaDescargo;
use App\Models\RespuestaDescargo;
use App\Models\TrazabilidadIADescargo;
use App\Models\ArticuloLegal;
use Illuminate\Support\Facades\Cache;
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

    /**
     * Banco de preguntas de respaldo.
     * Se usan ÚNICAMENTE cuando la IA falla definitivamente (job agota reintentos).
     * Son preguntas jurídicamente relevantes para cualquier proceso disciplinario.
     */
    const PREGUNTAS_FALLBACK = [
        '¿Tiene algún documento o prueba que respalde lo que acaba de decir?',
        '¿Alguien más estaba presente cuando ocurrió lo descrito en este proceso?',
        '¿Había pasado algo similar antes en su trabajo?',
        '¿Tomó alguna medida para solucionar o evitar la situación?',
        '¿Le comunicó a alguien de la empresa lo que estaba pasando?',
        '¿Considera que actuó correctamente? Explique por qué.',
        '¿Existe alguna circunstancia especial que explique su conducta?',
        '¿Cuál es su versión sobre lo que se le está imputando en este proceso?',
        '¿Cree que la empresa tenía conocimiento de la situación antes de citarle?',
        '¿Qué debería tenerse en cuenta para evaluar su caso?',
    ];

    // Circuit breaker: clave de caché y umbrales
    const CB_ERRORES_KEY  = 'ia_descargos_errores_transitorio';
    const CB_ACTIVO_KEY   = 'ia_descargos_circuit_breaker';
    const CB_UMBRAL       = 5;   // errores en la ventana para activar
    const CB_VENTANA_MIN  = 5;   // ventana de observación (minutos)

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

        $preguntasYRespuestas = $diligencia->preguntas()
            ->with('respuesta')
            ->respondidas()
            ->get()
            ->map(function ($pregunta) {
                $respuesta = $pregunta->respuesta?->respuesta ?? '(Sin respuesta)';
                return [
                    'pregunta' => $pregunta->pregunta,
                    'respuesta' => $respuesta,
                    'es_ia' => $pregunta->es_generada_por_ia,
                ];
            })
            ->toArray();

        return [
            'hechos' => $proceso->hechos,
            'articulos_legales' => $articulosLegales,
            'preguntas_respuestas' => $preguntasYRespuestas,
            'trabajador' => $proceso->trabajador->nombre_completo,
            'cargo' => $proceso->trabajador->cargo,
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
Analiza TODAS las respuestas del trabajador (no solo la última) teniendo en cuenta los hechos que indique en contraste con la conducta realizada que trasgrede las normas internas de la empresa y la ley aplicable en materia laboral.

REGLAS CRÍTICAS:
•⁠  ⁠NUNCA repitas una pregunta que ya se haya hecho anteriormente (revisa la lista de preguntas realizadas arriba).
•⁠  ⁠Genera nuevas preguntas si y solo si, existen inexactitudes, incongruencias, evasivas y/o contradicciones en los argumentos del trabajador.
•⁠  ⁠Máximo 1 pregunta.
•⁠  ⁠Las preguntas deben ser:
  * MUY IMPORTANTES: Usa lenguaje SENCILLO y CLARO que cualquier trabajador pueda entender fácilmente. Evita términos jurídicos complejos.
  * DIFERENTES a todas las preguntas anteriores (no reformules la misma pregunta con otras palabras).
  * Breves y directas.
  * Neutrales y no sugestivas.
  * Relevantes para el proceso disciplinario.
  * Dirigidas a esclarecer el hecho que dio origen a la diligencia de descargos con una tendencia de juzgamiento a favor del empleador y en contra del trabajador.
- Si la pregunta que ibas a generar ya fue hecha o es muy similar a una anterior, responde: NO_REQUIERE
- Si no se requieren más preguntas, responde exactamente: NO_REQUIERE

EJEMPLOS DE LENGUAJE CLARO:
❌ "¿Tuvo conocimiento de las directrices impartidas?"
✅ "¿Sabía qué debía hacer?"

❌ "¿Ejerció sus funciones cabalmente?"
✅ "¿Hizo bien su trabajo?"

❌ "¿Informó a su superior jerárquico?"
✅ "¿Le contó a su jefe?"

FORMATO DE RESPUESTA:
Si hay preguntas, responde en este formato:
PREGUNTA_1: [texto de la pregunta]
PREGUNTA_2: [texto de la pregunta]

Si no se requieren preguntas, responde:
NO_REQUIERE
PROMPT;
    }

    /**
     * Llama a la API de IA según el proveedor configurado.
     *
     * - Verifica el circuit breaker antes de hacer cualquier llamada.
     * - Reintenta hasta 2 veces (con pausa) en errores transitorios (503, 429).
     * - Registra errores transitorios para el circuit breaker.
     */
    protected function llamarIA(string $prompt): string
    {
        // Circuit breaker: si hay demasiados fallos recientes, lanzar excepción
        // inmediatamente sin llamar a la API (falla rápida para no saturar la cola).
        if ($this->circuitBreakerActivo()) {
            throw new \RuntimeException(
                'Circuit breaker activo: demasiados fallos recientes en la API de IA. ' .
                'El formulario usará preguntas de respaldo.'
            );
        }

        $maxReintentos = 2;
        $ultimoError   = null;

        for ($intento = 0; $intento <= $maxReintentos; $intento++) {
            try {
                if ($intento > 0) {
                    Log::warning("Reintentando llamada a IA (intento {$intento}/{$maxReintentos})", [
                        'provider'     => $this->provider,
                        'error_previo' => substr($ultimoError->getMessage(), 0, 200),
                    ]);
                    sleep($intento); // 1 s en el 2.º intento, 2 s en el 3.º
                }

                if ($this->provider === 'openai')    return $this->llamarOpenAI($prompt);
                if ($this->provider === 'anthropic') return $this->llamarAnthropic($prompt);
                if ($this->provider === 'gemini')    return $this->llamarGemini($prompt);

                throw new \Exception("Proveedor de IA no soportado: {$this->provider}");

            } catch (\Exception $e) {
                $ultimoError = $e;
                $msj = $e->getMessage();

                $esTransitorio = str_contains($msj, '503')
                    || str_contains($msj, '429')
                    || str_contains($msj, 'UNAVAILABLE')
                    || str_contains($msj, 'overloaded');

                if ($esTransitorio) {
                    $this->registrarErrorTransitorio();
                } else {
                    throw $e; // Error permanente — no reintentar
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
     * Llama a la API de Google Gemini
     */
    protected function llamarGemini(string $prompt): string
    {
        $apiKey = $this->config['api_key'];
        $model = $this->config['model'];

        // URL de la API de Gemini
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->timeout(30)->post($url, [
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
        ]);

        if (!$response->successful()) {
            throw new \Exception("Error en API Gemini: " . $response->body());
        }

        $responseData = $response->json();

        // Verificar si hay contenido en la respuesta
        if (!isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
            throw new \Exception("Respuesta de Gemini sin contenido válido");
        }

        // Verificar si la respuesta fue truncada por límite de tokens
        $finishReason = $responseData['candidates'][0]['finishReason'] ?? 'UNKNOWN';
        if ($finishReason === 'MAX_TOKENS') {
            Log::warning('Respuesta de Gemini truncada por límite de tokens', [
                'finish_reason' => $finishReason,
                'max_tokens' => $this->config['max_tokens'],
                'respuesta_parcial' => substr($responseData['candidates'][0]['content']['parts'][0]['text'], 0, 200),
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

    // =========================================================
    // CIRCUIT BREAKER
    // =========================================================

    /**
     * Registra un error transitorio de la API.
     * Si se supera el umbral activa el circuit breaker durante CB_VENTANA_MIN.
     */
    private function registrarErrorTransitorio(): void
    {
        $errores = Cache::get(self::CB_ERRORES_KEY, 0) + 1;
        Cache::put(self::CB_ERRORES_KEY, $errores, now()->addMinutes(self::CB_VENTANA_MIN));

        if ($errores >= self::CB_UMBRAL) {
            Cache::put(self::CB_ACTIVO_KEY, true, now()->addMinutes(self::CB_VENTANA_MIN));
            Log::warning('Circuit breaker IA activado: demasiados errores transitorios', [
                'provider'         => $this->provider,
                'errores_en_ventana' => $errores,
                'ventana_minutos'  => self::CB_VENTANA_MIN,
            ]);
        }
    }

    /**
     * Indica si el circuit breaker está activo.
     * Cuando está activo se salta la llamada a la API y se va directo al fallback.
     */
    public function circuitBreakerActivo(): bool
    {
        return Cache::get(self::CB_ACTIVO_KEY, false) === true;
    }

    // =========================================================
    // PREGUNTA FALLBACK
    // =========================================================

    /**
     * Genera una pregunta del banco de respaldo cuando la IA falla definitivamente.
     * Elige la primera del banco que aún no haya sido formulada en esta diligencia.
     */
    public function generarPreguntaFallback(DiligenciaDescargo $diligencia, int $preguntaPadreId): ?PreguntaDescargo
    {
        // Preguntas ya formuladas en esta diligencia
        $yaFormuladas = $diligencia->preguntas()
            ->pluck('pregunta')
            ->map(fn ($p) => mb_strtolower(trim($p)))
            ->toArray();

        $candidata = null;
        foreach (self::PREGUNTAS_FALLBACK as $texto) {
            if (!in_array(mb_strtolower(trim($texto)), $yaFormuladas, true)) {
                $candidata = $texto;
                break;
            }
        }

        if (!$candidata) {
            Log::warning('generarPreguntaFallback: todas las preguntas del banco ya fueron formuladas', [
                'diligencia_id' => $diligencia->id,
            ]);
            return null;
        }

        // Insertar ANTES de las preguntas de cierre (mismo mecanismo que guardarNuevasPreguntas)
        $preguntasCierre = $diligencia->preguntas()
            ->whereIn('pregunta', self::PREGUNTAS_CIERRE)
            ->orderBy('orden')
            ->get();

        if ($preguntasCierre->isNotEmpty()) {
            $ordenInsercion = $preguntasCierre->first()->orden;
            foreach ($preguntasCierre as $i => $pc) {
                $pc->update(['orden' => $ordenInsercion + 1 + $i]);
            }
            $orden = $ordenInsercion;
        } else {
            $orden = ($diligencia->preguntas()->max('orden') ?? 0) + 1;
        }

        $pregunta = PreguntaDescargo::create([
            'diligencia_descargo_id' => $diligencia->id,
            'pregunta'               => $candidata,
            'orden'                  => $orden,
            'es_generada_por_ia'     => false, // es fallback, no IA real
            'pregunta_padre_id'      => $preguntaPadreId,
            'estado'                 => 'activa',
        ]);

        Log::info('Pregunta fallback generada por fallo de IA', [
            'diligencia_id'   => $diligencia->id,
            'pregunta_padre'  => $preguntaPadreId,
            'pregunta_fallback' => $candidata,
        ]);

        return $pregunta;
    }
}
