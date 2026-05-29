<?php

namespace App\Services;

use App\Models\AuditoriaRIT;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\RITGeneratorService;

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


    /** Secciones obligatorias del CST con sus queries RAG, palabras clave y artículos del scraper */
    private const SECCIONES = [
        'admision' => [
            'titulo'               => 'Admisión y Período de Prueba',
            'query'                => 'admisión trabajadores período de prueba requisitos contrato periodo prueba',
            'codigos_obligatorios' => ['Art. 76 CST', 'Art. 77 CST', 'Art. 78 CST', 'Art. 80 CST'],
            'palabras_clave'       => ['admis', 'prueba', 'contrat', 'vinculac', 'ingres', 'libreta', 'embarazo', 'decreto 2663'],
            'capitulos'            => ['ADMISIÓN', 'ADMISION', 'PERÍODO DE PRUEBA', 'PERIODO DE PRUEBA'],
        ],
        'jornada' => [
            'titulo'               => 'Jornada Laboral y Horas Extras',
            'query'                => 'jornada laboral horas extras trabajo nocturno dominicales festivos trabajo suplementario recargo',
            'codigos_obligatorios' => ['Art. 158 CST', 'Art. 159 CST', 'Art. 160 CST', 'Art. 161 CST', 'Art. 162 CST', 'Art. 167 CST', 'Art. 168 CST', 'Art. 169 CST', 'Art. 179 CST', 'Art. 180 CST', 'Art. 181 CST', 'Art. 182 CST'],
            'palabras_clave'       => ['jornada', 'horario', 'hora extra', 'suplementar', 'nocturno', 'dominical', 'festiv', 'diarias', 'semanales', 'recargo'],
            'capitulos'            => ['JORNADA', 'TRABAJO SUPLEMENTARIO', 'HORAS EXTRAS', 'DOMINICALES'],
            // Captura Cap III (jornada ordinaria) + Cap IV (suplementario/extras) juntos
            'num_capitulos'        => 2,
        ],
        'descansos' => [
            'titulo'               => 'Descansos y Vacaciones',
            'query'                => 'descanso remunerado vacaciones compensatorio permisos licencias acumulación registro dominical recargo técnicos',
            'codigos_obligatorios' => ['Art. 179 CST', 'Art. 180 CST', 'Art. 181 CST', 'Art. 182 CST', 'Art. 186 CST', 'Art. 187 CST', 'Art. 188 CST', 'Art. 189 CST', 'Art. 190 CST'],
            'palabras_clave'       => ['vacacion', 'descanso', 'compensa', 'permiso', 'licencia', 'hábiles', 'consecutiv', 'registro especial', 'registro de vacac', 'dominical', 'recargo'],
            'capitulos'            => ['JORNADA', 'VACACIONES', 'DESCANSO', 'PERMISOS Y LICENCIAS', 'LICENCIAS'],
            'num_capitulos'        => 4,
        ],
        'salario' => [
            'titulo'               => 'Remuneración y Forma de Pago',
            'query'                => 'salario remuneración forma periodicidad pago deducciones propinas viáticos salario especie',
            'codigos_obligatorios' => ['Art. 127 CST', 'Art. 128 CST', 'Art. 129 CST', 'Art. 131 CST', 'Art. 132 CST', 'Art. 133 CST', 'Art. 134 CST', 'Art. 136 CST', 'Art. 143 CST', 'Art. 149 CST'],
            'palabras_clave'       => ['salario', 'remunera', 'pago', 'sueldo', 'deduccion', 'nómina', 'trueque', 'fichas', 'víveres'],
            'capitulos'            => ['REMUNERACIÓN', 'REMUNERACION', 'SALARIO', 'FORMA DE PAGO'],
        ],
        'disciplina' => [
            'titulo'               => 'Régimen Disciplinario',
            'query'                => 'régimen disciplinario faltas leves graves sanciones descargos procedimiento multa suspensión',
            'codigos_obligatorios' => ['Art. 108 CST', 'Art. 111 CST', 'Art. 112 CST', 'Art. 113 CST', 'Art. 114 CST', 'Art. 115 CST'],
            'palabras_clave'       => ['falta', 'sanc', 'disciplin', 'descargo', 'amonestac', 'suspens', 'sindical', 'multa', '1/5'],
            'capitulos'            => ['RÉGIMEN DISCIPLINARIO', 'REGIMEN DISCIPLINARIO', 'FALTAS', 'SANCIONES', 'ESCALA DE SANCIONES'],
            // Captura Cap VIII (clasificación de faltas) + Cap IX (escala de sanciones) juntos
            'num_capitulos'        => 2,
        ],
        'sst' => [
            'titulo'               => 'Seguridad y Salud en el Trabajo (SG-SST)',
            'query'                => 'seguridad salud trabajo SG-SST riesgos profesionales accidentes enfermedades laborales EPP COPASST',
            'codigos_obligatorios' => [],
            'palabras_clave'       => ['seguridad', 'salud', 'riesgo', 'accidente', 'SST', 'ARL', 'EPP', 'alcoholemia', 'psicoactiv', 'médico'],
            'capitulos'            => ['SEGURIDAD Y SALUD', 'SG-SST', 'SST'],
        ],
        'acoso' => [
            'titulo'               => 'Acoso Laboral y Sexual',
            'query'                => 'acoso laboral sexual prevención comité convivencia modalidades procedimiento queja denuncia',
            'codigos_obligatorios' => [
                'Art. 1 Ley 1010', 'Art. 2 Ley 1010', 'Art. 6 Ley 1010', 'Art. 7 Ley 1010',
                'Art. 9 Ley 1010', 'Art. 10 Ley 1010', 'Art. 11 Ley 1010', 'Art. 13 Ley 1010',
                'Art. 3 Res. 652/2012', 'Art. 5 Res. 652/2012', 'Art. 6 Res. 652/2012',
                'Art. 7 Res. 652/2012', 'Art. 8 Res. 652/2012', 'Art. 9 Res. 652/2012',
            ],
            'palabras_clave'       => ['acoso', 'hostigamiento', 'sexual', 'convivencia', 'matonismo', 'bipartit', '734', 'comité'],
            'capitulos'            => ['ACOSO', 'CONVIVENCIA LABORAL', 'COMITÉ DE CONVIVENCIA', 'PREVENCIÓN DE ACOSO'],
        ],
        'grupos_protegidos' => [
            'titulo'               => 'Protección de Sujetos Especiales',
            'query'                => 'mujer embarazada maternidad paternidad discapacidad fuero sindical estabilidad laboral reforzada',
            'codigos_obligatorios' => ['Art. 239 CST', 'Art. 240 CST', 'Art. 241 CST', 'Art. 241A CST'],
            'palabras_clave'       => ['maternidad', 'paternidad', 'embarazo', 'discapacidad', 'fuero', 'sindical', 'sujetos especial'],
            'capitulos'            => ['SUJETOS DE ESPECIAL', 'ESPECIAL PROTECCIÓN', 'GRUPOS PROTEGIDOS', 'TRABAJADORES PROTEGIDOS'],
        ],
    ];

    public function __construct(
        private BibliotecaLegalService $biblioteca,
        private RITGeneratorService    $ritGenerator,
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
            // Persistir texto en BD para que el job de mejora lo encuentre aunque expire la caché
            'texto_auditado'       => $textoExternoRIT ?: ($rit?->texto_completo),
        ]);

        // Mantener caché como capa adicional de disponibilidad (bajo coste)
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
            // Nota: texto_auditado persiste el texto en BD como fuente primaria/fallback.
            // Para fuente 'externo' la caché puede no existir si el worker arrancó tarde.
            $textoRIT = $auditoria->fuente === 'externo'
                ? (cache()->pull("auditoria_rit_texto_{$auditoria->id}", '') ?: ($auditoria->texto_auditado ?? ''))
                : ($auditoria->texto_auditado ?: ($auditoria->reglamento?->texto_completo ?? ''));

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
                        razonSocial: $empresa->nombre_completo,
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

            // ── Segunda pasada: reintentar secciones fallidas ─────────────────────
            $fallidas = array_keys(array_filter($secciones, fn($s) => ($s['calificacion'] ?? '') === 'Error'));
            if (!empty($fallidas)) {
                Log::warning('AuditoriaRIT: reintentando ' . count($fallidas) . ' sección(es) fallida(s)', [
                    'auditoria_id' => $auditoria->id,
                    'secciones'    => $fallidas,
                ]);

                foreach ($fallidas as $clave) {
                    sleep(5); // Pausa antes de reintentar para dejar que la API se recupere
                    try {
                        $secciones[$clave] = $this->auditarSeccion(
                            textoRIT:    $textoRIT,
                            config:      self::SECCIONES[$clave],
                            razonSocial: $empresa->nombre_completo,
                        );
                        Log::info("AuditoriaRIT: sección '{$clave}' recuperada en segunda pasada");
                    } catch (\Throwable $e) {
                        Log::error("AuditoriaRIT: sección '{$clave}' falló también en segunda pasada", [
                            'error' => substr($e->getMessage(), 0, 300),
                        ]);
                    }
                    $auditoria->update(['secciones' => $secciones]);
                }

                // Recalcular score total con los resultados finales
                $scoreTotal = collect($secciones)->sum(fn($s) => $s['score'] ?? 0);
            }

            $numSecciones = count(self::SECCIONES);
            $scoreGeneral = (int) round($scoreTotal / $numSecciones);
            $resumen      = $this->generarResumen($secciones, $empresa->nombre_completo, $scoreGeneral);

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
        $fragmentoRIT = $this->extraerFragmentoRIT(
            $textoRIT,
            $config['palabras_clave'],
            $config['capitulos'] ?? [],
            $config['num_capitulos'] ?? 1
        );

        // 2. Artículos obligatorios por código exacto (fuente primaria)
        $codigosObligatorios = $config['codigos_obligatorios'] ?? [];
        $articulosCst        = $this->ritGenerator->obtenerArticulosObligatorios($codigosObligatorios);

        // 2b. Búsqueda por tema en articulos_legales (igual que el generador) para que el auditor
        //     tenga acceso a los mismos artículos que el generador inyectó al crear el RIT.
        $articulosTema = $this->ritGenerator->buscarArticulosPorTema(
            queryTema:   $config['query'],
            yaObtenidos: $codigosObligatorios,
            limite:      6,
        );
        if (!empty(trim($articulosTema))) {
            $articulosCst = trim($articulosCst . "\n\n" . $articulosTema);
        }

        // 3. Biblioteca RAG — fuente complementaria
        $normativaRag = $this->biblioteca->buscarFragmentos(
            texto: $config['query'],
            limite: 4,
            umbral: 0.35
        );

        // 4. Sin ninguna fuente de normativa → abortar con advertencia
        if (empty(trim($articulosCst)) && empty(trim($normativaRag ?? ''))) {
            Log::warning("AuditoriaRIT: sin normativa (scraper ni biblioteca) para '{$config['titulo']}'");
            return [
                'titulo'               => $config['titulo'],
                'cumple'               => false,
                'calificacion'         => 'Sin base normativa',
                'score'                => 0,
                'hallazgos'            => ['No se encontró normativa CST para auditar esta sección. Ejecute el scraper o cargue documentos en la biblioteca legal.'],
                'recomendaciones'      => ['Ejecute php artisan cst:scraper para poblar la base normativa.'],
                'articulos_referencia' => [],
                'seccion_encontrada'   => !empty(trim($fragmentoRIT)),
            ];
        }

        // 5. Construir prompt con artículos reales del CST + RAG como contexto jurídico
        $seccionEncontrada = !empty(trim($fragmentoRIT));
        $contextoRIT = $seccionEncontrada
            ? "TEXTO DEL RIT — SECCIÓN RELEVANTE:\n{$fragmentoRIT}"
            : "TEXTO DEL RIT: Esta sección NO fue encontrada en el documento — calificar como Ausente.";

        $seccionArticulos = $articulosCst
            ? "\nARTÍCULOS OFICIALES DEL CST (fuente: base de datos interna — principal referencia normativa):\n{$articulosCst}\n"
            : '';

        $seccionRag = $normativaRag
            ? "\nFRAGMENTOS DE LA BIBLIOTECA JURÍDICA (fuente complementaria):\n{$normativaRag}\n"
            : '';

        $prompt = <<<PROMPT
Eres un auditor legal que revisa el Reglamento Interno de Trabajo de "{$razonSocial}".

REGLA FUNDAMENTAL — ANTI-ALUCINACIÓN (INCUMPLIRLA INVALIDA LA AUDITORÍA):
PROHIBICIÓN ABSOLUTA: En los campos "hallazgos" y "recomendaciones" NUNCA menciones ningún
número de artículo, nombre de ley, decreto, resolución, numeral, parágrafo, sentencia,
porcentaje, plazo en días ni salario mínimo que NO aparezca LITERALMENTE en la sección
"ARTÍCULOS OFICIALES" o "FRAGMENTOS DE BIBLIOTECA" inyectada abajo.
Esto incluye sub-referencias como "Num. 7", "Parágrafo 2°", "literal b" si no están en el texto.
Si el contexto es insuficiente para evaluar un aspecto, describe el hallazgo en términos
generales SIN citar artículo alguno (ej: "El RIT no incluye el procedimiento de descargos").

Para "articulos_referencia": copia TEXTUALMENTE los códigos TAL COMO aparecen en el contexto
(ej: "Art. 115 CST", "Art. 7 Ley 1010"). NUNCA reformatees ni añadas numerales o sub-referencias
que no estén en el texto inyectado. Si no hay artículos relevantes, devuelve [].
{$seccionArticulos}{$seccionRag}
SECCIÓN A AUDITAR: {$config['titulo']}

{$contextoRIT}

Evalúa si el RIT cumple lo que establece el contexto jurídico para esta sección.
Responde ÚNICAMENTE con JSON válido (sin texto adicional antes ni después):
{
  "cumple": boolean,
  "calificacion": "Completo" | "Parcial" | "Ausente",
  "score": integer 0-100,
  "hallazgos": [ máximo 3 strings sin citar artículos fuera del contexto, máx 120 chars c/u ],
  "recomendaciones": [ máximo 3 strings sin citar artículos fuera del contexto, máx 120 chars c/u ],
  "articulos_referencia": [ códigos copiados textualmente del contexto, máximo 5, o [] ]
}
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
     * Extrae el fragmento relevante del RIT para una sección temática.
     *
     * Estrategia 1 (preferida): detectar el CAPÍTULO correspondiente y extraer
     *   el texto completo hasta el siguiente CAPÍTULO. Garantiza capturar todos
     *   los artículos del capítulo, no solo los que contienen la palabra clave.
     *
     * Estrategia 2 (fallback): búsqueda por palabras_clave con ±10 líneas de
     *   contexto alrededor de cada coincidencia.
     */
    private function extraerFragmentoRIT(string $textoRIT, array $palabrasClave, array $capitulos = [], int $numCapitulos = 1): string
    {
        $lineas = explode("\n", $textoRIT);
        $total  = count($lineas);

        // ── Estrategia 1: extracción por encabezado CAPÍTULO ──────────────────
        // El generador produce DOS líneas: "CAPÍTULO III" seguido de "JORNADA ORDINARIA..."
        // Por eso se revisa la línea actual Y la siguiente para encontrar el título.
        if (!empty($capitulos)) {
            $inicio = null;
            foreach ($lineas as $i => $linea) {
                if (!preg_match('/CAP[IÍ]TULO/ui', $linea)) continue;
                // Línea actual (ej: "CAPÍTULO III JORNADA...") + línea siguiente (ej: "JORNADA...")
                $lineaUp     = mb_strtoupper($linea);
                $siguienteUp = isset($lineas[$i + 1]) ? mb_strtoupper($lineas[$i + 1]) : '';
                foreach ($capitulos as $keyword) {
                    $kw = mb_strtoupper($keyword);
                    if (str_contains($lineaUp, $kw) || str_contains($siguienteUp, $kw)) {
                        $inicio = $i;
                        break 2;
                    }
                }
            }

            if ($inicio !== null) {
                // Buscar el encabezado CAPÍTULO que delimita el bloque.
                // num_capitulos > 1 captura N capítulos consecutivos (ej: jornada=2 toma Cap III + Cap IV).
                $fin           = $total;
                $chapterCount  = 0;
                for ($i = $inicio + 1; $i < $total; $i++) {
                    if (preg_match('/CAP[IÍ]TULO/ui', $lineas[$i])) {
                        $chapterCount++;
                        if ($chapterCount >= $numCapitulos) {
                            $fin = $i;
                            break;
                        }
                    }
                }

                $fragmento = implode("\n", array_slice($lineas, $inicio, $fin - $inicio));
                if (!empty(trim($fragmento))) {
                    return trim($fragmento);
                }
            }
        }

        // ── Estrategia 2: palabras clave con ±10 líneas de contexto ───────────
        $indices = [];
        foreach ($lineas as $i => $linea) {
            $lineaNorm = mb_strtolower($linea);
            foreach ($palabrasClave as $clave) {
                if (str_contains($lineaNorm, mb_strtolower($clave))) {
                    for ($j = max(0, $i - 10); $j <= min($total - 1, $i + 10); $j++) {
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

        return trim($fragmento);
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

REGLA FUNDAMENTAL: NO cites ningún artículo, ley, decreto, resolución ni norma específica por nombre o número.
Usa únicamente términos generales como "la legislación laboral vigente", "las normas de seguridad en el trabajo",
"el régimen disciplinario exigido por la ley", etc.

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
        $config = config('services.ia.gemini', []);
        $apiKey = $config['api_key'] ?? '';

        // Cascade con configuración específica por modelo:
        // - flash/flash-lite soportan thinkingBudget:0 (respuesta inmediata, sin razonamiento)
        // - gemini-2.5-pro REQUIERE thinking mode (budget >= 1); usar 2048 como mínimo seguro
        $modelosConfig = [
            'gemini-2.5-flash'      => ['budget' => 0,    'timeout' => 120],
            'gemini-2.5-flash-lite' => ['budget' => 0,    'timeout' => 120],
            'gemini-2.5-pro'        => ['budget' => 2048, 'timeout' => 180],
        ];
        $modelos      = array_keys($modelosConfig);
        $totalModelos = count($modelos);

        $genConfigBase = [
            'temperature' => 0.2,
        ];
        if ($forzarJSON) {
            $genConfigBase['responseMimeType'] = 'application/json';
        }

        $lastError = '';

        foreach (array_values($modelos) as $idx => $model) {
            $url        = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
            $esUltimo   = ($idx === $totalModelos - 1);
            $cfg        = $modelosConfig[$model];
            $sobrecarga = false;

            // Config de generación específica: thinkingBudget según soporte del modelo
            $genConfig = $genConfigBase;
            if ($forzarJSON) {
                $genConfig['thinkingConfig'] = ['thinkingBudget' => $cfg['budget']];
            }

            $payload = [
                'contents'         => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => $genConfig,
            ];

            for ($intento = 1; $intento <= 2; $intento++) {
                try {
                    $response = Http::withHeaders(['Content-Type' => 'application/json'])
                        ->timeout($cfg['timeout'])
                        ->post($url, $payload);
                } catch (\Illuminate\Http\Client\ConnectionException $ce) {
                    Log::warning("AuditoriaRIT: timeout de red en {$model} (intento {$intento}), cascadeando", [
                        'error' => $ce->getMessage(),
                    ]);
                    $sobrecarga = true;
                    break;
                }

                if ($response->successful()) {
                    // El modelo thinking incluye razonamiento en parts anteriores;
                    // la respuesta real es el último part sin flag 'thought'
                    $parts = $response->json('candidates.0.content.parts', []);
                    foreach (array_reverse($parts) as $part) {
                        if (empty($part['thought']) && isset($part['text']) && $part['text'] !== '') {
                            return $part['text'];
                        }
                    }
                    return $response->json('candidates.0.content.parts.0.text', '');
                }

                $status    = $response->status();
                $lastError = $response->body();

                Log::warning("AuditoriaRIT: Gemini {$status} en modelo {$model}, intento {$intento}");

                if (in_array($status, [429, 503])) {
                    $sobrecarga = true;
                    break;
                }

                // Error 400 por incompatibilidad de thinkingBudget → cascade al siguiente
                if ($status === 400 && str_contains($lastError, 'thinking')) {
                    Log::warning("AuditoriaRIT: {$model} rechazó thinkingBudget, cascadeando");
                    $sobrecarga = true;
                    break;
                }

                // Error permanente real → lanzar excepción
                if (!in_array($status, [500, 502, 504])) {
                    throw new \RuntimeException('Error en API Gemini: ' . $lastError);
                }
                // 500/502/504 transitorio → segundo intento
            }

            if ($sobrecarga && !$esUltimo) {
                Log::warning("AuditoriaRIT: {$model} → {$modelos[$idx + 1]}");
                sleep(2); // Pausa corta para evitar rate-limit encadenado (OK en cola async)
                continue;
            }

            break;
        }

        throw new \RuntimeException('Error Gemini (todos los modelos intentados): ' . $lastError);
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
