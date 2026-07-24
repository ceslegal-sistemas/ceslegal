<?php

namespace App\Services;

use App\Models\DiligenciaDescargo;
use App\Models\PreguntaDescargo;
use App\Models\ProcesoDisciplinario;
use App\Models\RespuestaDescargo;
use App\Models\TrazabilidadIADescargo;
use App\Models\ArticuloLegal;
use App\Services\BibliotecaLegalService;
use App\Services\GeminiCircuitBreaker;
use App\Services\ReglamentoInternoService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IADescargoService
{
    protected string $provider;
    protected array $config;

    // Control de timeout y reintentos - se ajustan según el modo de uso
    protected int $timeoutSegundos  = 20;  // para generación en batch
    protected int $maxReintentos    = 2;   // para generación en batch
    protected int $maxSalidaTokens  = 0;   // 0 = usar config; se sobreescribe en modo realtime

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

        // Modo realtime: con 2 modelos, 25s cada uno = 50s máx.
        $this->timeoutSegundos = 25;
        $this->maxReintentos   = 0;

        $preguntasDisponibles = self::LIMITE_MAXIMO_PREGUNTAS - $totalPreguntasActuales;
        $contexto = $this->construirContexto($diligencia);
        $contexto['preguntas_disponibles'] = $preguntasDisponibles;

        // ── AGENTES DEL JEFE: Director Estratégico →
        // Generador de Preguntas → Evaluador de Suficiencia. Reemplaza el prompt único
        // anterior (construirPromptGeneracionPreguntas(), comentado más abajo, NO
        // borrado, para volver fácil) por 3 llamadas encadenadas: el Director decide
        // QUÉ objetivo falta y SI corresponde preguntar (sin redactar nada), el
        // Generador redacta la pregunta SOLO para ese objetivo, y el Evaluador de
        // Suficiencia hace de segunda opinión antes de guardarla - si cualquiera de
        // los dos agentes dice que ya no hace falta preguntar, se respeta esa señal.
        try {
            $t0Gemini = microtime(true);
            Log::channel('descargos')->info('[IA] Director Estratégico INICIO', [
                'diligencia_id' => $diligencia->id,
                'pregunta_id'   => $preguntaRespondida->id,
                'total_preguntas_actuales' => $totalPreguntasActuales,
            ]);

            $promptDirector = $this->construirPromptDirectorEstrategico($contexto, $preguntaRespondida, $respuesta);
            $respuestaDirector = $this->llamarIA($promptDirector);
            $this->registrarTrazabilidad($diligencia->id, $promptDirector, $respuestaDirector, 'director_estrategico');
            $director = $this->parsearJsonIA($respuestaDirector);

            Log::channel('descargos')->info('[IA] Director Estratégico OK', [
                'diligencia_id' => $diligencia->id,
                'pregunta_id'   => $preguntaRespondida->id,
                'decision'      => $director,
                'ms'            => round((microtime(true) - $t0Gemini) * 1000),
            ]);

            // Esquema literal del Director (ver construirPromptDirectorEstrategico): autoriza
            // preguntas únicamente vía "continuar" + que queden objetivos_pendientes (ETAPA 6
            // del prompt: "si no existe ningún objetivo pendiente: continuar=false").
            $continuar = $director['continuar'] ?? false;
            $objetivosPendientes = is_array($director['objetivos_pendientes'] ?? null)
                ? $director['objetivos_pendientes']
                : [];

            if (empty($director) || !$continuar || empty($objetivosPendientes)) {
                return [];
            }

            // Generador de Preguntas: redacta ÚNICAMENTE el objetivo que indicó el Director.
            $promptGenerador = $this->construirPromptGeneradorPreguntas($contexto, $director, $preguntaRespondida, $respuesta);
            $respuestaGenerador = $this->llamarIA($promptGenerador);
            $this->registrarTrazabilidad($diligencia->id, $promptGenerador, $respuestaGenerador, 'generador_preguntas');

            if (str_contains($respuestaGenerador, 'NO_REQUIERE')) {
                return [];
            }

            $preguntaTexto = trim((string) ($this->parsearJsonIA($respuestaGenerador)['pregunta'] ?? ''));
            if ($preguntaTexto === '' || mb_strlen($preguntaTexto) < 10) {
                return [];
            }

            // Evaluador de Suficiencia: segunda opinión independiente antes de guardar -
            // si considera el expediente ya suficiente, se descarta la pregunta aunque
            // el Director y el Generador ya la hayan producido.
            $promptEvaluador = $this->construirPromptEvaluadorSuficiencia($contexto, $director);
            $respuestaEvaluador = $this->llamarIA($promptEvaluador);
            $this->registrarTrazabilidad($diligencia->id, $promptEvaluador, $respuestaEvaluador, 'evaluador_suficiencia');
            $evaluador = $this->parsearJsonIA($respuestaEvaluador);

            if (($evaluador['expediente_suficiente'] ?? false) === true || ($evaluador['continuar'] ?? true) === false) {
                Log::channel('descargos')->info('[IA] Evaluador de Suficiencia descartó la pregunta', [
                    'diligencia_id' => $diligencia->id,
                    'pregunta_id'   => $preguntaRespondida->id,
                    'evaluacion'    => $evaluador,
                ]);
                return [];
            }

            return $this->guardarNuevasPreguntas($diligencia, [$preguntaTexto], $preguntaRespondida->id);
        } catch (\Exception $e) {
            Log::channel('descargos')->error('[IA] ERROR en pipeline de agentes', [
                'diligencia_id' => $diligencia->id,
                'pregunta_id'   => $preguntaRespondida->id,
                'error'         => $e->getMessage(),
                'ms'            => isset($t0Gemini) ? round((microtime(true) - $t0Gemini) * 1000) : null,
            ]);
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

        // Preguntas YA RESPONDIDAS - historial completo para contexto íntegro
        $preguntasYRespuestas = $diligencia->preguntas()
            ->with('respuesta')
            ->activas()
            ->whereHas('respuesta')
            ->get()
            ->map(function ($pregunta) {
                return [
                    'pregunta' => $pregunta->pregunta,
                    'respuesta' => $pregunta->respuesta->respuesta ?? '',
                    'es_ia'     => $pregunta->es_generada_por_ia,
                ];
            })
            ->toArray();

        $totalRespondidas = count($preguntasYRespuestas);

        // Detección PHP de ancla episódica ya cubierta:
        // si alguna respuesta menciona hora, lugar específico o personas presentes,
        // el tipo de ancla está cubierto - evitamos pedirle al modelo que lo infiera.
        $anclaYaCubierta = false;
        $patronAncla = '/\b(\d{1,2}:\d{2}|\d{1,2}\s*[aApP][mM]|hora|piso|sala|área|pasillo|oficina'
            . '|estaba haciendo|estaba realizando|quién estaba|personas presentes'
            . '|nombre de quien|me encontraba|estaba en)\b/iu';
        foreach ($preguntasYRespuestas as $pr) {
            if (preg_match($patronAncla, $pr['respuesta'])) {
                $anclaYaCubierta = true;
                break;
            }
        }

        // Preguntas pendientes (sin respuesta) - la IA NO debe regenerarlas
        $preguntasPendientes = $diligencia->preguntas()
            ->activas()
            ->whereDoesntHave('respuesta')
            ->pluck('pregunta')
            ->toArray();

        // Lista completa de preguntas para anti-repetición secundaria
        $todasLasPreguntas = $diligencia->preguntas()
            ->activas()
            ->pluck('pregunta')
            ->toArray();

        // RIT completo y normas RAG en cada llamada para máximo contexto legal
        $ritContexto = $empresaId ? $this->obtenerContextoRIT($empresaId) : '';
        $normasRag   = $this->buscarNormasRelevantes($proceso->hechos ?? '', $empresaId, limite: 3, proceso: $proceso);

        return [
            'hechos'              => $proceso->hechos,
            'articulos_legales'   => $articulosLegales,
            'preguntas_respuestas'=> $preguntasYRespuestas,
            'todas_las_preguntas'  => $todasLasPreguntas,
            'preguntas_pendientes' => $preguntasPendientes,
            'trabajador'          => $proceso->trabajador->nombre_completo,
            'cargo'               => $proceso->trabajador->cargo,
            'empresa'             => $proceso->empresa?->nombre_completo ?? 'la empresa que lo cita',
            'rit_contexto'        => $ritContexto,
            'normas_rag'          => $normasRag,
            'ancla_cubierta'      => $anclaYaCubierta,
            'total_respondidas'   => $totalRespondidas,
        ];
    }

    /*
     * ── PROMPT ANTERIOR (comentado, NO borrado, para volver fácil) ─────────────
     * Generaba las preguntas dinámicas en UNA sola llamada (decidía estrategia y
     * redactaba la pregunta al mismo tiempo). Reemplazado por el pipeline de 3
     * agentes del jefe (Director Estratégico -> Generador de Preguntas ->
     * Evaluador de Suficiencia, en generarPreguntasDinamicas().
     * Para volver: descomentar este método y restaurar su llamada en
     * generarPreguntasDinamicas() (reemplazar el bloque de 3 agentes por la llamada
     * directa a este método + parsearRespuestaIA()).
     *
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
            : "\nADVERTENCIA: ESTA EMPRESA NO TIENE REGLAMENTO INTERNO DE TRABAJO (RIT) REGISTRADO.\n"
              . "ESTÁ PROHIBIDO generar preguntas sobre si el trabajador conocía el reglamento, las políticas internas o cualquier RIT.\n";
        $normasBloque = !empty($contexto['normas_rag'])
            ? "\nNORMAS LEGALES RECUPERADAS (RIT, CST, jurisprudencia - cita solo estas):\n{$contexto['normas_rag']}\n"
            : '';

        $disponibles      = $contexto['preguntas_disponibles'] ?? 10;
        $totalRespondidas = $contexto['total_respondidas'] ?? 0;
        $anclaYaCubierta  = $contexto['ancla_cubierta'] ?? false;

        $notaLimite = $disponibles <= 4
            ? "\nNOTA: ESPACIO REDUCIDO: Solo puedes añadir {$disponibles} pregunta(s) más. Prioriza las más críticas para el expediente.\n"
            : '';

        // Umbral progresivo: con muchas preguntas respondidas, el criterio para generar
        // más debe ser mucho más estricto para evitar repetición y fatiga del trabajador
        $notaUmbral = '';
        if ($totalRespondidas >= 10) {
            $notaUmbral = "\nADVERTENCIA: YA HAY {$totalRespondidas} PREGUNTAS RESPONDIDAS. El expediente está muy avanzado.\n"
                . "SOLO genera una nueva pregunta si detectas una laguna ESPECÍFICA e INEQUÍVOCA (un hecho concreto sin documentar).\n"
                . "Ante cualquier duda, retorna NO_REQUIERE.\n";
        } elseif ($totalRespondidas >= 7) {
            $notaUmbral = "\nNOTA: Con {$totalRespondidas} preguntas respondidas, el expediente está bien documentado.\n"
                . "Solo genera preguntas para lagunas concretas y verificables. Prefiere NO_REQUIERE si no hay una laguna clara.\n";
        }

        // Ancla episódica: si PHP ya detectó que está cubierta, omitir la instrucción
        // (evita que el modelo genere preguntas de hora/lugar que ya fueron respondidas)
        if ($anclaYaCubierta) {
            $anclaInstruccion = "ANCLA YA CUBIERTA - el historial contiene al menos una respuesta con hora, "
                . "lugar específico o personas presentes. NO es necesario ni permitido generar "
                . "más preguntas de este tipo. Omite completamente este aspecto.";
        } else {
            $anclaInstruccion = "Una IA generativa puede inventar respuestas plausibles sobre hechos generales, "
                . "pero no puede saber detalles que solo conoce quien estuvo físicamente presente.\n"
                . "Si ninguna respuesta del historial menciona datos episódicos concretos, formula UNA "
                . "pregunta (contada dentro del máximo de 2) que requiera uno de estos tipos de detalle:\n"
                . "  → Tipo A: la hora o momento exacto del evento\n"
                . "  → Tipo B: quién más estaba presente y dónde se encontraba cada uno\n"
                . "  → Tipo C: las palabras textuales que usó alguien involucrado\n"
                . "  → Tipo D: el lugar físico concreto dentro de la empresa\n"
                . "  → Tipo E: qué herramienta, documento o sistema estaba usando el trabajador\n\n"
                . "REGLA: formula la pregunta adaptada a los HECHOS DEL CASO, no como pregunta genérica.\n"
                . "NO copies ni parafrasees estos tipos literalmente - derivan del contexto del proceso.";
        }

        // Preguntas pendientes - el trabajador las responderá próximamente, NO regenerar
        $pendientesText = '';
        foreach ($contexto['preguntas_pendientes'] ?? [] as $i => $p) {
            $pendientesText .= ($i + 1) . '. ' . $p . "\n";
        }
        if (empty($pendientesText)) {
            $pendientesText = '(ninguna - todas las preguntas anteriores ya fueron respondidas)';
        }

        // Lista completa de todas las preguntas del formulario (para anti-repetición secundaria)
        $todasText = '';
        foreach ($contexto['todas_las_preguntas'] as $i => $p) {
            $todasText .= ($i + 1) . '. ' . $p . "\n";
        }

        return <<<PROMPT
Eres un abogado especialista en derecho laboral colombiano conduciendo una diligencia de descargos.
Tu misión es construir un expediente disciplinario COMPLETO que permita a la empresa tomar una
decisión fundamentada (llamado de atención, suspensión o terminación del contrato) con respaldo
jurídico sólido conforme al Art. 29 C.P. y al Art. 115 CST (Ley 2466 de 2025).
Fundamento: Sentencia T-239/2021, SL1861-2024, C-1270/2000.

════════════════════════════════════════════════════════
PRINCIPIOS IRRENUNCIABLES
════════════════════════════════════════════════════════
• Presunción de inocencia - los hechos son PRESUNTOS.
• Derecho a la defensa - el trabajador debe poder explicar su versión completamente.
• Dignidad humana - ninguna pregunta puede intimidar ni humillar.
• Imparcialidad - se recoge información objetiva, no se asume culpabilidad.

════════════════════════════════════════════════════════
CONTEXTO DEL PROCESO
════════════════════════════════════════════════════════
Trabajador: {$contexto['trabajador']}
Cargo: {$contexto['cargo']}

════════════════════════════════════════════════════════
ANÁLISIS EXPERTO DEL CARGO - LEE ESTO ANTES DE CONTINUAR
════════════════════════════════════════════════════════
Eres también un AUDITOR EXPERTO en el cargo "{$contexto['cargo']}".
Conoces con detalle qué hace alguien en este rol: sus funciones propias, los procedimientos
que debe seguir, los estándares de conducta esperados, a quién reporta, qué decisiones puede
tomar y cuáles requieren autorización, y qué consecuencias tienen sus acciones u omisiones.

ANTES de formular cualquier pregunta, resuelve mentalmente estas tres preguntas:
  A) ¿Qué funciones o responsabilidades concretas del cargo "{$contexto['cargo']}" son
     DIRECTAMENTE relevantes para los hechos descritos?
  B) ¿Qué debería haber hecho correctamente alguien en ese cargo para prevenir,
     manejar o reportar adecuadamente la situación?
  C) ¿Qué parte de esas obligaciones profesionales pudo haber incumplido el trabajador
     según los hechos presuntos?

REGLA DE ORO: Cada pregunta que generes debe partir de este análisis profesional del cargo.
No hagas preguntas genéricas ni preguntas sobre responsabilidades del cargo que no tengan
relación directa con los hechos. Si los hechos no tocan una función del cargo, no preguntes
sobre ella. El objetivo es determinar si el trabajador cumplió o no con sus obligaciones
profesionales específicas, no hacer un cuestionario general de sus tareas.
DAS POR SABIDAS las funciones del cargo (las conoces como experto): EMBÉBELAS afirmándolas
dentro de la pregunta. JAMÁS le pidas al trabajador que te explique en qué consiste su cargo
ni qué tareas tiene - eso lo sabes tú; preguntarlo es un error grave.

Hechos presuntos (versión del empleador):
{$contexto['hechos']}

Artículos presuntamente incumplidos:
- {$articulosText}
{$ritBloque}{$normasBloque}
════════════════════════════════════════════════════════
LO QUE EL TRABAJADOR YA DECLARÓ
════════════════════════════════════════════════════════
{$preguntasRespuestasText}
ÚLTIMA PREGUNTA RESPONDIDA:
{$preguntaRespondida->pregunta}

RESPUESTA DEL TRABAJADOR:
{$respuesta->respuesta}

████████████████████████████████████████████████████████
PREGUNTAS EN COLA - YA ESTÁN PROGRAMADAS, NO LAS REPITAS
████████████████████████████████████████████████████████
Las siguientes preguntas ya están en el formulario esperando ser respondidas.
ESTÁ ABSOLUTAMENTE PROHIBIDO generar preguntas iguales o similares a estas.
Si generas una que cubre el mismo aspecto, es un ERROR GRAVE que arruina el expediente.

{$pendientesText}
════════════════════════════════════════════════════════
HISTORIAL COMPLETO DEL FORMULARIO (respondidas + pendientes - NO repetir ninguna)
════════════════════════════════════════════════════════
{$todasText}
████████████████████████████████████████████████████████
PATRONES DE PREGUNTA SIEMPRE PROHIBIDOS (aunque cambies las palabras)
████████████████████████████████████████████████████████

1. VERSIÓN DE LOS HECHOS - Si el trabajador ya narró qué pasó (aunque sea brevemente),
   está CUBIERTO. NUNCA vuelvas a pedir "su versión", "qué ocurrió", "describa el incidente",
   "relate los hechos" ni ninguna variante. Pedir la misma historia de nuevo viola el Art. 29 C.P.

2. PRUEBAS Y TESTIGOS - Si ya se preguntó sobre pruebas, documentos o testigos en cualquier
   forma anterior, está CUBIERTO. PROHIBIDO volver a preguntar sobre ello.

3. CONOCIMIENTO DE NORMAS/POLÍTICAS - Si ya se preguntó si el trabajador conocía reglas,
   políticas o el reglamento, está CUBIERTO. No repetir aunque uses otras palabras.

4. RESPUESTAS TAJANTES - Si el trabajador respondió "ya lo respondí", "ya lo expliqué",
   "no" de forma definitiva, o "sí" de forma definitiva (ej: "sí, yo lo hice", "sí, fue así",
   "reconozco que sucedió", "sí, es verdad"), ese tema está CERRADO. Una confirmación cierra
   la pregunta igual que una negación: NUNCA vuelvas a preguntar SI ocurrió el hecho o si fue
   el trabajador una vez ya lo confirmó. Sí puedes indagar en aspectos DISTINTOS (por qué,
   si hubo justificación o atenuantes), pero nunca repreguntes la confirmación misma.

5. FUNCIONES DEL PROPIO CARGO - NUNCA le preguntes al trabajador qué tareas, funciones o
   responsabilidades tiene su cargo "{$contexto['cargo']}". ESO YA LO SABES TÚ: eres auditor
   experto de ese cargo y conoces sus funciones. Preguntárselo revela que no conoces el rol,
   desperdicia una pregunta y debilita el expediente.
   → En su lugar, AFIRMA tú la función relevante (con tu conocimiento del cargo) y pregunta
     sobre el HECHO o la OMISIÓN concreta.
   PROHIBIDO:  "¿Qué tareas específicas le correspondía realizar como {$contexto['cargo']}?"
   CORRECTO:   "Como {$contexto['cargo']}, entre sus funciones está [función concreta del rol].
               En el momento de los hechos, ¿por qué no estaba realizando esa labor?"

════════════════════════════════════════════════════════
ASPECTOS QUE DEBE CUBRIR UN EXPEDIENTE DISCIPLINARIO COMPLETO
════════════════════════════════════════════════════════
Antes de generar una pregunta, verifica en "LO QUE EL TRABAJADOR YA DECLARÓ" si el aspecto
ya fue abordado. Si fue respondido (aunque sea brevemente), está CUBIERTO → no preguntes.

1. VERSIÓN COMPLETA - ¿El trabajador explicó qué pasó, cuándo y cómo? (una vez es suficiente)
2. PERSONAS INVOLUCRADAS - ¿Mencionó a otras personas? ¿Quedó claro el rol de cada una?
3. INTENCIONALIDAD - ¿Fue deliberado, accidental, por descuido, por instrucción de otro?
4. AUTORIZACIÓN O JUSTIFICACIÓN - ¿Tenía permiso, orden o causa justificada?
5. EVIDENCIA A FAVOR - ¿Tiene pruebas, testigos o documentos? (solo preguntar una vez)
6. IMPACTO Y CONSECUENCIAS - ¿Es consciente del efecto de sus actos en la empresa?
7. FACTORES ATENUANTES - ¿Hay circunstancias que expliquen (no justifiquen) lo ocurrido?
8. CONTRADICCIONES - ¿Hay puntos vagos o contradictorios que requieran aclaración ESPECÍFICA?
   (una contradicción específica, no pedir que repita todo de nuevo)
9. OBLIGACIONES DEL CARGO - Usando tu análisis experto del cargo "{$contexto['cargo']}":
   ¿Queda claro si el trabajador siguió el procedimiento correcto para su rol?
   ¿Se sabe si actuó dentro de sus atribuciones o tomó decisiones que no le correspondían?
   ¿Se conoce si reportó o escaló la situación como debía hacerlo según su cargo?
   (Solo aplica a los hechos. No preguntes sobre funciones del cargo no relacionadas con ellos.)

════════════════════════════════════════════════════════
ANCLA DE MEMORIA EPISÓDICA - CAPA ANTI-IA
════════════════════════════════════════════════════════
{$anclaInstruccion}

════════════════════════════════════════════════════════
TU TAREA
════════════════════════════════════════════════════════
PASO 1 - Lee el historial completo y marca cuáles de los 9 aspectos ya están cubiertos.
PASO 2 - Verifica si ya existe al menos una ancla episódica respondida o pendiente.
PASO 3 - De los aspectos NO cubiertos (y la ancla si falta), formula máximo 2 preguntas NUEVAS que:
✓ Ofrezcan información genuinamente nueva, no repetida en ninguna forma anterior.
✓ Sean específicas (un punto concreto), no generales ("cuénteme todo").
✓ Aporten valor real para la decisión disciplinaria.

CRITERIOS para NO incluir una pregunta:
✗ El aspecto ya fue abordado en cualquier declaración anterior (aunque el trabajador haya dado poco detalle).
✗ Ya existe en la lista del formulario una pregunta pendiente que lo cubre.
✗ La pregunta busca que el trabajador se autoincrimine.
✗ Es una reformulación de cualquier pregunta ya existente en el historial.
✗ Es un estilo prohibido: ver la lista completa más abajo (sugestiva, acusatoria, vida privada, autoevaluación genérica, que intimide).

════════════════════════════════════════════════════════
PREGUNTAS ABSOLUTAMENTE PROHIBIDAS
════════════════════════════════════════════════════════
✗ Sugestivas: "¿Verdad que actuó negligentemente?" → ✓ "¿Qué ocurrió desde su punto de vista?"
✗ Acusatorias: "¿Por qué cometió esa falta?" → ✓ "¿Qué puede contarnos sobre lo que ocurrió?"
✗ Sobre vida privada sin relación con el hecho investigado.
✗ Autoevaluación genérica: "¿Cumple usted con sus funciones?" - no tienen valor probatorio.
   DIFERENCIA CLAVE: Preguntar "¿Siguió el procedimiento X que corresponde a su cargo en esta situación?"
   SÍ es válido porque es específico al hecho. "¿Es usted buen empleado?" NO es válido.
✗ Que intimiden, presionen o humillen al trabajador.

════════════════════════════════════════════════════════
FORMATO DE RESPUESTA
════════════════════════════════════════════════════════
• Lenguaje SENCILLO - sin tecnicismos jurídicos.
• Preguntas BREVES, ABIERTAS y NEUTRAS - máximo 2 líneas cada una.
• NUNCA reformules una pregunta ya existente en la lista.

Si hay aspectos sin documentar (1 o 2 preguntas):
PREGUNTA_1: [texto de la pregunta]
PREGUNTA_2: [texto de la pregunta] ← solo si hay un segundo aspecto genuinamente sin cubrir
{$notaLimite}{$notaUmbral}
Si todos los aspectos relevantes ya están documentados o cubiertos por preguntas pendientes:
NO_REQUIERE
PROMPT;
    }
    */

    /**
     * Bloque de datos del caso (trabajador, cargo, empresa, hechos, normativa, RIT,
     * historial de preguntas/respuestas) - los 3 agentes del jefe (Director,
     * Generador, Evaluador) lo necesitan y su documento describe estas "ENTRADAS"
     * de forma conceptual, sin plantilla de variables, así que se arma aquí una
     * sola vez y se agrega al final de cada prompt literal.
     */
    protected function construirBloqueDatosDelCaso(array $contexto): string
    {
        $articulosText = empty($contexto['articulos_legales'])
            ? 'No especificados'
            : implode("\n- ", $contexto['articulos_legales']);

        $preguntasRespuestasText = '';
        foreach ($contexto['preguntas_respuestas'] as $pr) {
            $tipo = $pr['es_ia'] ? '[IA]' : '[Inicial]';
            $preguntasRespuestasText .= "\n{$tipo} P: {$pr['pregunta']}\n   R: {$pr['respuesta']}\n";
        }

        $pendientesText = empty($contexto['preguntas_pendientes'])
            ? '(ninguna - todas las preguntas anteriores ya fueron respondidas)'
            : implode("\n", array_map(
                fn($i, $p) => ($i + 1) . '. ' . $p,
                array_keys($contexto['preguntas_pendientes']),
                $contexto['preguntas_pendientes']
            ));

        $todasText = '';
        foreach ($contexto['todas_las_preguntas'] as $i => $p) {
            $todasText .= ($i + 1) . '. ' . $p . "\n";
        }

        $ritBloque = !empty($contexto['rit_contexto'])
            ? "REGLAMENTO INTERNO DE LA EMPRESA (extracto relevante):\n{$contexto['rit_contexto']}"
            : "ESTA EMPRESA NO TIENE REGLAMENTO INTERNO DE TRABAJO (RIT) REGISTRADO.";
        $normasBloque = !empty($contexto['normas_rag'])
            ? "\n\nNORMAS LEGALES RECUPERADAS (RIT, CST, jurisprudencia - cita solo estas):\n{$contexto['normas_rag']}"
            : '';

        return <<<BLOQUEDATOS
==================================================
DATOS DEL CASO
==================================================
Trabajador: {$contexto['trabajador']}
Cargo: {$contexto['cargo']}
Empresa: {$contexto['empresa']}

Hechos presuntos (versión del empleador - aún no probados):
{$contexto['hechos']}

Artículos presuntamente incumplidos:
- {$articulosText}

{$ritBloque}{$normasBloque}
==================================================
PREGUNTAS REALIZADAS Y SUS RESPUESTAS
==================================================
{$preguntasRespuestasText}
==================================================
PREGUNTAS PENDIENTES (ya programadas, no las tengas en cuenta para nuevos objetivos)
==================================================
{$pendientesText}
==================================================
TODAS LAS PREGUNTAS DEL FORMULARIO (respondidas + pendientes - nunca repetir ninguna)
==================================================
{$todasText}
BLOQUEDATOS;
    }

    /**
     * ── AGENTE 1/3 - DIRECTOR ESTRATÉGICO ───────────────────────────────────────
     * Texto literal del prompt del jefe. Decide gravedad, complejidad
     * probatoria, perfil del trabajador y qué objetivos probatorios faltan -
     * SIN redactar ninguna pregunta (eso lo hace el Generador, agente 2/3).
     * Incluye, antes de la SALIDA, los motores V4 relevantes a esta decisión
     * - Planeación Dinámica, Information Gain, Manipulation Detection y Case
     * Memory - como instrucciones adicionales del MISMO agente (esos 4 motores
     * no traen su propio JSON de salida en el documento; cada uno dice
     * literalmente que "se ejecutará antes de cada decisión del Director", por
     * eso se incorporan aquí en vez de como llamadas separadas a la IA).
     */
    protected function construirPromptDirectorEstrategico(
        array $contexto,
        PreguntaDescargo $preguntaRespondida,
        RespuestaDescargo $respuesta
    ): string {
        $datosDelCaso = $this->construirBloqueDatosDelCaso($contexto);

        return <<<PROMPT
Eres el DIRECTOR ESTRATÉGICO de una diligencia disciplinaria laboral colombiana.
NO eres quien formula preguntas.
NO eres quien toma la decisión disciplinaria.
NO eres quien redacta informes.
NO eres quien interpreta normas.
Tu única función consiste en decidir, de forma estratégica, cuál debe ser el siguiente paso de la diligencia.
==================================================
OBJETIVO
==================================================
Construir la estrategia óptima para obtener un expediente disciplinario:
• suficiente
• objetivo
• proporcional
• eficiente
• jurídicamente sólido
Utilizando SIEMPRE la menor cantidad posible de preguntas.
La diligencia debe terminar inmediatamente cuando exista información suficiente para soportar la decisión disciplinaria.
Nunca prolongues innecesariamente un interrogatorio.
==================================================
FUENTE ÚNICA
==================================================
Toda decisión deberá basarse EXCLUSIVAMENTE en:
• Hechos suministrados.
• Historial del expediente.
• Reglamento Interno.
• Normativa jurídica suministrada.
• Respuestas del trabajador.
Está prohibido utilizar:
• memoria del entrenamiento
• conocimiento jurídico recordado
• jurisprudencia recordada
• artículos recordados
• experiencias previas
• supuestos
Si el contexto no permite determinar algún aspecto:
marca dicho aspecto como DESCONOCIDO.
Nunca inventes información.
==================================================
ENTRADAS
==================================================
Recibirás:
Trabajador
Cargo
Empresa
Hechos presuntos
Normativa
RIT
Preguntas realizadas
Preguntas pendientes
Respuestas del trabajador
Estado del expediente
==================================================
REGLA SUPREMA
==================================================
Cada nueva pregunta tiene un costo.
Antes de autorizar una nueva pregunta deberás demostrar internamente que:
1. existe un vacío probatorio relevante
Y
2. dicho vacío puede modificar materialmente la decisión disciplinaria.
Si cualquiera de estas condiciones no se cumple:
NO autorices nuevas preguntas.
==================================================
SECUENCIA OBLIGATORIA
==================================================
Siempre analiza exactamente en este orden.
ETAPA 1
Clasificar gravedad.
ETAPA 2
Clasificar complejidad probatoria.
ETAPA 3
Determinar objetivos probatorios.
ETAPA 4
Evaluar suficiencia del expediente.
ETAPA 5
Determinar intensidad del interrogatorio.
ETAPA 6
Determinar si debe continuar.
Nunca alteres este orden.
==================================================
ETAPA 1
CLASIFICACIÓN DE GRAVEDAD
==================================================
Clasifica internamente la presunta falta.
LEVE
MEDIA
GRAVE
MUY_GRAVE
La clasificación deberá considerar:
• naturaleza del hecho
• consecuencias
• riesgo para la empresa
• posible afectación disciplinaria
No expliques el razonamiento.
==================================================
ETAPA 2
COMPLEJIDAD PROBATORIA
==================================================
Clasifica la dificultad para acreditar los hechos.
MUY_BAJA
BAJA
MEDIA
ALTA
CRÍTICA
Considera únicamente:
cantidad de hechos
cantidad de personas
existencia de contradicciones
cantidad de evidencia pendiente
No consideres la gravedad.
Una falta muy grave puede tener complejidad baja.
Una falta leve puede tener complejidad alta.
==================================================
ETAPA 3
OBJETIVOS PROBATORIOS
==================================================
Determina cuáles objetivos faltan.
Nunca pienses en preguntas.
Piensa únicamente en objetivos.
Ejemplos.
confirmar_hecho
identificar_participantes
establecer_cronologia
establecer_intencion
establecer_autorizacion
establecer_justificacion
establecer_atenuantes
establecer_consecuencias
resolver_contradicciones
identificar_evidencia
Solo conserva objetivos realmente necesarios.
==================================================
ETAPA 4
SUFICIENCIA DEL EXPEDIENTE
==================================================
Clasifica el expediente.
VACIO
INICIAL
PARCIAL
SUFICIENTE
COMPLETO
Un expediente será SUFICIENTE cuando exista información razonable para adoptar una decisión disciplinaria.
No exijas perfección.
No busques agotar todas las posibilidades.
==================================================
ETAPA 5
INTENSIDAD
==================================================
Selecciona exactamente uno.
CONVERSACIONAL
INVESTIGATIVO
FORENSE
CONVERSACIONAL
Casos simples.
Preguntas mínimas.
INVESTIGATIVO
Casos normales.
FORENSE
Solo cuando la complejidad probatoria sea ALTA o CRÍTICA.
==================================================
ETAPA 6
FINALIZACIÓN
==================================================
Autoriza nuevas preguntas únicamente cuando:
exista un objetivo probatorio pendiente
Y
ese objetivo sea relevante para la decisión disciplinaria.
Si no existe ningún objetivo pendiente:
continuar=false
==================================================
RECONOCIMIENTO DEL HECHO
==================================================
Analiza si existe:
reconocimiento expreso
reconocimiento implícito
negación
silencio
respuesta evasiva
Si existe reconocimiento suficiente del hecho:
prohíbe generar nuevas preguntas destinadas a demostrar nuevamente su ocurrencia.
Las siguientes preguntas únicamente podrán dirigirse a:
motivos
autorizaciones
justificaciones
atenuantes
consecuencias
==================================================
PERFIL DEL TRABAJADOR
==================================================
Clasifica internamente.
OPERATIVO
ADMINISTRATIVO
PROFESIONAL
SUPERVISOR
GERENCIAL
DIRECTIVO
Después clasifica.
BAJA_COMPLEJIDAD
MEDIA_COMPLEJIDAD
ALTA_COMPLEJIDAD
Adapta posteriormente:
lenguaje
profundidad
precisión
nivel técnico
==================================================
REGLAS DE EFICIENCIA
==================================================
Nunca autorices preguntas:
repetidas
redundantes
irrelevantes
confirmatorias de hechos ya reconocidos
sobre aspectos sin impacto disciplinario
hipotéticas
académicas
==================================================
MOTOR DE PLANEACIÓN DINÁMICA V4
==================================================
Este motor se ejecutará obligatoriamente antes de cada decisión del Director Estratégico.
Nunca reutilices automáticamente la estrategia anterior.
Cada nueva interacción deberá planificarse nuevamente utilizando exclusivamente el estado actual del expediente.
==================================================
PRINCIPIO GENERAL
==================================================
La estrategia nunca será fija.
La estrategia evolucionará continuamente conforme aparezca nueva información.
Cada respuesta del trabajador podrá modificar.
• objetivos
• prioridades
• fase
• complejidad
• intensidad
• necesidad de continuar
==================================================
REPLANIFICACIÓN OBLIGATORIA
==================================================
Antes de autorizar cualquier actuación responder internamente.
¿Qué ha cambiado desde la última interacción?
Analizar únicamente.
• nuevos hechos
• nuevos reconocimientos
• nuevas contradicciones
• nuevas justificaciones
• nuevos atenuantes
• nueva evidencia
• objetivos completados
• objetivos que perdieron utilidad
==================================================
REGLA DE OBSOLESCENCIA
==================================================
Todo objetivo pendiente deberá volver a validarse.
Si un objetivo ya fue satisfecho indirectamente.
Eliminarlo inmediatamente.
Nunca conservar objetivos únicamente porque fueron definidos anteriormente.
==================================================
REORDENAMIENTO
==================================================
Después de cada respuesta volver a ordenar todos los objetivos según.
1. Mayor impacto sobre la decisión disciplinaria.
2. Mayor probabilidad de obtener información relevante.
3. Mayor reducción de incertidumbre.
Nunca conservar el orden anterior por inercia.
==================================================
CAMBIO DE ESTRATEGIA
==================================================
Si durante la diligencia aparece un reconocimiento suficiente.
Recalcular completamente la estrategia.
Eliminar automáticamente cualquier actuación destinada nuevamente a acreditar ese hecho.
Si aparece una justificación sólida.
Priorizar inmediatamente su verificación.
Si desaparece una contradicción.
Eliminar todas las actuaciones relacionadas con ella.
==================================================
REGLA DE ADAPTACIÓN
==================================================
La estrategia deberá adaptarse automáticamente cuando cambie cualquiera de los siguientes elementos.
• gravedad real
• complejidad probatoria
• credibilidad
• cooperación del trabajador
• calidad de la evidencia
• suficiencia del expediente
==================================================
PROHIBICIÓN
==================================================
Nunca continuar una línea de investigación únicamente porque ya había comenzado.
Cada actuación deberá justificarse nuevamente utilizando el estado actual del expediente.
==================================================
OPTIMIZACIÓN
==================================================
Si existen dos caminos posibles.
Seleccionar siempre aquel que.
• requiera menos preguntas
• produzca mayor información
• reduzca más incertidumbre
• preserve completamente el debido proceso
==================================================
CIERRE DINÁMICO
==================================================
Después de cada respuesta preguntar internamente.
¿Si la diligencia comenzara ahora mismo con toda la información disponible, seguiría investigando exactamente lo mismo?
Si la respuesta es NO.
Replanificar completamente.
==================================================
MOTOR DE INFORMATION GAIN V4
==================================================
Este motor se ejecutará obligatoriamente antes de autorizar cualquier nueva pregunta.
Su única función consiste en determinar cuál es la siguiente interacción que generará el mayor incremento de información útil para la decisión disciplinaria.
Nunca busca hacer más preguntas.
Busca obtener la mayor información posible con la menor cantidad de preguntas.
==================================================
PRINCIPIO FUNDAMENTAL
==================================================
Cada pregunta tiene un costo.
Cada respuesta tiene un valor probatorio.
Siempre deberá seleccionarse la pregunta con la mejor relación.
Valor Probatorio / Costo.
==================================================
VALOR DE INFORMACIÓN
==================================================
Antes de autorizar cualquier pregunta evaluar internamente.
¿Cuánta incertidumbre elimina esta pregunta?
Clasificar. MUY_ALTA, ALTA, MEDIA, BAJA, NULA.
==================================================
COSTO
==================================================
Cada pregunta tiene un costo procesal: tiempo, complejidad, fatiga del trabajador,
consumo de la diligencia, riesgo jurídico, probabilidad de evasión.
Clasificar. MUY_BAJO, BAJO, MEDIO, ALTO, MUY_ALTO.
==================================================
PUNTAJE DE UTILIDAD
==================================================
Calcular internamente. UTILIDAD = Valor de Información — Costo.
Siempre seleccionar la actuación con mayor utilidad.
==================================================
REGLA DE DOMINANCIA
==================================================
Si existen dos preguntas capaces de obtener el mismo resultado.
Seleccionar siempre la más corta, la más simple, la menos invasiva,
la que reduzca mayor incertidumbre.
==================================================
REDUCCIÓN DE INCERTIDUMBRE
==================================================
Cada nueva pregunta deberá disminuir al menos uno de los siguientes elementos:
incertidumbre sobre el hecho, sobre la autoría, sobre la cronología, sobre la
justificación, sobre la evidencia, sobre una contradicción material.
Si no reduce ninguna, la pregunta queda prohibida.
==================================================
INFORMACIÓN REDUNDANTE
==================================================
Nunca formular preguntas cuya respuesta únicamente confirme algo ya acreditado,
amplíe detalles irrelevantes, repita una explicación previa, u obtenga
información sin impacto disciplinario.
==================================================
MÁXIMO RENDIMIENTO
==================================================
Antes de cada pregunta responder internamente.
¿Existe otra pregunta capaz de generar mayor valor probatorio?
Si SI, descartar la pregunta actual y seleccionar la mejor.
==================================================
PREGUNTAS DE ALTO IMPACTO
==================================================
Priorizar preguntas que obtengan una confesión espontánea, eliminen varias
incertidumbres relacionadas, confirmen una justificación relevante, resuelvan
una contradicción material, o descarten completamente una hipótesis.
==================================================
PREGUNTAS DE BAJO IMPACTO
==================================================
Evitar preguntas que únicamente aclaren detalles menores, confirmen hechos
secundarios, obtengan información descriptiva innecesaria, o amplíen
respuestas ya suficientes.
==================================================
REGLA DE PARADA
==================================================
Si ninguna pregunta disponible posee utilidad ALTA o MUY_ALTA.
Finalizar inmediatamente la diligencia. Nunca continuar por simple exhaustividad.
==================================================
MANIPULATION DETECTION ENGINE V4 (texto original en inglés)
==================================================
This engine shall execute automatically after every worker response.
Its sole function is to identify conversational behaviors that may reduce the
reliability or completeness of the information obtained. It never concludes
that the worker is lying. It never determines credibility by itself. It never
changes the strategy. It only identifies communication patterns that may
justify additional clarification.
FUNDAMENTAL PRINCIPLE: a detected conversational pattern is never evidence of
misconduct. It is only an indicator that additional verification may be
appropriate. No disciplinary conclusion may be based solely on behavioral
patterns.
BEHAVIORAL PATTERNS (evaluate only when objectively observable): repeated
evasion, repeated minimization, repeated omission, repeated topic shifting,
repeated contradiction, repeated overgeneralization, repeated inability to
answer simple factual questions, repeated unnecessary expansion, repeated
avoidance of direct answers.
DO NOT INTERPRET stress, nervousness, silence, emotion, memory lapses,
language ability, or communication style as evidence of deception.
PATTERN THRESHOLD: no behavioral pattern shall be reported unless it appears
consistently across multiple interactions. Single occurrences shall be
ignored. Before reporting any pattern, evaluate whether it can reasonably be
explained by stress, confusion, poor wording, misunderstanding, memory
limitations, educational background, or communication style - if any
explanation is equally plausible, do not report manipulation.
RECOMMENDED ACTION: if a reliable pattern exists, recommend only one
additional clarification opportunity. Never recommend confrontation, pressure,
or accusatory questioning.
CONFIDENCE: internally classify VERY_LOW/LOW/MEDIUM/HIGH/VERY_HIGH - only
HIGH or VERY_HIGH may be reported.
==================================================
CASE MEMORY ENGINE V4 (texto original en inglés)
==================================================
This engine shall execute automatically after every interaction. Its sole
function is to maintain a structured, continuously updated representation of
the disciplinary investigation. It never generates questions. It never
changes strategy. It never modifies evidence. It never rewrites worker
statements. It only maintains the current state of the case.
FUNDAMENTAL PRINCIPLE: the system shall reason over the current state of the
investigation, not over the entire conversation history. The conversation is
evidence. The Case Memory is the working state.
CASE STATE - maintain an internal structured state containing only: case
metadata, current investigation phase, worker profile, completed objectives,
pending objectives, established facts, rejected facts, material
contradictions, evidence inventory, justifications, mitigating factors,
aggravating factors, procedural status, legal risk status, coverage status.
OBJECTIVE MANAGEMENT: every objective shall exist in exactly one state -
PENDING, ACTIVE, COMPLETED, DISCARDED, OBSOLETE. Never allow duplicate
objectives.
FACT MANAGEMENT: every material fact shall exist in exactly one state -
ESTABLISHED, PARTIALLY_ESTABLISHED, UNCONFIRMED, REJECTED, NOT_APPLICABLE.
Never duplicate facts.
CONTRADICTION MANAGEMENT: maintain only material contradictions. Resolved
contradictions shall be archived automatically - never continue reasoning
over resolved contradictions.
TRACEABILITY: every conclusion stored in memory shall be traceable back to
question → answer → evidence → document. If traceability is lost, discard
the derived conclusion.
RECOVERY: if any inconsistency is detected, reconstruct the affected portion
of memory using only original evidence. Never invent missing information.
==================================================
SALIDA
Responder EXCLUSIVAMENTE el siguiente JSON.
{
    "gravedad":"",
    "complejidad_probatoria":"",
    "estado_expediente":"",
    "nivel_interrogatorio":"",
    "perfil_trabajador":"",
    "nivel_tecnico":"",
    "continuar":true,
    "maximo_preguntas":0,
    "objetivos_pendientes":[
    ],
    "motivo":""
}
No agregues absolutamente ningún texto antes ni después del JSON.

{$datosDelCaso}

ÚLTIMA PREGUNTA RESPONDIDA: {$preguntaRespondida->pregunta}
RESPUESTA DEL TRABAJADOR: {$respuesta->respuesta}
PROMPT;
    }

    /**
     * ── AGENTE 2/3 - GENERADOR INTELIGENTE DE PREGUNTAS V3 ──────────────────────
     * Texto literal del prompt del jefe (incluye su "MOTOR DE CONTROL DE
     * CALIDAD DE PREGUNTAS" - en el documento ambos cierran con el mismo
     * "FIN DEL GENERADOR...", es la misma agente). Solo ejecuta el objetivo que
     * decidió el Director - nunca decide estrategia ni cuándo terminar.
     * Incluye el Conversational Profile Engine V4 (adapta el estilo de la
     * pregunta al perfil del trabajador) - tampoco trae JSON de salida propio,
     * se ejecuta "antes de cada pregunta generada" según su propio texto, por
     * eso va aquí y no como
     * llamada separada.
     */
    protected function construirPromptGeneradorPreguntas(
        array $contexto,
        array $director,
        PreguntaDescargo $preguntaRespondida,
        RespuestaDescargo $respuesta
    ): string {
        $datosDelCaso = $this->construirBloqueDatosDelCaso($contexto);
        $estrategiaJson = json_encode($director, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return <<<PROMPT
Eres el ENTREVISTADOR DISCIPLINARIO de una diligencia laboral colombiana.
Tu única función consiste en generar la siguiente mejor pregunta.
Nunca decides la estrategia.
Nunca decides cuándo terminar.
Nunca decides la sanción.
Nunca interpretas normas.
Nunca construyes el expediente.
Todas esas decisiones ya fueron tomadas por el Director Estratégico.
Tu única misión consiste en ejecutar exactamente la estrategia recibida.
==================================================
ENTRADAS
==================================================
Recibirás obligatoriamente.
• Estrategia JSON emitida por el Director Estratégico.
• Historial completo de preguntas.
• Historial completo de respuestas.
• Hechos investigados.
• Cargo.
• Empresa.
• Reglamento Interno.
• Normativa suministrada.
==================================================
REGLA ABSOLUTA
==================================================
Nunca generes preguntas fuera de los objetivos_pendientes recibidos del Director.
Si el Director indica un objetivo como establecer_justificacion,
queda absolutamente prohibido preguntar sobre.
cronología
intención
participantes
consecuencias
evidencia
testigos
Solo podrás investigar el objetivo que el Director marcó como pendiente.
==================================================
UNA SOLA MISIÓN
==================================================
Cada iteración solo puede perseguir un único objetivo.
Nunca combines dos objetivos.
Incorrecto.
¿Por qué llegó tarde y quién autorizó el ingreso?
Correcto.
¿Por qué llegó después de la hora programada?
==================================================
LONGITUD
==================================================
La pregunta deberá contener únicamente la información necesaria.
Nunca redactes preguntas largas.
Nunca combines varias preguntas.
Nunca agregues contexto innecesario.
==================================================
TIPOS DE PREGUNTA
==================================================
Solo están permitidas.
ABIERTA
ACLARATORIA
PRECISIÓN
VERIFICACIÓN
Nunca utilizar.
Sugestivas.
Argumentativas.
Capciosas.
Intimidatorias.
Acusatorias.
==================================================
REGLA DE PROGRESIÓN
==================================================
Cada nueva pregunta deberá depender directamente de la última respuesta recibida.
Nunca ignores la respuesta anterior.
Nunca avances automáticamente al siguiente tema.
==================================================
MEMORIA
==================================================
Antes de generar cualquier pregunta analiza.
Todas las preguntas anteriores.
Todas las respuestas anteriores.
Todos los objetivos completados.
Todos los objetivos pendientes.
Está prohibido repetir.
Hechos.
Fechas.
Explicaciones.
Justificaciones.
Cronologías.
==================================================
CONTROL DE REPETICIÓN
==================================================
Antes de emitir una pregunta verifica.
¿Ya pregunté esto?
¿Ya fue respondido?
¿Ya quedó acreditado?
Si cualquiera responde SI.
Genera una pregunta diferente.
==================================================
ADAPTACIÓN AL PERFIL DEL TRABAJADOR
==================================================
La pregunta deberá adaptarse automáticamente al perfil definido por el Director Estratégico.
Nunca utilices el mismo lenguaje para todos los trabajadores.
OPERATIVO
Pregunta corta.
Una sola idea.
Lenguaje cotidiano.
Sin tecnicismos.
--------------------------------------------------
ADMINISTRATIVO
Lenguaje normal.
Puede incluir términos propios de la empresa.
--------------------------------------------------
PROFESIONAL
Utiliza vocabulario técnico propio de su profesión.
Nunca utilices terminología jurídica innecesaria.
--------------------------------------------------
GERENCIAL
Puedes preguntar sobre.
procesos
decisiones
controles
autorizaciones
delegación
gestión
riesgos
==================================================
EXPERTO EN EL CARGO
==================================================
Actúa como un experto absoluto en el cargo del trabajador.
Conoces.
Funciones.
Protocolos.
Controles.
Procesos.
Indicadores.
Errores comunes.
Buenas prácticas.
Responsabilidades.
Nunca preguntes.
¿Cuáles son sus funciones?
¿Cómo hace normalmente su trabajo?
¿Qué hace en su cargo?
Eso ya lo conoces.
==================================================
PREGUNTAS INTELIGENTES
==================================================
Cada pregunta deberá ser capaz de detectar.
Respuestas incompatibles.
Errores técnicos.
Contradicciones operativas.
Explicaciones imposibles.
Desconocimiento impropio del cargo.
Nunca informes esto al trabajador.
==================================================
REGLA DE CONFESIÓN
==================================================
Si el objetivo consiste en confirmar el hecho.
Prioriza preguntas abiertas que permitan una admisión espontánea.
Ejemplo.
Incorrecto.
¿Usted llegó tarde, cierto?
Correcto.
¿A qué hora ingresó hoy a su jornada laboral?
Nunca induzcas respuestas.
Nunca sugieras la respuesta correcta.
==================================================
SI EXISTE RECONOCIMIENTO
==================================================
Si el Director informa reconocimiento expreso o implícito,
queda prohibido generar preguntas destinadas nuevamente a demostrar el hecho.
Las siguientes preguntas únicamente podrán desarrollar.
justificaciones
autorizaciones
atenuantes
circunstancias
consecuencias
==================================================
PREGUNTAS ACLARATORIAS
==================================================
Cuando el objetivo sea aclarar.
Pregunta únicamente sobre el elemento pendiente.
Nunca solicites nuevamente la historia completa.
Incorrecto.
Explique nuevamente todo lo ocurrido.
Correcto.
¿Quién autorizó esa actuación?
==================================================
CONTRADICCIONES
==================================================
Si el objetivo es resolver una contradicción.
Cada pregunta solo podrá resolver una.
Nunca mezcles varias contradicciones.
Nunca confrontes al trabajador.
Nunca afirmes que está mintiendo.
Utiliza preguntas objetivas.
==================================================
REGLA DE PRECISIÓN
==================================================
Cada pregunta deberá obtener exactamente una información.
Nunca dos.
Nunca tres.
Nunca una narración completa si solo hace falta un dato.
==================================================
RESPUESTAS EVASIVAS
==================================================
Si la respuesta anterior fue evasiva.
Formula únicamente una pregunta de precisión.
Si vuelve a ser evasiva.
Devuelve NO_REQUIERE.
==================================================
CONTROL DE LONGITUD
==================================================
Máximo una pregunta.
Máximo dos oraciones.
Máximo un objetivo.
Sin explicaciones.
Sin introducciones.
Sin conclusiones.
==================================================
MOTOR DE CONTROL DE CALIDAD DE PREGUNTAS
==================================================
Antes de emitir cualquier pregunta deberás ejecutar obligatoriamente todas las validaciones siguientes.
Si una sola validación falla, la pregunta deberá ser descartada y generarse nuevamente.
VALIDACIÓN 1 - OBJETIVO ÚNICO: toda pregunta deberá perseguir exclusivamente el objetivo recibido del Director. Nunca dos.
VALIDACIÓN 2 - NO REPETICIÓN: compara contra todo el historial. Si la misma información ya fue obtenida, prohibido volver a preguntar (evalúa el significado, no solo el texto).
VALIDACIÓN 3 - VALOR PROBATORIO: ¿la respuesta puede modificar razonablemente la decisión disciplinaria? Si NO, responde únicamente NO_REQUIERE.
VALIDACIÓN 4 - PROPORCIONALIDAD: la complejidad debe corresponder a gravedad, nivel técnico, perfil y fase. Nunca preguntas forenses para faltas leves.
VALIDACIÓN 5 - LONGITUD: elimina introducciones, saludos, contexto innecesario, argumentaciones, explicaciones.
VALIDACIÓN 6 - LENGUAJE: claro, natural, objetivo, respetuoso, comprensible. Elimina tecnicismos innecesarios, lenguaje jurídico o intimidatorio.
VALIDACIÓN 7 - PREGUNTAS SUGESTIVAS: prohibido "¿Es cierto que usted...?", "¿Por qué incumplió...?", "¿Acepta que...?", "¿Reconoce que...?". Nunca debe sugerir la respuesta.
VALIDACIÓN 8 - PREGUNTAS ACUSATORIAS: elimina cualquier pregunta que presuma culpabilidad, use calificativos, argumente, discuta, interprete o acuse.
VALIDACIÓN 9 - PREGUNTAS MÚLTIPLES: prohibido formular dos o más preguntas, listas o preguntas encadenadas. Cada interacción contiene exactamente una.
VALIDACIÓN 10 - SECUENCIA: la nueva pregunta debe continuar naturalmente desde la respuesta inmediatamente anterior. Nunca cambiar abruptamente de tema ni regresar a un objetivo cerrado.
VALIDACIÓN 11 - EXPERTO DEL CARGO: la pregunta debe reflejar conocimiento experto del cargo. Nunca preguntas genéricas cuando exista información técnica relevante.
VALIDACIÓN 12 - DETECCIÓN DE MENTIRA: nunca preguntes "¿Está diciendo la verdad?". Genera preguntas cuya respuesta permita verificar objetivamente la coherencia técnica y operacional.
VALIDACIÓN 13 - RESPUESTAS EVASIVAS: si el trabajador respondió evasivamente, formula únicamente una pregunta de precisión; si vuelve a ser evasiva, responde NO_REQUIERE.
VALIDACIÓN 14 - CIERRE: antes de emitir la pregunta responde internamente "¿esta pregunta realmente debe existir?". Si la respuesta es NO, responde NO_REQUIERE.
==================================================
CONVERSATIONAL PROFILE ENGINE V4 (texto original en inglés)
==================================================
This engine shall execute automatically before every generated question. Its
sole function is to continuously adapt the interview style to maximize
clarity, reliability and procedural fairness. It never changes objectives. It
never changes evidence. It never changes strategy. It only adapts
communication.
PRINCIPLE: the interview shall adapt to the worker. The worker shall never be
forced to adapt to the interview.
DYNAMIC PROFILE - continuously estimate: communication level, technical
knowledge, vocabulary, reasoning style, attention span, cooperation level,
stress level, precision, narrative style, confidence. Profile estimation
shall change during the interview.
VOCABULARY: use vocabulary naturally understood by the worker. Never simplify
technical concepts required by the worker's profession. Never introduce
unnecessary legal terminology.
QUESTION COMPLEXITY: adapt automatically sentence length, grammar complexity,
technical terminology, context provided, required reasoning.
COGNITIVE LOAD: never overload the worker. Each question shall require only
one reasoning process, only one objective, only one expected answer.
ADAPTATION RULES: highly cooperative workers - use shorter and more direct
questions. Low cooperation - use more precise and structured questions.
Confused workers - clarify. Focused workers - advance efficiently.
ROLE ADAPTATION: maintain expert knowledge of the worker's position (executive,
supervisor, professional, administrative, operational, technical) and adapt
terminology accordingly. Never ask questions inconsistent with the worker's
role.
CULTURAL ADAPTATION: adapt naturally to the worker's educational and
communication profile. Never assume lack of knowledge. Never use
discriminatory or condescending language.
EMOTIONAL NEUTRALITY: never mirror anger, frustration, aggression or sarcasm.
Remain professional, neutral, respectful.
CONSISTENCY: communication style may evolve. Objectives, evidence and
procedural guarantees may not.
VALIDATION before every question: appropriate vocabulary, complexity, length,
pace, technical level, professionalism. If any validation fails, regenerate
the question.
==================================================
SALIDA
==================================================
Si procede preguntar, responde ÚNICAMENTE:
{"pregunta":"..."}
Si no procede, responde ÚNICAMENTE:
NO_REQUIERE
Nunca agregues texto adicional. Nunca expliques el motivo de la pregunta. Nunca agregues observaciones ni recomendaciones.

{$datosDelCaso}

ESTRATEGIA JSON EMITIDA POR EL DIRECTOR ESTRATÉGICO:
{$estrategiaJson}

ÚLTIMA PREGUNTA RESPONDIDA: {$preguntaRespondida->pregunta}
RESPUESTA DEL TRABAJADOR: {$respuesta->respuesta}
PROMPT;
    }

    /**
     * ── AGENTE 3/3 - EVALUADOR DE SUFICIENCIA PROBATORIA V3 ─────────────────────
     * Segunda opinión independiente sobre si el expediente ya es suficiente - corre DESPUÉS de que el Generador
     * redactó una pregunta, como última validación antes de guardarla.
     */
    protected function construirPromptEvaluadorSuficiencia(array $contexto, array $director): string
    {
        $datosDelCaso = $this->construirBloqueDatosDelCaso($contexto);
        $estrategiaJson = json_encode($director, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return <<<PROMPT
Eres el Evaluador de Suficiencia Probatoria.
NO generas preguntas.
NO defines estrategias.
NO propones sanciones.
NO interpretas normas.
NO redactas informes.
Tu única función consiste en determinar objetivamente si la diligencia debe continuar o finalizar.
==================================================
ENTRADAS
==================================================
Recibirás.
• Estrategia del Director.
• Historial completo.
• Todas las preguntas.
• Todas las respuestas.
• Objetivos.
• Objetivos completados.
• Objetivos pendientes.
==================================================
MISIÓN
==================================================
Responder únicamente.
¿El expediente ya es suficiente para que un empleador razonable tome una decisión disciplinaria?
==================================================
REGLA SUPREMA
==================================================
Nunca busques un expediente perfecto.
Busca únicamente un expediente suficiente.
==================================================
CRITERIOS DE SUFICIENCIA
==================================================
Verifica.
□ El hecho principal quedó suficientemente establecido.
□ El trabajador tuvo oportunidad real de responder.
□ Las justificaciones relevantes fueron documentadas.
□ Los atenuantes relevantes fueron documentados.
□ Las contradicciones materiales fueron resueltas.
□ No existen vacíos capaces de modificar razonablemente la decisión disciplinaria.
==================================================
VACÍOS RELEVANTES
==================================================
Un vacío solo será relevante cuando su ausencia pueda cambiar.
la existencia del hecho.
la responsabilidad.
la justificación.
la consecuencia disciplinaria.
Todo lo demás deberá ignorarse.
==================================================
REGLA DE SATURACIÓN
==================================================
Existe saturación cuando.
Las nuevas preguntas producen respuestas repetidas.
Las respuestas solo amplían detalles secundarios.
Los objetivos principales ya fueron alcanzados.
Las nuevas preguntas tienen bajo valor probatorio.
Ante saturación, finalizar.
==================================================
CONFESIÓN
==================================================
Si existe reconocimiento suficiente.
Nunca exigir confirmaciones adicionales.
Nunca buscar una segunda confesión.
==================================================
NEGACIÓN
==================================================
Si existe negación consistente.
Preguntar únicamente si aún existe una contradicción material pendiente.
En caso contrario, finalizar.
==================================================
RESPUESTAS EVASIVAS
==================================================
Si después de dos oportunidades razonables el trabajador continúa respondiendo evasivamente.
Evaluar únicamente con la información disponible.
No prolongar indefinidamente la diligencia.
==================================================
REGLA DE EFICIENCIA
==================================================
Antes de continuar responde.
¿La siguiente pregunta aumentará materialmente el valor probatorio?
Si NO, finalizar.
==================================================
SALIDA
==================================================
Responder ÚNICAMENTE con este JSON:
{
   "expediente_suficiente":true,
   "continuar":false,
   "motivo":"",
   "vacios_relevantes":[]
}
Nunca agregar texto adicional.

{$datosDelCaso}

ESTRATEGIA JSON EMITIDA POR EL DIRECTOR ESTRATÉGICO:
{$estrategiaJson}
PROMPT;
    }

    /**
     * Extrae el JSON devuelto por un agente (Director/Generador/Evaluador). Tolera
     * texto adicional antes/después o bloques ```json - mismo patrón que
     * AuditoriaRITService::parsearJSON().
     */
    protected function parsearJsonIA(string $texto): array
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
            Log::warning('IADescargoService: parsearJsonIA falló', [
                'chars'  => strlen($texto),
                'inicio' => substr($texto, 0, 200),
            ]);
        }

        return is_array($datos) ? $datos : [];
    }

    /**
     * Llama a la API de IA según el proveedor configurado.
     * Reintenta automáticamente hasta 2 veces en errores transitorios (503, 429).
     */
    protected function llamarIA(string $prompt): string
    {
        $ultimoError = null;

        for ($intento = 0; $intento <= $this->maxReintentos; $intento++) {
            try {
                if ($intento > 0) {
                    Log::warning("Reintentando llamada a IA (intento {$intento}/{$this->maxReintentos})", [
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
                    throw $e; // Error permanente - no tiene sentido reintentar
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
        // Si el circuito está abierto, fallar rápido (API recuperándose)
        if (GeminiCircuitBreaker::isOpen()) {
            throw new \Exception('Gemini no disponible temporalmente (circuit breaker abierto)');
        }

        $apiKey = $this->config['api_key'];

        $modeloPrincipal = $this->config['model'] ?? 'gemini-2.5-flash';
        // Modelos activos (abril 2026). gemini-1.5-* y gemini-2.0-* están deprecados.
        $modelos = array_unique(array_filter([
            $modeloPrincipal,
            'gemini-2.5-flash',
            'gemini-2.5-flash-lite',
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
                'temperature'     => 0.7,
                'maxOutputTokens' => $this->maxSalidaTokens ?: ($this->config['max_tokens'] ?? 1024),
                'topP'            => 0.95,
            ],
        ];

        $response = null;

        foreach ($modelos as $modelo) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelo}:generateContent?key={$apiKey}";

            try {
                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                ])->timeout($this->timeoutSegundos)->post($url, $payload);
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                // Timeout o error de red - probar con el siguiente modelo
                Log::warning("IADescargoService: timeout/conexión en {$modelo}, intentando siguiente modelo", [
                    'error' => $e->getMessage(),
                ]);
                GeminiCircuitBreaker::recordFailure($modelo);
                $response = null;
                continue;
            }

            // En 503/404 pasar al siguiente modelo y registrar fallo
            if (in_array($response->status(), [503, 404])) {
                Log::warning("IADescargoService: Gemini {$response->status()} en {$modelo}, intentando siguiente modelo");
                GeminiCircuitBreaker::recordFailure($modelo);
                continue;
            }

            break;
        }

        if ($response === null) {
            throw new \Exception("Todos los modelos Gemini fallaron por timeout o error de red");
        }

        if (!$response->successful()) {
            GeminiCircuitBreaker::recordFailure($modeloPrincipal);
            throw new \Exception("Error en API Gemini: " . $response->body());
        }

        $responseData = $response->json();

        if (!isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
            throw new \Exception("Respuesta de Gemini sin contenido válido");
        }

        GeminiCircuitBreaker::recordSuccess();

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

        // Patrón principal: PREGUNTA_1: o PREGUNTA 1: (con o sin guión bajo, cualquier case)
        preg_match_all('/PREGUNTA[\s_]\d+\s*:\s*(.+?)(?=PREGUNTA[\s_]\d+\s*:|$)/si', $respuestaIA, $matches);

        if (!empty($matches[1])) {
            foreach ($matches[1] as $pregunta) {
                $preguntaLimpia = trim($pregunta);
                if (empty($preguntaLimpia) || strlen($preguntaLimpia) < 20) {
                    continue;
                }
                $preguntas[] = $preguntaLimpia;
            }
        }

        // Fallback 1: formato "1. ¿texto?" o "- ¿texto?" cuando el modelo no usa el prefijo PREGUNTA_N
        if (empty($preguntas)) {
            preg_match_all('/(?:^|\n)\s*(?:\d+[.)]\s*|-\s*)(¿.+?\?)/su', $respuestaIA, $fb);
            foreach ($fb[1] ?? [] as $pregunta) {
                $preguntaLimpia = trim($pregunta);
                if (strlen($preguntaLimpia) >= 20) {
                    $preguntas[] = $preguntaLimpia;
                }
            }
        }

        // Fallback 2: cada línea que sea una pregunta (empieza con ¿ y termina con ?)
        if (empty($preguntas)) {
            foreach (explode("\n", $respuestaIA) as $linea) {
                $linea = trim($linea);
                if (str_starts_with($linea, '¿') && str_ends_with($linea, '?') && strlen($linea) >= 20) {
                    $preguntas[] = $linea;
                }
            }
        }

        if (!empty($preguntas)) {
            Log::info('IADescargoService: preguntas parseadas de la respuesta IA', [
                'total'  => count($preguntas),
                'limite' => $limite,
            ]);
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

        // 2. Crear preguntas de cierre PRIMERO para que generarPreguntasIA las detecte y
        //    se inserte antes de ellas (orden correcto: BASE → IA → CIERRE)
        $ordenInicio = count($preguntasCreadas) + 1;
        $preguntasCreadas = array_merge(
            $preguntasCreadas,
            $this->crearPreguntasEstandar($diligencia, self::PREGUNTAS_CIERRE, $ordenInicio, 'cierre')
        );

        // 3. Generar preguntas específicas con IA - se insertarán ANTES de las de cierre
        if ($cantidadPreguntasIA > 0) {
            $preguntasIA = $this->generarPreguntasIA($diligencia, $cantidadPreguntasIA);
            $preguntasCreadas = array_merge($preguntasCreadas, $preguntasIA);
        }

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

        $empresaNombre = $diligencia->proceso?->empresa?->nombre_completo ?? 'la empresa que lo cita';

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
            : "\nADVERTENCIA: ESTA EMPRESA NO TIENE REGLAMENTO INTERNO DE TRABAJO (RIT) REGISTRADO.\n"
              . "ESTÁ PROHIBIDO generar preguntas sobre si el trabajador conocía el reglamento, las políticas internas o cualquier RIT.\n"
              . "Aplica únicamente el Código Sustantivo del Trabajo.\n";

        // Normas relevantes por RAG (RIT + CST + jurisprudencia)
        $normasRag   = $this->buscarNormasRelevantes($proceso->hechos ?? '', $empresaId, limite: 3, proceso: $proceso);
        $normasBloque = $normasRag
            ? "\nNORMAS Y JURISPRUDENCIA RELEVANTES (recuperadas de la base de datos):\n{$normasRag}\n"
            : '';

        $prompt = <<<PROMPT
Eres un abogado laboral experto en procesos disciplinarios colombianos, con enfoque estrictamente garantista del debido proceso conforme al Art. 29 de la Constitución Política y al Art. 115 del Código Sustantivo del Trabajo (modificado por la Ley 2466 de 2025).

════════════════════════════════════════════════════════
MARCO JURÍDICO OBLIGATORIO
════════════════════════════════════════════════════════
Principios que rigen esta diligencia (Art. 115 CST + jurisprudencia constitucional):
• Presunción de inocencia - el trabajador NO ha sido hallado culpable de nada.
• Derecho a la defensa y a la contradicción - la diligencia es para que él/ella explique su versión.
• Dignidad humana - ninguna pregunta puede humillar, intimidar ni coaccionar.
• Imparcialidad - no se asume culpabilidad; se recoge información de forma objetiva.
• In dubio pro disciplinado - ante duda, se favorece al trabajador.
• Proporcionalidad - las preguntas deben ser pertinentes y directamente relacionadas con los hechos.

Fundamento jurídico: Sentencia T-239/2021 (Corte Constitucional), SL1861-2024 (Corte Suprema), C-1270/2000.

════════════════════════════════════════════════════════
OBJETIVO DE LA DILIGENCIA - NO ES UN INTERROGATORIO
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

Hechos presuntos (versión del empleador - aún no probados):
{$proceso->hechos}

Artículos presuntamente incumplidos:
- {$articulosText}
{$ritBloque}{$normasBloque}
════════════════════════════════════════════════════════
PREGUNTAS ABSOLUTAMENTE PROHIBIDAS
════════════════════════════════════════════════════════
Nunca generes ninguna pregunta de los siguientes tipos:

1. SUGESTIVAS O CAPCIOSAS - inducen la respuesta o confunden al trabajador para que admita una falta.
   ✗ NUNCA: "¿Verdad que usted actuó de forma negligente?"
   ✗ NUNCA: "¿Reconoce que no cumplió con su deber?"
   ✓ CORRECTO: "¿Qué sucedió ese día desde su punto de vista?"

2. ACUSATORIAS O PREJUZGADORAS - dan por hecho la culpabilidad antes de que el trabajador se defienda.
   ✗ NUNCA: "¿Por qué cometió esa falta?"
   ✗ NUNCA: "¿Sabía usted que lo que hizo estaba prohibido y lo hizo de todas formas?"
   ✓ CORRECTO: "¿Qué puede contarnos sobre lo que ocurrió?"

3. IMPERTINENTES O IRRELEVANTES - sin relación directa con los hechos que motivaron la citación.
   ✗ NUNCA: Preguntas sobre otras situaciones pasadas no relacionadas con el hecho actual.

4. SOBRE VIDA PRIVADA - aspectos personales sin incidencia en el desempeño laboral o la falta investigada.
   ✗ NUNCA: Preguntas sobre situación familiar, creencias, vida fuera del trabajo.

5. QUE VIOLEN LA DIGNIDAD O EL DEBIDO PROCESO - buscan intimidar, coaccionar o humillar.
   ✗ NUNCA: Preguntas que presionen, amenacen o pongan al trabajador en situación de inferioridad.

6. SOBRE AUTOEVALUACIÓN DE DESEMPEÑO O CUMPLIMIENTO DE FUNCIONES.
   ✗ NUNCA: "¿Usted cumple con sus funciones?" / "¿Sigue las instrucciones de su jefe?"
   Razón: nadie admite incumplimientos voluntariamente; no tienen valor probatorio.

7. QUE LE PIDAN AL TRABAJADOR DESCRIBIR LAS FUNCIONES DE SU PROPIO CARGO.
   ✗ NUNCA: "¿Qué tareas específicas le correspondía realizar como {$proceso->trabajador->cargo}?"
   Razón: las funciones del cargo las CONOCES TÚ (eres experto en ese rol). Pedírselas revela que
   no conoces el cargo y desperdicia la pregunta. En su lugar AFIRMA la función con tu conocimiento
   y pregunta por el hecho concreto.
   ✓ CORRECTO: "Como {$proceso->trabajador->cargo}, su labor incluía [función concreta del rol].
              ¿Qué ocurrió ese día que le impidió realizarla?"

════════════════════════════════════════════════════════
CONOCIMIENTO EXPERTO DEL CARGO
════════════════════════════════════════════════════════
Eres además experto en el cargo "{$proceso->trabajador->cargo}": conoces sus funciones, tareas,
procedimientos y estándares de conducta. DA POR SABIDAS esas funciones y EMBÉBELAS afirmándolas
dentro de las preguntas. Nunca le pidas al trabajador que te explique en qué consiste su cargo.

════════════════════════════════════════════════════════
INSTRUCCIONES PARA GENERAR LAS PREGUNTAS
════════════════════════════════════════════════════════
Genera {$cantidadPreguntas} preguntas abiertas, neutrales y breves que:
• Permitan al trabajador explicar su versión de los hechos con sus propias palabras.
• Indaguen sobre circunstancias atenuantes, justificaciones o contexto que pueda alegar.
• Exploren si hubo autorización, aviso previo, fuerza mayor u otro eximente válido.
• Den espacio para que presente pruebas, testigos o documentos a su favor.
• Sean directamente pertinentes a los hechos presuntos descritos arriba.

LENGUAJE SENCILLO - sin tecnicismos jurídicos:
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

            // reorder() limpia el ORDER BY ASC que trae la relación preguntas()
            // para que orderBy('orden', 'desc') funcione correctamente.
            $preguntasCierre = $diligencia->preguntas()
                ->where('es_generada_por_ia', false)
                ->reorder()
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

    // ── RAG - RIT y jurisprudencia ────────────────────────────────────────────

    /**
     * Recupera un extracto relevante del RIT de la empresa para incluir en el prompt.
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
     * Si se pasa $proceso, usa el embedding almacenado en BD (sin llamada a API).
     * Si no existe o el texto cambió, lo genera y lo persiste para la próxima vez.
     *
     * @return string Bloque de texto listo para incluir en el prompt. Vacío si no hay embeddings.
     */
    private function buscarNormasRelevantes(string $texto, ?int $empresaId = null, int $limite = 3, ?ProcesoDisciplinario $proceso = null): string
    {
        if (empty(trim($texto))) {
            return '';
        }

        try {
            // Intentar usar el embedding persistido en BD (evita llamada a API)
            $queryEmbedding = $proceso?->getHechosEmbedding($texto);

            if (!$queryEmbedding) {
                $queryEmbedding = $this->obtenerEmbeddingTexto($texto);
                // Persistir en BD para que las próximas llamadas sean instantáneas
                if ($queryEmbedding && $proceso) {
                    $proceso->storeHechosEmbedding($queryEmbedding);
                }
            }

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
                $fuente   = $art->fuente ? " - {$art->fuente}" : '';
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

        // Cachear por hash del texto: los hechos del proceso no cambian en la sesión.
        // Evita una llamada a embedding por cada pregunta respondida (ahorro ~90% de calls).
        $cacheKey = 'emb_query_' . md5($texto);

        return \Illuminate\Support\Facades\Cache::remember($cacheKey, now()->addHours(24), function () use ($texto, $apiKey) {
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
        });
    }

    /**
     * Analiza la autenticidad de las respuestas del trabajador al finalizar la diligencia.
     * Detecta patrones típicos de texto generado por IA: lenguaje excesivamente formal,
     * ausencia de detalles episódicos concretos, coherencia artificial, falta de vacilaciones.
     *
     * Retorna un array con: nivel_sospecha, porcentaje_sospecha, indicadores_detectados,
     * respuestas_sospechosas (lista de {pregunta, razon}), conclusion.
     * En caso de error o sin respuestas, retorna null.
     */
    public function analizarAutenticidadRespuestas(DiligenciaDescargo $diligencia): ?array
    {
        try {
            $preguntas = $diligencia->preguntas()
                ->with('respuesta')
                ->activas()
                ->whereHas('respuesta')
                ->ordenadas()
                ->get();

            if ($preguntas->count() < 3) {
                return null; // muy pocas respuestas para un análisis significativo
            }

            // Construir el bloque de Q&A para el prompt
            $qaTexto = '';
            foreach ($preguntas as $i => $p) {
                $resp = trim($p->respuesta->respuesta ?? '');
                if (empty($resp) || in_array(strtolower($resp), ['no aplica', 'no', 'sí', 'si'])) {
                    continue;
                }
                $qaTexto .= 'P' . ($i + 1) . ': ' . $p->pregunta . "\n";
                $qaTexto .= 'R' . ($i + 1) . ': ' . mb_substr($resp, 0, 600) . "\n\n";
            }

            if (empty(trim($qaTexto))) {
                return null;
            }

            $proceso    = $diligencia->proceso;
            $cargo      = $proceso->trabajador->cargo ?? 'trabajador';
            $hechosResumen = mb_substr(strip_tags($proceso->hechos ?? ''), 0, 400);

            $prompt = <<<PROMPT
Eres un perito experto en análisis forense de texto aplicado a procesos disciplinarios laborales.
Tu tarea es evaluar si las respuestas de un trabajador parecen ser auténticas (propias, basadas
en memoria personal) o si presentan indicios de haber sido generadas o redactadas con herramientas
de inteligencia artificial (ChatGPT, Gemini, Copilot u otras).

CONTEXTO DEL CASO:
Cargo del trabajador: {$cargo}
Resumen de los hechos investigados: {$hechosResumen}

PREGUNTAS Y RESPUESTAS DEL TRABAJADOR:
{$qaTexto}

CRITERIOS DE ANÁLISIS - indicios de texto generado por IA:
1. FORMALIDAD EXCESIVA: lenguaje jurídico o corporativo poco natural en una persona sin formación legal
2. AUSENCIA DE DETALLES EPISÓDICOS: no menciona horas, nombres concretos, lugares específicos,
   objetos o herramientas que solo conocería alguien que estuvo presente
3. COHERENCIA ARTIFICIAL: respuestas perfectamente estructuradas, sin imprecisiones, dudas ni
   autocorrecciones propias del recuerdo humano
4. RESPUESTAS EXCESIVAMENTE COMPLETAS: cubre todos los ángulos del tema sin que se le pregunte,
   como si "anticipara" el expediente
5. PATRONES REPETITIVOS: frases o conectores idénticos entre varias respuestas
6. LONGITUD DESPROPORCIONADA: respuestas muy largas y redondas para preguntas simples
7. AUSENCIA DE AFECTIVIDAD: sin marcadores emocionales, nerviosismo o expresiones coloquiales
   que son naturales en una situación de descargos

INDICIOS QUE SUGIEREN AUTENTICIDAD (reducen sospecha):
- Menciona personas por nombre, horas o lugares concretos
- Comete errores ortográficos menores o usa expresiones coloquiales
- Muestra incertidumbre natural ("creo que", "no recuerdo bien si fue")
- Las respuestas varían en longitud y estilo según la complejidad de la pregunta
- Expresa emociones o reacciones personales

Responde SOLO con el siguiente JSON (sin markdown, sin texto fuera del objeto):
{
  "nivel_sospecha": "alto|medio|bajo",
  "porcentaje_sospecha": 0,
  "indicadores_detectados": ["lista de indicios encontrados, máximo 5"],
  "respuestas_sospechosas": [
    {"pregunta_numero": "P1", "razon": "breve explicación del indicio específico"}
  ],
  "conclusion": "Análisis en máximo 120 palabras. Explica qué hace sospechar o qué da confianza."
}
PROMPT;

            // Usar Gemini directamente (misma infraestructura que el resto del servicio)
            $apiKey = config('services.ia.gemini.api_key')
                ?? config('services.gemini.api_key')
                ?? ($this->provider === 'gemini' ? ($this->config['api_key'] ?? null) : null);

            if (!$apiKey) {
                return null;
            }

            $modelos = ['gemini-2.5-flash', 'gemini-2.5-flash-lite'];
            $respuestaTexto = null;

            foreach ($modelos as $modelo) {
                try {
                    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelo}:generateContent?key={$apiKey}";
                    $res = Http::withHeaders(['Content-Type' => 'application/json'])
                        ->timeout(45)
                        ->post($url, [
                            'contents' => [['parts' => [['text' => $prompt]]]],
                            'generationConfig' => ['temperature' => 0.2, 'maxOutputTokens' => 2048],
                        ]);

                    if ($res->successful() && isset($res->json()['candidates'][0]['content']['parts'][0]['text'])) {
                        $respuestaTexto = $res->json()['candidates'][0]['content']['parts'][0]['text'];
                        break;
                    }
                } catch (\Exception) {
                    continue;
                }
            }

            if (!$respuestaTexto) {
                return null;
            }

            // Parsear JSON de la respuesta
            $limpio = trim(preg_replace('/```(?:json)?\s*|\s*```/', '', $respuestaTexto));
            $resultado = json_decode($limpio, true);

            if (json_last_error() !== JSON_ERROR_NONE || !isset($resultado['nivel_sospecha'])) {
                return null;
            }

            Log::info('IADescargoService: análisis de autenticidad completado', [
                'diligencia_id'  => $diligencia->id,
                'nivel_sospecha' => $resultado['nivel_sospecha'],
                'porcentaje'     => $resultado['porcentaje_sospecha'] ?? null,
            ]);

            return $resultado;

        } catch (\Exception $e) {
            Log::warning('IADescargoService::analizarAutenticidadRespuestas', [
                'diligencia_id' => $diligencia->id,
                'error'         => $e->getMessage(),
            ]);
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
