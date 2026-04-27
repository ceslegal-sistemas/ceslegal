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

                $resultado = $this->auditarSeccion(
                    textoRIT: $textoRIT,
                    config: $config,
                    razonSocial: $empresa->razon_social,
                );

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

        // 2. Buscar normativa relevante en la biblioteca legal (RAG)
        $normativa = $this->biblioteca->buscarFragmentos(
            texto: $config['query'],
            limite: 3,
            umbral: 0.45
        );

        // 3. Construir prompt compacto y estructurado
        $seccionEncontrada = !empty(trim($fragmentoRIT));
        $contextoRIT = $seccionEncontrada
            ? "TEXTO DEL RIT (sección relevante):\n{$fragmentoRIT}"
            : "TEXTO DEL RIT: Esta sección NO fue encontrada en el documento.";

        $contextoNormativa = $normativa
            ? "NORMATIVA VIGENTE DE LA BIBLIOTECA LEGAL:\n{$normativa}"
            : "NORMATIVA: No se encontraron fragmentos específicos en la biblioteca; usa tu conocimiento del CST colombiano vigente.";

        $prompt = <<<PROMPT
Eres un abogado laboral colombiano auditando el Reglamento Interno de Trabajo de "{$razonSocial}".

SECCIÓN A AUDITAR: {$config['titulo']}

{$contextoNormativa}

{$contextoRIT}

Analiza si la sección cumple con la normativa colombiana vigente. Responde ÚNICAMENTE en JSON válido:
{
  "cumple": true o false,
  "calificacion": "Completo" | "Parcial" | "Ausente",
  "score": número entre 0 y 100,
  "hallazgos": ["hallazgo 1", "hallazgo 2"],
  "recomendaciones": ["recomendación 1", "recomendación 2"],
  "articulos_referencia": ["Art. X del CST", "Ley Y de Z"]
}
Máximo 3 hallazgos y 3 recomendaciones. Sé preciso y jurídico.
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
            'maxOutputTokens' => $forzarJSON ? 2048 : 1024,
        ];
        if ($forzarJSON) {
            $genConfig['responseMimeType'] = 'application/json';
        }

        $response = Http::withHeaders(['Content-Type' => 'application/json'])
            ->timeout(60)
            ->post($url, [
                'contents' => [
                    ['parts' => [['text' => $prompt]]],
                ],
                'generationConfig' => $genConfig,
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Error Gemini: ' . $response->body());
        }

        return $response->json('candidates.0.content.parts.0.text', '');
    }

    private function parsearJSON(string $texto): array
    {
        // Extraer JSON aunque venga envuelto en markdown
        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $texto, $m)) {
            $texto = $m[1];
        } elseif (preg_match('/(\{.*\})/s', $texto, $m)) {
            $texto = $m[1];
        }

        $datos = json_decode(trim($texto), true);
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
