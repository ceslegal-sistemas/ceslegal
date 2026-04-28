<?php

namespace App\Services;

use App\Models\AuditoriaRIT;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Servicio de Auditoría de Reglamento Interno de Trabajo.
 *
 * Estrategia de eficiencia de tokens:
 * - El RIT se procesa por secciones temáticas (no todo de una vez).
 * - Para cada sección se hace un RAG targetizado (3-4 fragmentos de la biblioteca).
 * - Se extrae solo el fragmento relevante del RIT (~1,500 chars) por sección.
 * - Resultado: ~3,000 tokens/sección × 8 secciones ≈ 24,000 tokens totales.
 */
class AuditoriaRITService
{
    /** Máximo de caracteres del RIT a enviar por sección (~375 palabras) */
    private const MAX_CHARS_SECCION = 2000;

    /** Secciones obligatorias del CST con sus queries RAG y palabras clave */
    private const SECCIONES = [
        'admision' => [
            'titulo'         => 'Admisión y Período de Prueba',
            'query'          => 'admisión trabajadores período de prueba requisitos contrato Art. 76 80 CST',
            'palabras_clave' => ['admis', 'prueba', 'contrat', 'vinculac', 'ingres'],
        ],
        'jornada' => [
            'titulo'         => 'Jornada Laboral y Horas Extras',
            'query'          => 'jornada laboral horas extras trabajo nocturno dominicales festivos Art. 158 168 179 CST',
            'palabras_clave' => ['jornada', 'horario', 'hora extra', 'nocturno', 'dominical', 'festiv'],
        ],
        'descansos' => [
            'titulo'         => 'Descansos y Vacaciones',
            'query'          => 'descanso remunerado vacaciones compensación permisos Art. 186 192 CST',
            'palabras_clave' => ['vacacion', 'descanso', 'compensa', 'permiso', 'licencia'],
        ],
        'salario' => [
            'titulo'         => 'Remuneración y Forma de Pago',
            'query'          => 'salario remuneración forma periodicidad pago deducciones Art. 127 149 CST',
            'palabras_clave' => ['salario', 'remunera', 'pago', 'sueldo', 'deduccion', 'nómina'],
        ],
        'disciplina' => [
            'titulo'         => 'Régimen Disciplinario',
            'query'          => 'régimen disciplinario faltas leves graves sanciones descargos procedimiento Art. 105 113 CST',
            'palabras_clave' => ['falta', 'sanc', 'disciplin', 'descargo', 'amonestac', 'suspens'],
        ],
        'sst' => [
            'titulo'         => 'Seguridad y Salud en el Trabajo (SG-SST)',
            'query'          => 'seguridad salud trabajo SG-SST riesgos profesionales accidentes enfermedades laborales obligaciones empleador',
            'palabras_clave' => ['seguridad', 'salud', 'riesgo', 'accidente', 'SST', 'ARL', 'EPP'],
        ],
        'acoso' => [
            'titulo'         => 'Acoso Laboral y Sexual',
            'query'          => 'acoso laboral sexual prevención Ley 1010 2006 Ley 2365 2024 comité convivencia',
            'palabras_clave' => ['acoso', 'hostigamiento', 'sexual', 'convivencia', 'matonismo', 'Ley 1010', 'Ley 2365'],
        ],
        'grupos_protegidos' => [
            'titulo'         => 'Protección de Sujetos Especiales',
            'query'          => 'mujer embarazada maternidad paternidad discapacidad fuero circunstancial trabajadores protegidos',
            'palabras_clave' => ['maternidad', 'paternidad', 'embarazo', 'discapacidad', 'fuero', 'mujer', 'sindical'],
        ],
    ];

    public function __construct(
        private BibliotecaLegalService $biblioteca
    ) {}

    /**
     * Crea el registro de auditoría en estado 'pendiente'.
     * El procesamiento real lo hace procesarAuditoria() (llamado desde Job o síncronamente).
     */
    public function iniciar(Empresa $empresa, ?string $textoExternoRIT = null): AuditoriaRIT
    {
        $rit = ReglamentoInterno::where('empresa_id', $empresa->id)
            ->orderByDesc('updated_at')
            ->first();

        $fuente = $textoExternoRIT ? 'externo' : 'sistema';

        $auditoria = AuditoriaRIT::create([
            'empresa_id'           => $empresa->id,
            'reglamento_interno_id' => $rit?->id,
            'estado'               => 'pendiente',
            'fuente'               => $fuente,
        ]);

        // Guardar texto externo en caché temporal para que el Job lo recupere
        if ($textoExternoRIT) {
            cache()->put("auditoria_rit_texto_{$auditoria->id}", $textoExternoRIT, now()->addHours(2));
        }

        return $auditoria;
    }

    /**
     * Procesa la auditoría sección por sección.
     * Actualiza el registro en BD después de cada sección para mostrar progreso en tiempo real.
     */
    public function procesarAuditoria(AuditoriaRIT $auditoria): void
    {
        $auditoria->update(['estado' => 'procesando', 'secciones' => []]);

        try {
            $empresa = $auditoria->empresa;

            // Obtener texto del RIT
            $textoRIT = $auditoria->fuente === 'externo'
                ? cache()->pull("auditoria_rit_texto_{$auditoria->id}", '')
                : ($auditoria->reglamento?->texto_completo ?? '');

            if (empty(trim($textoRIT))) {
                throw new \RuntimeException('No se encontró texto del RIT para auditar.');
            }

            $secciones = [];
            $scoreTotal = 0;

            foreach (self::SECCIONES as $clave => $config) {
                Log::info("AuditoriaRIT: procesando sección '{$config['titulo']}'", [
                    'auditoria_id' => $auditoria->id,
                ]);

                try {
                    $resultado = $this->auditarSeccion(
                        textoRIT: $textoRIT,
                        config: $config,
                        razonSocial: $empresa->razon_social,
                    );
                } catch (\Throwable $e) {
                    // Sección fallida → marcar y continuar con las demás
                    Log::warning("AuditoriaRIT: sección '{$config['titulo']}' falló, se continúa", [
                        'error' => substr($e->getMessage(), 0, 200),
                    ]);
                    $resultado = [
                        'titulo'              => $config['titulo'],
                        'cumple'              => false,
                        'calificacion'        => 'Error',
                        'score'               => 0,
                        'hallazgos'           => ['No se pudo analizar esta sección. Intente de nuevo.'],
                        'recomendaciones'     => [],
                        'articulos_referencia' => [],
                        'seccion_encontrada'  => false,
                    ];
                }

                $secciones[$clave] = $resultado;
                $scoreTotal += $resultado['score'] ?? 0;

                // Guardar progreso parcial tras cada sección
                $auditoria->update(['secciones' => $secciones]);
            }

            $numSecciones = count(self::SECCIONES);
            $scoreGeneral = (int) round($scoreTotal / $numSecciones);
            $resumen      = $this->generarResumen($secciones, $empresa->razon_social, $scoreGeneral);

            $auditoria->update([
                'estado'          => 'completado',
                'score'           => $scoreGeneral,
                'resumen_general' => $resumen,
                'secciones'       => $secciones,
            ]);

            Log::info("AuditoriaRIT: completada con score {$scoreGeneral}/100", [
                'auditoria_id' => $auditoria->id,
                'empresa_id'   => $empresa->id,
            ]);

        } catch (\Throwable $e) {
            Log::error('AuditoriaRIT: error en procesamiento', [
                'auditoria_id' => $auditoria->id,
                'error'        => $e->getMessage(),
            ]);

            $auditoria->update([
                'estado'        => 'error',
                'mensaje_error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Audita una sección temática del RIT contra la biblioteca legal.
     */
    private function auditarSeccion(string $textoRIT, array $config, string $razonSocial): array
    {
        // 1. Extraer fragmento relevante del RIT para esta sección (sin enviar todo el documento)
        $fragmentoRIT = $this->extraerFragmentoRIT($textoRIT, $config['palabras_clave']);

        // 2. Buscar normativa relevante en la biblioteca legal (RAG) — umbral bajo para capturar más
        $normativa = $this->biblioteca->buscarFragmentos(
            texto: $config['query'],
            limite: 5,
            umbral: 0.35
        );

        // 3. Sin documentos en la biblioteca → no se puede auditar esta sección con fundamento
        if (empty(trim($normativa ?? ''))) {
            Log::warning("AuditoriaRIT: biblioteca sin documentos para sección '{$config['titulo']}'");
            return [
                'titulo'              => $config['titulo'],
                'cumple'              => false,
                'calificacion'        => 'Sin base normativa',
                'score'               => 0,
                'hallazgos'           => ['La biblioteca legal no contiene documentos para auditar esta sección.'],
                'recomendaciones'     => ['Cargue los documentos normativos correspondientes en la biblioteca legal.'],
                'articulos_referencia' => [],
                'seccion_encontrada'  => !empty(trim($fragmentoRIT)),
            ];
        }

        // 4. Construir prompt — Gemini debe limitarse EXCLUSIVAMENTE a la biblioteca legal
        $seccionEncontrada = !empty(trim($fragmentoRIT));
        $contextoRIT = $seccionEncontrada
            ? "TEXTO DEL RIT (sección relevante):\n{$fragmentoRIT}"
            : "TEXTO DEL RIT: Esta sección NO fue encontrada en el documento.";

        $prompt = <<<PROMPT
Eres un auditor legal que revisa el Reglamento Interno de Trabajo de "{$razonSocial}".

INSTRUCCIÓN CRÍTICA: Basa tu análisis EXCLUSIVAMENTE en los fragmentos de la biblioteca jurídica
proporcionados a continuación. NO uses tu conocimiento de entrenamiento, NO cites normas que no
aparezcan en los fragmentos provistos. Si los fragmentos no son suficientes para evaluar un aspecto,
indícalo en hallazgos. Cita siempre el documento fuente de cada hallazgo.

SECCIÓN A AUDITAR: {$config['titulo']}

FRAGMENTOS DE LA BIBLIOTECA JURÍDICA (única fuente autorizada):
{$normativa}

{$contextoRIT}

Analiza si el RIT cumple la normativa provista. JSON de respuesta (sin texto adicional):
- cumple: boolean
- calificacion: "Completo", "Parcial" o "Ausente"
- score: integer 0-100
- hallazgos: array máximo 2 strings, cada uno máximo 80 caracteres
- recomendaciones: array máximo 2 strings, cada uno máximo 80 caracteres
- articulos_referencia: array máximo 4 strings cortos (ej: "Art. 76 CST")
PROMPT;

        $respuesta = $this->llamarIA($prompt, true);
        $datos     = $this->parsearJSON($respuesta);

        return array_merge([
            'titulo'              => $config['titulo'],
            'cumple'              => false,
            'calificacion'        => 'Ausente',
            'score'               => 0,
            'hallazgos'           => [],
            'recomendaciones'     => [],
            'articulos_referencia' => [],
            'seccion_encontrada'  => $seccionEncontrada,
        ], $datos, ['titulo' => $config['titulo'], 'seccion_encontrada' => $seccionEncontrada]);
    }

    /**
     * Extrae las líneas más relevantes del RIT para una sección temática.
     * Divide por línea individual (el RIT generado usa \n simples, no dobles).
     * Toma ±4 líneas de contexto alrededor de cada coincidencia.
     */
    private function extraerFragmentoRIT(string $textoRIT, array $palabrasClave): string
    {
        $lineas     = explode("\n", $textoRIT);
        $indices    = [];

        foreach ($lineas as $i => $linea) {
            $lineaNorm = mb_strtolower($linea);
            foreach ($palabrasClave as $clave) {
                if (str_contains($lineaNorm, mb_strtolower($clave))) {
                    // Incluir contexto ±4 líneas
                    for ($j = max(0, $i - 4); $j <= min(count($lineas) - 1, $i + 4); $j++) {
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
            if ($i > $prev + 1) $fragmento .= "\n"; // separador entre bloques
            $fragmento .= $lineas[$i] . "\n";
            $prev = $i;
        }

        return mb_substr(trim($fragmento), 0, self::MAX_CHARS_SECCION);
    }

    /**
     * Genera un resumen ejecutivo de la auditoría completa.
     * UNA sola llamada a IA, con el resumen de secciones (no el texto completo del RIT).
     */
    private function generarResumen(array $secciones, string $razonSocial, int $score): string
    {
        $listaSecciones = '';
        foreach ($secciones as $seccion) {
            $listaSecciones .= "- {$seccion['titulo']}: {$seccion['calificacion']} ({$seccion['score']}/100)\n";
        }

        $prompt = <<<PROMPT
Eres un abogado laboral colombiano. Redacta un resumen ejecutivo profesional de la auditoría del RIT de "{$razonSocial}".

Score general: {$score}/100
Resultados por sección:
{$listaSecciones}

Redacta 2-3 párrafos indicando: (1) estado general del cumplimiento, (2) principales riesgos jurídicos identificados, (3) acciones prioritarias recomendadas. Tono formal y jurídico. Sin markdown.
PROMPT;

        try {
            return trim($this->llamarIA($prompt));
        } catch (\Throwable $e) {
            return "Auditoría completada con score {$score}/100. Revise los resultados por sección para el detalle de hallazgos y recomendaciones.";
        }
    }

    private function llamarIA(string $prompt, bool $forzarJSON = false): string
    {
        $config  = config('services.ia.gemini', []);
        $apiKey  = $config['api_key'] ?? '';
        $model   = $config['model'] ?? 'gemini-2.5-flash';
        $url     = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $genConfig = [
            'temperature'     => 0.2,
            'maxOutputTokens' => $forzarJSON ? 2048 : 768,
        ];
        if ($forzarJSON) {
            $genConfig['responseMimeType'] = 'application/json';
        }

        $payload = [
            'contents'         => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => $genConfig,
        ];

        // Un único reintento inmediato para 503/429 — sin sleep() porque
        // en cola sync el tiempo de espera acumula y desborda el timeout del servidor
        for ($intento = 1; $intento <= 2; $intento++) {
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(60)
                ->post($url, $payload);

            if ($response->successful()) {
                // gemini-2.5-flash (thinking model) incluye el pensamiento en parts[0]
                // y la respuesta real en el último part que no sea "thought"
                $parts = $response->json('candidates.0.content.parts', []);
                foreach (array_reverse($parts) as $part) {
                    if (empty($part['thought']) && isset($part['text']) && $part['text'] !== '') {
                        return $part['text'];
                    }
                }
                // Fallback por si no hay parts o todos son thought
                return $response->json('candidates.0.content.parts.0.text', '');
            }

            $status = $response->status();

            if (!in_array($status, [429, 503]) || $intento === 2) {
                throw new \RuntimeException('Error Gemini (' . $status . '): ' . $response->body());
            }

            Log::warning("AuditoriaRIT: Gemini {$status}, reintentando inmediatamente…");
        }

        throw new \RuntimeException('Error Gemini: reintento fallido.');
    }

    private function parsearJSON(string $texto): array
    {
        $texto = trim($texto);

        // Con responseMimeType:application/json el texto ya es JSON puro → intentar directo
        $datos = json_decode($texto, true);
        if (is_array($datos)) {
            return $datos;
        }

        // Fallback: extraer JSON de bloque markdown o texto libre
        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $texto, $m)) {
            $datos = json_decode(trim($m[1]), true);
        } elseif (preg_match('/(\{.*\})/s', $texto, $m)) {
            $datos = json_decode(trim($m[1]), true);
        }

        if (!is_array($datos)) {
            Log::warning('AuditoriaRIT: parsearJSON falló', [
                'chars'  => strlen($texto),
                'inicio' => substr($texto, 0, 200),
            ]);
        }

        return is_array($datos) ? $datos : [];
    }

    public static function getTitulosSecciones(): array
    {
        return array_map(fn($s) => $s['titulo'], self::SECCIONES);
    }

    public static function getNumSecciones(): int
    {
        return count(self::SECCIONES);
    }
}
