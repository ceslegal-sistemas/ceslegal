<?php

namespace App\Services;

use App\Models\AuditoriaRIT;
use App\Models\ReglamentoInterno;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Genera un RIT mejorado (v+1) a partir de una auditoría completada.
 *
 * Flujo:
 * 1. Obtiene el texto del RIT auditado.
 * 2. Parsea el texto original en sus 16 capítulos.
 * 3. Por cada capítulo: incorpora artículos del scraper + RAG + hallazgos de auditoría.
 * 4. Llama a Gemini capítulo por capítulo (cascade flash → flash-lite).
 * 5. Ensambla el texto completo y crea un nuevo ReglamentoInterno (version+1).
 * 6. Genera PDF permanente con DomPDF.
 *
 * REGLA ANTI-ALUCINACIÓN: ningún artículo, ley, porcentaje ni plazo se hardcodea
 * en los prompts. Todo contenido legal proviene de:
 *   a) articulosObligatorios → tabla articulos_legales (scraper leyes.co)
 *   b) rag → tabla fragmentos_documento (biblioteca RAG)
 */
class RITMejoradoService
{
    /**
     * Mapeo capítulo (número romano) → claves de secciones en AuditoriaRIT::secciones.
     * Capítulos sin hallazgos específicos reciben lista vacía.
     */
    private const CAPITULO_A_SECCION = [
        'I'    => [],
        'II'   => ['admision'],
        'III'  => ['jornada'],
        'IV'   => ['jornada'],
        'V'    => ['salario'],
        'VI'   => ['descansos'],
        'VII'  => ['descansos'],
        'VIII' => ['disciplina'],
        'IX'   => ['disciplina'],
        'X'    => [],
        'XI'   => [],
        'XII'  => ['sst'],
        'XIII' => [],
        'XIV'  => ['acoso'],
        'XV'   => ['grupos_protegidos'],
        'XVI'  => [],
    ];

    private string $modeloUsado = 'gemini-2.5-flash';

    public function __construct(
        private BibliotecaLegalService $biblioteca,
        private RITGeneratorService    $ritGenerator,
    ) {}

    // ── Punto de entrada ──────────────────────────────────────────────────────

    /**
     * Genera el RIT mejorado capítulo a capítulo, persiste archivos y actualiza auditoría.
     *
     * @param  \Closure|null  $onProgress  fn(int $cap, int $total, string $titulo): void
     * @throws \RuntimeException si el texto base está vacío o todos los modelos fallan.
     */
    public function generar(AuditoriaRIT $auditoria, ?\Closure $onProgress = null): ReglamentoInterno
    {
        $empresa = $auditoria->empresa;

        // ── 1. Mejorar capítulo por capítulo ──────────────────────────────────
        $textoMejorado = $this->mejorarCapitulosRIT($auditoria, $onProgress);

        // ── 2. Determinar versión ─────────────────────────────────────────────
        $ritOrigen       = $auditoria->reglamento;
        $siguienteVersion = ($ritOrigen?->version ?? 1) + 1;
        $nombreMejorado  = ($ritOrigen?->nombre ?? 'Reglamento Interno de Trabajo')
            . " (v{$siguienteVersion})";

        // ── 3. Crear nuevo ReglamentoInterno ──────────────────────────────────
        $ritMejorado = ReglamentoInterno::create([
            'empresa_id'              => $empresa->id,
            'nombre'                  => $nombreMejorado,
            'texto_completo'          => $textoMejorado,
            'ruta_docx'               => null,
            'activo'                  => false,
            'respuestas_cuestionario' => $ritOrigen?->respuestas_cuestionario,
            'fuente'                  => 'mejora_ia',
            'version'                 => $siguienteVersion,
            'auditoria_origen_id'     => $auditoria->id,
            'reglamento_origen_id'    => $ritOrigen?->id,
        ]);

        // ── 4. PDF permanente ─────────────────────────────────────────────────
        $rutaPdf = $this->generarPDFPermanente($textoMejorado, $empresa, $ritMejorado->id, $siguienteVersion);
        if ($rutaPdf) {
            $ritMejorado->update(['ruta_pdf' => $rutaPdf]);
        }

        Log::info('RITMejoradoService: RIT mejorado generado', [
            'auditoria_id'    => $auditoria->id,
            'empresa_id'      => $empresa->id,
            'rit_mejorado_id' => $ritMejorado->id,
            'version'         => $siguienteVersion,
            'modelo'          => $this->modeloUsado,
        ]);

        // ── 5. Actualizar auditoría ───────────────────────────────────────────
        $auditoria->update([
            'estado_mejora'          => 'completado',
            'reglamento_mejorado_id' => $ritMejorado->id,
        ]);

        return $ritMejorado;
    }

    // ── Mejora capítulo por capítulo ──────────────────────────────────────────

    /**
     * Parsea el RIT original en capítulos, mejora cada uno con Gemini
     * incorporando artículos del scraper + RAG + hallazgos de la auditoría.
     */
    public function mejorarCapitulosRIT(AuditoriaRIT $auditoria, ?\Closure $onProgress = null): string
    {
        $textoOriginal      = $this->obtenerTextoOriginal($auditoria);
        $capitulosOriginales = $this->parsearCapitulos($textoOriginal);
        $secciones          = $auditoria->secciones ?? [];
        $empresa            = $auditoria->empresa;
        // Respuestas del cuestionario del RIT origen (vacío para RITs subidos manualmente)
        $respuestas         = (array) ($auditoria->reglamento?->respuestas_cuestionario ?? []);

        $capitulos    = RITGeneratorService::getCapitulos();
        $total        = count($capitulos);
        $articuloInicio = 1;
        $bloques      = [];

        // Clasificar los capítulos del original por TEMA (no por número): un RIT de experto
        // numera distinto y un tema canónico puede abarcar varios capítulos del original
        // (p. ej. "Vacaciones" + "Permisos"). Lo no-canónico (teletrabajo, migrantes…) queda
        // como 'extra' y se preserva al final. Así no se pierde ni se duplica contenido.
        $clasificado    = $this->clasificarCapitulosOriginales($capitulosOriginales);
        $capitulosExtra = $clasificado['__extra__'] ?? [];

        foreach ($capitulos as $idx => $cap) {
            $capOriginal         = $clasificado[$cap['numero']] ?? '';
            $hallazgos           = $this->hallazgosParaCapitulo($cap, $secciones);
            $codigosObligatorios = $cap['codigos_obligatorios'] ?? [];

            // Paridad con el generador: RAG a 8 fragmentos
            $rag = $this->biblioteca->buscarFragmentos($cap['query_rag'], limite: 8, umbral: 0.30) ?? '';

            // Paridad con el generador: obligatorios por código + búsqueda por tema
            $articulosLegales = $this->ritGenerator->obtenerArticulosObligatorios($codigosObligatorios);
            $articulosPorTema = $this->ritGenerator->buscarArticulosPorTema(
                $cap['query_rag'],
                $codigosObligatorios,   // excluir los ya obtenidos por código exacto
                8
            );
            $articulosLegales = trim($articulosLegales . ($articulosPorTema ? "\n\n" . $articulosPorTema : ''));

            // Paridad con el generador: contexto completo de la empresa
            $contextoEmpresa = $this->ritGenerator->construirContextoEmpresa($cap, $respuestas, $empresa);

            $prompt = $this->construirPromptMejoraCapitulo(
                $cap,
                $capOriginal,
                $hallazgos,
                $articulosLegales,
                $rag,
                $articuloInicio,
                $empresa->nombre_completo,
                $contextoEmpresa,
            );

            $textoCap = $this->llamarGemini($prompt, $empresa->id);

            // Paridad con el generador: validación + un reintento si el capítulo sale inválido
            if (!$this->ritGenerator->validarCapitulo($textoCap, $cap['titulo'])) {
                Log::warning('RITMejoradoService: capítulo inválido, reintentando', [
                    'empresa_id' => $empresa->id,
                    'capitulo'   => $cap['numero'],
                ]);
                $textoCap = $this->llamarGemini($prompt, $empresa->id);
            }

            $bloques[]      = trim($textoCap);
            $articuloInicio += max(1, preg_match_all('/^ARTÍCULO\s+\d+/m', $textoCap));

            if ($onProgress) {
                $onProgress($idx + 1, $total, $cap['titulo']);
            }
        }

        // Preservar los capítulos ADICIONALES del RIT original que no encajan en los 16
        // canónicos (p. ej. teletrabajo, trabajadores migrantes, cláusulas ineficaces): se
        // conservan VERBATIM al final para NO perder el trabajo del experto ni inventar nada.
        $extra = $this->preservarCapitulosAdicionales($capitulosExtra, count($capitulos), $articuloInicio);
        if ($extra !== '') {
            $bloques[] = $extra;
        }

        return implode("\n\n", $bloques);
    }

    // ── Helpers privados ─────────────────────────────────────────────────────

    /**
     * Extrae el texto del RIT a partir de la auditoría.
     * Prioriza texto_auditado; fallback al reglamento vinculado.
     */
    private function obtenerTextoOriginal(AuditoriaRIT $auditoria): string
    {
        $texto = trim($auditoria->texto_auditado ?? '');
        if (empty($texto)) {
            $texto = trim($auditoria->reglamento?->texto_completo ?? '');
        }
        if (empty($texto)) {
            Log::error('RITMejoradoService: texto vacío', [
                'auditoria_id' => $auditoria->id,
            ]);
            throw new \RuntimeException('No hay texto del RIT disponible para generar la versión mejorada.');
        }
        return $texto;
    }

    /**
     * Divide el texto del RIT en bloques por capítulo.
     * Retorna array ['I' => 'CAPÍTULO I\n...', 'II' => ..., ...]
     *
     * Tolera formatos heterogéneos de RITs subidos manualmente:
     *   - Con o sin tilde: "CAPÍTULO" / "CAPITULO"
     *   - Romanos (I, II), arábigos (1, 2) u ordinales en palabra (PRIMERO, SEGUNDO)
     *   - Cualquier mayúscula/minúscula y separadores posteriores (-, -, ., :)
     * Normaliza siempre la clave al número romano que usa getCapitulos().
     */
    private function parsearCapitulos(string $texto): array
    {
        // Ordinales en palabra reconocidos (evita partir en "CAPÍTULO DE DISPOSICIONES...")
        $ordinales = 'PRIMERO|SEGUNDO|TERCERO|CUARTO|QUINTO|SEXTO|S[EÉ]PTIMO|OCTAVO|NOVENO'
            . '|D[EÉ]CIMO|UND[EÉ]CIMO|DUOD[EÉ]CIMO'
            . '|DECIMOPRIMERO|DECIMOSEGUNDO|DECIMOTERCERO|DECIMOCUARTO|DECIMOQUINTO|DECIMOSEXTO';

        // Encabezado: CAP[IÍ]TULO + (romano | arábigo | ordinal reconocido)
        $token  = "(?:[IVXLCDM]+|\d+|{$ordinales})";
        $patron = "/(?=^\s*CAP[IÍ]TULO\s+{$token}\b)/imu";
        $partes = preg_split($patron, $texto, -1, PREG_SPLIT_NO_EMPTY);

        $capitulos = [];
        foreach ($partes as $bloque) {
            if (preg_match("/^\s*CAP[IÍ]TULO\s+({$token})\b/imu", $bloque, $m)) {
                $romano = $this->normalizarNumeroCapitulo($m[1]);
                if ($romano !== null && !isset($capitulos[$romano])) {
                    $capitulos[$romano] = trim($bloque);
                }
            }
        }

        return $capitulos;
    }

    /**
     * Diccionario TEMÁTICO: frases clave que identifican cada capítulo canónico dentro del
     * encabezado de un capítulo del RIT original. Se usa para clasificar por tema (no por número).
     * Frases pensadas para no solaparse entre temas afines.
     */
    private const TEMAS_CAPITULO = [
        'I'    => ['denominacion', 'domicilio', 'objeto', 'ambito de aplicacion', 'disposiciones generales'],
        'II'   => ['admision', 'ingreso', 'condiciones de admision', 'periodo de prueba', 'vinculacion', 'contratacion'],
        'III'  => ['horario', 'jornada'],
        'IV'   => ['suplementario', 'horas extras', 'dominicales', 'festivos', 'nocturno', 'dias de descanso', 'descanso obligatorio', 'recargo'],
        'V'    => ['salario', 'remuneracion', 'sueldo', 'forma de pago', 'lugar, dias', 'periodos de pago'],
        'VI'   => ['vacaciones'],
        'VII'  => ['permisos', 'licencias', 'maternidad', 'paternidad', 'luto', 'calamidad'],
        'VIII' => ['clasificacion de faltas'],
        'IX'   => ['sanciones', 'escala de faltas', 'escala de sanciones', 'multas', 'disciplinar'],
        'X'    => ['reclamos', 'quejas', 'peticiones', 'procedimiento de reclamo'],
        'XI'   => ['obligaciones especiales', 'prohibiciones especiales', 'prescripciones de orden', 'orden jerarquico', 'conducta', 'comportamiento'],
        'XII'  => ['riesgos laborales', 'seguridad y salud', 'salud en el trabajo', 'copasst', 'higiene'],
        'XIII' => ['uniformes', 'dotacion', 'equipos', 'bienes de la empresa', 'herramientas'],
        'XIV'  => ['convivencia', 'acoso', 'comite de convivencia'],
        'XV'   => ['proteccion de sujetos', 'sujetos de especial', 'mujeres y menores', 'labores prohibidas', 'discapacidad', 'fuero', 'estabilidad reforzada'],
        'XVI'  => ['disposiciones finales', 'publicacion y vigencia', 'clausulas ineficaces', 'vigencia del presente'],
    ];

    /**
     * Clasifica los capítulos del RIT original por TEMA (no por número). Un tema canónico puede
     * recibir VARIOS capítulos del original (p. ej. "Vacaciones" + "Permisos"); lo que no encaje
     * en ningún tema (teletrabajo, migrantes, trabajadores accidentales, cláusulas ineficaces…)
     * queda en '__extra__' para preservarse. Devuelve:
     *   ['I'=>textoConcatenado, ..., 'XVI'=>..., '__extra__'=>[romano=>texto, ...]]
     */
    private function clasificarCapitulosOriginales(array $capitulosOriginales): array
    {
        $resultado = ['__extra__' => []];

        foreach ($capitulosOriginales as $rom => $texto) {
            // Clasificar SOLO por el título del capítulo (líneas 1-2), nunca por el cuerpo:
            // usar el cuerpo mete falsos positivos (p. ej. "salario" aparece en Prohibiciones).
            $lineas = preg_split('/\r?\n/', trim($texto));
            $head   = $this->normalizar(implode(' ', array_slice($lineas, 0, 2)));

            $mejor = null;
            $mejorScore = 0;
            foreach (self::TEMAS_CAPITULO as $numero => $frases) {
                $score = 0;
                foreach ($frases as $frase) {
                    if (str_contains($head, $frase)) {
                        $score++;
                    }
                }
                if ($score > $mejorScore) {
                    $mejorScore = $score;
                    $mejor      = $numero;
                }
            }

            if ($mejor !== null && $mejorScore >= 1) {
                // Quitar el encabezado "CAPÍTULO X / TÍTULO" del texto fuente para que el
                // modelo NO lo repita y genere capítulos/numeración fantasma en la salida.
                $resultado[$mejor] = trim(($resultado[$mejor] ?? '') . "\n\n" . $this->cuerpoSinEncabezado($texto));
            } else {
                $resultado['__extra__'][$rom] = trim($texto);
            }
        }

        return $resultado;
    }

    /**
     * Devuelve el cuerpo de un capítulo del original SIN su encabezado (línea "CAPÍTULO X" y
     * la línea de título). Evita que ese encabezado se cuele como texto fuente y el modelo lo
     * reproduzca creando capítulos duplicados.
     */
    private function cuerpoSinEncabezado(string $texto): string
    {
        $lineas = preg_split('/\r?\n/', trim($texto));
        $drop   = 1; // quita "CAPÍTULO X"
        if (isset($lineas[1]) && trim($lineas[1]) !== '' && !preg_match('/^\s*ART[IÍ]CULO/i', $lineas[1])) {
            $drop = 2; // quita también la línea de título
        }
        return trim(implode("\n", array_slice($lineas, $drop)));
    }

    /** Minúsculas sin acentos, para comparar temas de forma robusta. */
    private function normalizar(string $texto): string
    {
        $texto = mb_strtolower($texto);
        return strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    }

    /**
     * Preserva VERBATIM los capítulos del original que no encajan en ningún tema canónico
     * (teletrabajo, migrantes, cláusulas ineficaces, etc.). Los renumera como CAPÍTULO XVII,
     * XVIII… y renumera sus artículos de forma consecutiva. NO llama a la IA → cero riesgo de
     * alucinación y no se pierde el trabajo del abogado experto.
     */
    private function preservarCapitulosAdicionales(array $extra, int $totalCanonicos, int $articuloInicio): string
    {
        if (empty($extra)) {
            return '';
        }

        $bloques   = [];
        $numeroCap = $totalCanonicos; // los canónicos ocupan I..XVI
        $art       = $articuloInicio;

        foreach ($extra as $texto) {
            $texto = trim($texto);
            if ($texto === '' || !preg_match('/ART[IÍ]CULO\s+\d+/iu', $texto)) {
                continue; // saltar capítulos vacíos o sin articulado real
            }
            $numeroCap++;
            $romNuevo = $this->intentarArabigoARomano($numeroCap) ?? (string) $numeroCap;

            $lineas = preg_split('/\r?\n/', $texto);
            $titulo = '';
            if (isset($lineas[1]) && trim($lineas[1]) !== '' && !preg_match('/^\s*ART[IÍ]CULO/i', $lineas[1])) {
                $titulo = trim($lineas[1]);
            }
            $cuerpo = trim(implode("\n", array_slice($lineas, ($titulo !== '') ? 2 : 1)));

            // Renumerar los artículos consecutivamente (conserva el texto íntegro del experto).
            $cuerpo = preg_replace_callback('/ART[IÍ]CULO\s+\d+/iu', function () use (&$art) {
                return 'ARTÍCULO ' . $art++;
            }, $cuerpo);

            $encabezado = "CAPÍTULO {$romNuevo}\n" . ($titulo !== '' ? $titulo : 'DISPOSICIONES COMPLEMENTARIAS');
            $bloques[]  = $encabezado . "\n" . $cuerpo;
        }

        return implode("\n\n", $bloques);
    }

    /**
     * Normaliza el identificador de un capítulo a número romano (I..XVI).
     * Acepta romanos ('IV'), arábigos ('4') y ordinales en palabra ('CUARTO').
     * Retorna null si no se reconoce.
     */
    private function normalizarNumeroCapitulo(string $raw): ?string
    {
        $raw = trim(mb_strtoupper($raw));

        // Ya es romano válido
        if (preg_match('/^[IVXLCDM]+$/', $raw)) {
            return $raw;
        }

        // Arábigo → romano
        if (ctype_digit($raw)) {
            return $this->intentarArabigoARomano((int) $raw);
        }

        // Ordinal en palabra → romano
        $ordinales = [
            'PRIMERO' => 1,
            'SEGUNDO' => 2,
            'TERCERO' => 3,
            'CUARTO' => 4,
            'QUINTO' => 5,
            'SEXTO' => 6,
            'SÉPTIMO' => 7,
            'SEPTIMO' => 7,
            'OCTAVO' => 8,
            'NOVENO' => 9,
            'DÉCIMO' => 10,
            'DECIMO' => 10,
            'UNDÉCIMO' => 11,
            'UNDECIMO' => 11,
            'DECIMOPRIMERO' => 11,
            'DUODÉCIMO' => 12,
            'DUODECIMO' => 12,
            'DECIMOSEGUNDO' => 12,
            'DECIMOTERCERO' => 13,
            'DECIMOCUARTO' => 14,
            'DECIMOQUINTO' => 15,
            'DECIMOSEXTO' => 16,
        ];

        return isset($ordinales[$raw]) ? $this->intentarArabigoARomano($ordinales[$raw]) : null;
    }

    /** Convierte un entero 1..16 a número romano. */
    private function intentarArabigoARomano(int $n): ?string
    {
        if ($n < 1 || $n > 39) {
            return null;
        }
        $mapa = [
            10 => 'X',
            9 => 'IX',
            5 => 'V',
            4 => 'IV',
            1 => 'I',
        ];
        $romano = '';
        foreach ($mapa as $valor => $simbolo) {
            while ($n >= $valor) {
                $romano .= $simbolo;
                $n -= $valor;
            }
        }
        return $romano;
    }

    /**
     * Extrae y formatea los hallazgos de auditoría relevantes para un capítulo.
     */
    private function hallazgosParaCapitulo(array $cap, array $secciones): string
    {
        $claves = self::CAPITULO_A_SECCION[$cap['numero']] ?? [];
        $lineas = [];

        foreach ($claves as $clave) {
            $seccion = $secciones[$clave] ?? null;
            if (!$seccion) {
                continue;
            }

            $score     = $seccion['score'] ?? 100;
            $titulo    = $seccion['titulo'] ?? $clave;
            $hallazgos = $seccion['hallazgos'] ?? [];
            $recs      = $seccion['recomendaciones'] ?? [];

            if ($score >= 100 && empty($hallazgos) && empty($recs)) {
                continue;
            }

            $lineas[] = "Sección auditada: {$titulo} (puntuación: {$score}/100)";
            foreach ($hallazgos as $h) {
                $lineas[] = "  - HALLAZGO: {$h}";
            }
            foreach ($recs as $r) {
                $lineas[] = "  - CORRECCIÓN REQUERIDA: {$r}";
            }
        }

        return empty($lineas)
            ? 'Este capítulo no presentó hallazgos en la auditoría. Mantén la estructura, mejora la redacción si está incompleta.'
            : implode("\n", $lineas);
    }

    /**
     * Construye el prompt de mejora para un capítulo concreto.
     * REGLA: ningún artículo, ley ni porcentaje se hardcodea aquí;
     *        todo contenido legal viene de $articulosLegales o $rag.
     */
    private function construirPromptMejoraCapitulo(
        array  $cap,
        string $capituloOriginal,
        string $hallazgos,
        string $articulosLegales,
        string $rag,
        int    $articuloInicio,
        string $razonSocial,
        string $contextoEmpresa = '',
    ): string {
        $numero      = $cap['numero'];
        $titulo      = $cap['titulo'];
        $instrucciones = $cap['instrucciones'];

        // Estándar de oro: elementos obligatorios que este capítulo debe cubrir (contenido curado).
        $goldItems  = \App\Support\RitGoldStandard::paraCapitulo($numero);
        $goldBloque = $goldItems
            ? "\nELEMENTOS OBLIGATORIOS QUE DEBE CUBRIR ESTE CAPÍTULO (verifica que todos estén presentes; si el original omite alguno, agrégalo):\n"
            . \App\Support\RitGoldStandard::comoLista($goldItems) . "\n"
            : '';

        $seccionArticulos = $articulosLegales
            ? "\nTEXTO OFICIAL DE ARTÍCULOS DEL CST (fuente: base de datos interna - ÚNICA fuente válida para citas):\n"
            . $articulosLegales . "\n"
            : '';

        $seccionRag = $rag
            ? "\nFRAGMENTOS DE LA BIBLIOTECA JURÍDICA (fuente autorizada para citas adicionales):\n"
            . $rag . "\n"
            : '';

        $seccionContexto = $contextoEmpresa
            ? "\nDATOS REALES DE LA EMPRESA (úsalos en la redacción; NUNCA uses corchetes ni placeholders):\n"
            . $contextoEmpresa . "\n"
            : '';

        $seccionOriginal = $capituloOriginal
            ? "\nCAPÍTULO ORIGINAL A MEJORAR:\n" . $capituloOriginal . "\n"
            : "\nNo se encontró el capítulo original. Redáctalo desde cero siguiendo las instrucciones de contenido.\n";

        return <<<PROMPT
Eres un abogado laboral colombiano experto en Reglamentos Internos de Trabajo. Tu objetivo es
producir un capítulo IGUAL O SUPERIOR al de un abogado experto: más completo, más preciso y
totalmente conforme a la normativa vigente - nunca más pobre ni más corto.

TAREA: Perfeccionar el CAPÍTULO {$numero} ({$titulo}) del RIT de "{$razonSocial}".

REGLA FUNDAMENTAL - CITAS LEGALES (CERO ALUCINACIÓN):
- Números de artículo, nombres de ley, porcentajes y plazos legales: SOLO los que aparezcan
  textualmente en el contexto jurídico proporcionado más abajo.
- PROHIBIDO inventar o recordar artículos, leyes, porcentajes o plazos de tu entrenamiento.
- Si el contexto jurídico no trae una cifra o referencia, redacta el concepto sin citar fuente.

REGLA DE PRESERVACIÓN (NO EMPOBRECER EL TRABAJO DEL EXPERTO):
- CONSERVA todas las cláusulas, derechos, garantías, definiciones y detalles específicos que
  ya trae el capítulo original y que sean legales. NO elimines contenido. NO lo resumas.
- El resultado debe ser IGUAL O MÁS EXTENSO Y COMPLETO que el capítulo original.
- Solo puedes: (a) corregir los hallazgos de la auditoría, (b) actualizar lo que contradiga la
  normativa vigente proporcionada, (c) añadir lo obligatorio que falte, (d) mejorar la redacción.
- Si el original y la norma vigente coinciden, mantén la redacción del original.
{$seccionArticulos}{$seccionRag}{$seccionContexto}
HALLAZGOS DE LA AUDITORÍA PARA ESTE CAPÍTULO (CORRÍGELOS TODOS):
{$hallazgos}
{$seccionOriginal}
INSTRUCCIONES DE CONTENIDO PARA ESTE CAPÍTULO (mínimos obligatorios; puedes superarlos, nunca quedarte corto):
{$instrucciones}
{$goldBloque}
INSTRUCCIONES DE FORMATO:
- Los artículos de este capítulo se numeran desde ARTÍCULO {$articuloInicio}.
- Primera línea del capítulo: CAPÍTULO {$numero}
- Segunda línea: {$titulo}
- Cada artículo: párrafo completo de mínimo 60 palabras en su propia línea.
- Usa los datos reales de la empresa cuando el artículo lo requiera; NUNCA uses corchetes ni placeholders.
- NUNCA uses guiones (-), asteriscos (*) ni almohadillas (#) al inicio de línea.
- Para listas internas usa: "1) texto 2) texto" en líneas separadas.
- TABLAS cuando aplique: TABLA: / ENCABEZADO: col1 | col2 / FILA: v1 | v2 / FIN_TABLA

Devuelve ÚNICAMENTE el texto del capítulo mejorado, sin comentarios ni explicaciones.
PROMPT;
    }

    /**
     * Llama a Gemini con cascade flash → flash-lite.
     * Misma lógica que RITGeneratorService::llamarGemini().
     */
    private function llamarGemini(string $prompt, int $empresaId = 0): string
    {
        $config = config('services.ia.gemini', []);
        $apiKey = $config['api_key'] ?? '';

        // Limpiar bytes UTF-8 inválidos
        $prompt = preg_replace(
            '/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u',
            '',
            $prompt
        ) ?? iconv('UTF-8', 'UTF-8//IGNORE', $prompt);

        $modelos = [
            'gemini-2.5-flash'      => ['budget' => 0,    'timeout' => 120],
            'gemini-2.5-flash-lite' => ['budget' => 0,    'timeout' => 90],
        ];
        $lastError = '';

        foreach ($modelos as $model => $cfg) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

            Log::info('RITMejoradoService: llamando Gemini por capítulo', [
                'model'      => $model,
                'empresa_id' => $empresaId,
            ]);

            $payload = [
                'contents'         => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => [
                    'temperature'     => 0.3,
                    'maxOutputTokens' => 32768,
                    'topP'            => 0.95,
                    'thinkingConfig'  => ['thinkingBudget' => $cfg['budget']],
                ],
            ];

            for ($intento = 1; $intento <= 2; $intento++) {
                try {
                    $response = Http::withHeaders(['Content-Type' => 'application/json'])
                        ->timeout($cfg['timeout'])
                        ->post($url, $payload);
                } catch (\Illuminate\Http\Client\ConnectionException $ce) {
                    Log::warning('RITMejoradoService: timeout, cascade al siguiente modelo', [
                        'model' => $model,
                        'error' => $ce->getMessage(),
                    ]);
                    break;
                }

                if ($response->successful()) {
                    $parts = $response->json('candidates.0.content.parts', []);
                    $texto = '';
                    foreach (array_reverse($parts) as $part) {
                        if (empty($part['thought']) && !empty($part['text'])) {
                            $texto = $part['text'];
                            break;
                        }
                    }
                    if (empty($texto)) {
                        $texto = $parts[0]['text'] ?? '';
                    }
                    if (!empty($texto)) {
                        $this->modeloUsado = $model;
                        return trim($texto);
                    }
                    break;
                }

                $status    = $response->status();
                $lastError = $response->body();

                if ($status === 400 && str_contains($lastError, 'thinking')) {
                    break; // cascade al siguiente modelo
                }
                if (in_array($status, [429, 503]) && $intento < 2) {
                    sleep(15);
                } elseif (in_array($status, [500, 502, 504]) && $intento < 2) {
                    sleep(10);
                } else {
                    break;
                }
            }
        }

        throw new \RuntimeException('Error en API Gemini (todos los modelos intentados): ' . $lastError);
    }

    /**
     * Genera el PDF permanente y lo guarda en storage/app/private/.
     */
    private function generarPDFPermanente(
        string $textoMejorado,
        \App\Models\Empresa $empresa,
        int $ritId,
        int $version
    ): ?string {
        try {
            $tmpPath = $this->ritGenerator->generarPDFTemp($textoMejorado, $empresa);

            $directorio   = "private/rits/{$empresa->id}";
            $rutaRelativa = "{$directorio}/rit_v{$version}_{$ritId}.pdf";

            Storage::makeDirectory($directorio);
            Storage::put($rutaRelativa, file_get_contents($tmpPath));
            @unlink($tmpPath);

            return $rutaRelativa;
        } catch (\Throwable $e) {
            Log::warning('RITMejoradoService: no se pudo generar PDF permanente', [
                'empresa_id' => $empresa->id,
                'rit_id'     => $ritId,
                'error'      => $e->getMessage(),
            ]);
            return null;
        }
    }
}
