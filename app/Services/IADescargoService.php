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

    // Preguntas estándar iniciales
    const PREGUNTAS_INICIALES = [
        '¿Desea hacerse acompañar de algún compañero de trabajo o persona de confianza? (Responda SÍ o NO)',
        'Indique el nombre completo de la persona que lo acompañará.',
        'Indique el cargo o relación de la persona que lo acompañará (compañero de trabajo, familiar, abogado, etc.).',
        'Señale por favor para qué empresa labora Usted.',
        '¿Señale por favor cual es el cargo que desempeña actualmente en la Compañía y describa concretamente cuáles son sus funciones?',
        '¿Conoce usted el Reglamento Interno de Trabajo y las normas internas de la Empresa?',
        '¿Quién es su jefe inmediato?',
        '¿Cumple usted cabalmente las funciones propias de su cargo?',
        '¿Acata usted las órdenes impartidas por parte de su empleador?',
        '¿Conoce las razones por las cuales fue citado(a) a esta diligencia?',
    ];

    // Preguntas estándar de cierre
    const PREGUNTAS_CIERRE = [
        '¿Informó de esta situación a su jefe inmediato?',
        '¿Había estado en diligencia de descargos anteriormente?',
        '¿Sabe usted que el incumplimiento de sus obligaciones laborales puede acarrear sanciones?',
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

            // Limitar a máximo 2 preguntas dinámicas
            $nuevasPreguntas = $this->parsearRespuestaIA($respuestaIA, 2);

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
Analiza las respuestas del trabajador teniendo en cuenta los hechos que indique en contraste con la conducta realizada que trasgrede las normas internas de la empresa y la ley aplicable en materia laboral.
•⁠  ⁠Genera nuevas preguntas si y solo si, existen inexactitudes, incongruencias, evasivas y/o contradicciones en los argumentos del trabajador.
•⁠  ⁠Máximo 2 preguntas.
•⁠  ⁠Las preguntas deben ser:
  * Claras, breves y concisas.
  * Neutrales y no sugestivas
  * Relevantes para el proceso disciplinario.
  * Dirigidas a esclarecer el hecho que dio origen a la diligencia de descargos con una tendencia de juzgamiento a favor del empleador y en contra del trabajador.
- Si no se requieren más preguntas, responde exactamente: NO_REQUIERE

FORMATO DE RESPUESTA:
Si hay preguntas, responde en este formato:
PREGUNTA_1: [texto de la pregunta]
PREGUNTA_2: [texto de la pregunta]

Si no se requieren preguntas, responde:
NO_REQUIERE
PROMPT;
    }

    /**
     * Llama a la API de IA según el proveedor configurado
     */
    protected function llamarIA(string $prompt): string
    {
        if ($this->provider === 'openai') {
            return $this->llamarOpenAI($prompt);
        }

        if ($this->provider === 'anthropic') {
            return $this->llamarAnthropic($prompt);
        }

        if ($this->provider === 'gemini') {
            return $this->llamarGemini($prompt);
        }

        throw new \Exception("Proveedor de IA no soportado: {$this->provider}");
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
     * Guarda las nuevas preguntas generadas por la IA
     */
    protected function guardarNuevasPreguntas(
        DiligenciaDescargo $diligencia,
        array $preguntas,
        int $preguntaPadreId
    ): array {
        $ultimoOrden = $diligencia->preguntas()->max('orden') ?? 0;
        $preguntasGuardadas = [];

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
    public function generarPreguntasCompletas(DiligenciaDescargo $diligencia, int $cantidadPreguntasIA = 5): array
    {
        $preguntasCreadas = [];

        // 1. Crear preguntas estándar iniciales
        $preguntasCreadas = array_merge(
            $preguntasCreadas,
            $this->crearPreguntasEstandar($diligencia, self::PREGUNTAS_INICIALES, 1, 'inicial')
        );

        // 2. Generar preguntas específicas con IA
        $preguntasIA = $this->generarPreguntasIA($diligencia, $cantidadPreguntasIA);
        $preguntasCreadas = array_merge($preguntasCreadas, $preguntasIA);

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
            // Para preguntas iniciales, las preguntas 2 y 3 (índices 1 y 2) dependen de la pregunta 1 (índice 0)
            $preguntaPadreId = null;
            if ($tipo === 'inicial' && ($index === 1 || $index === 2)) {
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
    protected function generarPreguntasIA(DiligenciaDescargo $diligencia, int $cantidadPreguntas = 5): array
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
- Ser claras, específicas y neutrales
- Permitir al trabajador explicar su versión de los hechos
- Indagar sobre circunstancias, motivaciones y contexto
- Dirigidas a esclarecer el hecho que dio origen a la diligencia de descargos con una tendencia de juzgamiento a favor del empleador y en contra del trabajador.

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
