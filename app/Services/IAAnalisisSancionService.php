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

            // Construir el prompt para la IA
            $prompt = $this->construirPromptAnalisisSancion(
                $proceso,
                $trabajador,
                $empresa,
                $historialProcesos,
                $contextoDescargos,
                $sancionesRIT,
                $contextoRITRag,
                $contextoCST
            );

            Log::info('Analizando proceso disciplinario para sugerir sanciones', [
                'proceso_id' => $proceso->id,
                'trabajador_id' => $trabajador->id,
                'cantidad_procesos_previos' => count($historialProcesos),
            ]);

            $analisisTexto = $this->llamarGemini($prompt);

            // Parsear la respuesta de la IA
            $analisis = $this->parsearAnalisisIA($analisisTexto);

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
     * - Subido (DOCX/PDF):      $sancionesRIT = [],  $contextoRITRag = fragmentos RAG relevantes.
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

            // Documento subido: usar RAG sobre el texto completo
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
        $sancionesLaborales = $proceso->sancionesLaborales;

        if ($sancionesLaborales->isEmpty()) {
            return "No se han seleccionado motivos de descargos del reglamento interno.\n";
        }

        $detalle = "";
        foreach ($sancionesLaborales as $index => $sancion) {
            $numero = $index + 1;
            $tipoFalta = strtoupper($sancion->tipo_falta);
            $emoji = $sancion->tipo_falta === 'leve' ? '🟢' : '🔴';
            $tipoSancion = $sancion->tipo_sancion_texto;

            $detalle .= "{$numero}. {$emoji} [{$tipoFalta}] {$sancion->nombre_claro}\n";
            $detalle .= "   Descripción: {$sancion->descripcion}\n";
            $detalle .= "   Sanción del reglamento: {$tipoSancion}\n";

            if ($sancion->tipo_sancion === 'suspension' && $sancion->dias_suspension_texto) {
                $detalle .= "   Días de suspensión según reglamento: {$sancion->dias_suspension_texto}\n";
            }

            // Verificar si es reincidencia
            if ($sancion->esReincidencia()) {
                $detalle .= "   NOTA: Este motivo es una REINCIDENCIA (no es la primera vez)\n";
            }

            $detalle .= "\n";
        }

        return $detalle;
    }

    /**
     * Obtener contexto de descargos si existen
     */
    private function obtenerContextoDescargos(ProcesoDisciplinario $proceso): string
    {
        $diligencia = $proceso->diligenciaDescargo;

        if (!$diligencia) {
            return 'No se han realizado descargos aún.';
        }

        $preguntas = $diligencia->preguntas()
            ->with('respuesta')
            ->ordenadas()
            ->get();

        if ($preguntas->isEmpty()) {
            return 'No hay descargos registrados.';
        }

        $contexto = '';
        foreach ($preguntas as $index => $pregunta) {
            $respuesta = $pregunta->respuesta?->respuesta ?? 'Sin respuesta';
            $contexto .= ($index + 1) . ". {$pregunta->pregunta}\n   Respuesta: {$respuesta}\n\n";
        }

        return $contexto;
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
        string $contextoCST = ''
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

        // Caso B: datos estructurados del wizard (construido_ia)
        } elseif (!empty($sancionesRIT['faltas_leves']) || !empty($sancionesRIT['faltas_graves'])) {
            $seccionRIT  = "\n═══════════════════════════════════════════════════════════════════\n";
            $seccionRIT .= "RÉGIMEN DISCIPLINARIO DEL RIT DE {$empresa->nombre_completo}:\n";
            $seccionRIT .= "═══════════════════════════════════════════════════════════════════\n";

            if (!empty($sancionesRIT['faltas_leves'])) {
                $seccionRIT .= "FALTAS LEVES definidas en el RIT:\n";
                foreach ($sancionesRIT['faltas_leves'] as $f) {
                    $seccionRIT .= "  - {$f}\n";
                }
            }
            if (!empty($sancionesRIT['faltas_graves'])) {
                $seccionRIT .= "FALTAS GRAVES definidas en el RIT:\n";
                foreach ($sancionesRIT['faltas_graves'] as $f) {
                    $seccionRIT .= "  - {$f}\n";
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
            $seccionCST .= "CONTEXTO LEGAL CST — ARTÍCULOS APLICABLES (fuente oficial SUIN-Juriscol):\n";
            $seccionCST .= "═══════════════════════════════════════════════════════════════════\n";
            $seccionCST .= $contextoCST . "\n";
            $seccionCST .= "PROHIBICIÓN ABSOLUTA: Solo puedes citar artículos del CST que aparezcan\n";
            $seccionCST .= "TEXTUALMENTE en el bloque anterior. Nunca menciones artículos que no estén\n";
            $seccionCST .= "aquí aunque los conozcas de tu entrenamiento. Si necesitas un artículo que\n";
            $seccionCST .= "no aparece en el contexto, escribe únicamente \"según el CST\" sin número.\n";
            $seccionCST .= "═══════════════════════════════════════════════════════════════════\n";
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

Determina la(s) sanción(es) jurídicamente válida(s) basándote EXCLUSIVAMENTE en estas fuentes, en este orden de prioridad:

1. EL REGLAMENTO INTERNO DE TRABAJO (RIT) DE LA EMPRESA — fuente primaria: define qué conductas son faltas y qué sanciones contempla.
2. EL CÓDIGO SUSTANTIVO DEL TRABAJO (CST) — solo los artículos del bloque "CONTEXTO LEGAL CST" más abajo. Nunca inventes números de artículos.
3. EL HISTORIAL DISCIPLINARIO DEL TRABAJADOR — reincidencia, agravantes y atenuantes.

INSTRUCCIÓN CRÍTICA (anti-invención): No inventes rangos de días ni categorías de faltas. Deriva TODO del RIT de esta empresa y de los artículos del CST proporcionados. Si el RIT dice "suspensión hasta 8 días", no sugieras 30. Si el RIT no contempla terminación, no la sugieras.

GARANTISMO (obligatorio antes de recomendar cualquier sanción): evalúa y reporta en "verificacion_garantias":
- TIPICIDAD/LEGALIDAD: ¿la conducta está tipificada como falta en el RIT? No se sanciona lo que no está tipificado.
- DEBIDO PROCESO: citación previa, derecho a ser oído y a aportar pruebas (Art. 115 CST si aparece en el contexto). Un defecto de procedimiento anula la sanción.
- INMEDIATEZ: la sanción debe ser oportuna; advierte si los hechos podrían estar caducos.
- NON BIS IN IDEM: no sancionar dos veces el mismo hecho.
- PROPORCIONALIDAD y gradualidad de la medida.
- SUFICIENCIA PROBATORIA: distingue hechos constatables de opiniones; si la prueba es débil, adviértelo.

FUERO / ESTABILIDAD LABORAL REFORZADA (obligatorio): no dispones de datos del trabajador sobre fuero. Si la información (hechos o descargos) sugiere posible fuero —maternidad/lactancia, sindical, salud o discapacidad, prepensionado, acoso laboral (Ley 1010 de 2006)— ALÉRTALO en "alerta_fuero". Y SIEMPRE que se evalúe TERMINACIÓN, marca requiere_verificacion=true: terminar a un trabajador aforado sin permiso del Ministerio del Trabajo o autorización judicial es NULO (reintegro e indemnización).

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
2. Verifica si hay reincidencia en el historial — agrava la sanción conforme al RIT y los artículos del CST proporcionados en el bloque CONTEXTO LEGAL CST.
3. Evalúa los descargos del trabajador — considera atenuantes y argumentos de defensa.
4. De las sanciones que el RIT contempla, selecciona la apropiada. Si el RIT no aporta datos, aplica solo lo que los artículos del CST proporcionados permiten.
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
- sanciones_sugeridas: array con TODOS los tipos de sanción jurídicamente válidos y proporcionales para este caso. Puede ser uno, dos o tres; o VACÍO [] si lo correcto es NO sancionar (descargos que exoneran, prueba insuficiente o falta no tipificada en el RIT). No incluir "no_sancion". Ejemplo: si tanto llamado_atencion como suspension son proporcionales, incluir ambos.
- bases_juridicas: incluir SOLO las sanciones que estén en sanciones_sugeridas. Cada texto (máximo 100 palabras) debe argumentar específicamente esa sanción citando el artículo concreto del RIT o del CST que la sustenta. No repetir el contenido de razonamiento_legal; este campo es la base jurídica puntual de cada opción.
- sancion_principal: el tipo que más se ajusta al caso, debe estar dentro de sanciones_sugeridas.
- sanciones_disponibles: incluye SOLO las sanciones que el RIT contempla. Sin RIT, aplica lo que permite el CST según la gravedad. "multa" solo si el RIT la define explícitamente con monto o porcentaje; de lo contrario, no la incluyas.
- dias_suspension_max_rit: número entero con el MÁXIMO de días que el RIT contempla EXPLÍCITAMENTE para la suspensión aplicable. Si el RIT no especifica días concretos, usa el límite que establezca el artículo correspondiente en el bloque CONTEXTO LEGAL CST proporcionado. Si no aplica suspensión, pon null. NUNCA inventes un valor que no esté en el RIT ni en el bloque CONTEXTO LEGAL CST proporcionado.
- dias_suspension (recomendacion_final): número concreto dentro del rango 1..dias_suspension_max_rit que mejor se ajuste al caso, o null si no hay suspensión.
- La gravedad es "leve", "grave" o "muy_grave" — no hay subcategorías ni niveles. La clasificación la define el RIT.
- Confianza "alta": el RIT clasifica explícitamente esta conducta. "media": se infiere del RIT. "baja": no hay datos del RIT, se aplica solo el CST.
- En "motivos_analizados": incluye CADA motivo seleccionado con su análisis individual.
- Si hay "otro motivo": analisis_otro_motivo.aplica=true y completa TODOS sus campos.
- Si NO hay "otro motivo": analisis_otro_motivo.aplica=false y los demás campos son null.
- razones_no_recomendadas: incluir una clave por CADA sanción que NO esté en sanciones_sugeridas. "no_sancion" SIEMPRE debe aparecer. Para multa: incluir SOLO si está en sanciones_disponibles pero NO en sanciones_sugeridas; si el RIT no contempla multa, no incluir esta clave. Para llamado_atencion, suspension y terminacion: incluir SOLO si NO están en sanciones_sugeridas. Cada texto (máximo 80 palabras), lenguaje claro y directo sin tecnicismos.
- requiere_sancion (recomendacion_final): false si lo correcto es NO sancionar (en ese caso sanciones_sugeridas va vacío y sancion_principal y dias_suspension van null); true si procede al menos una sanción.
- alerta_fuero: requiere_verificacion DEBE ser true siempre que "terminacion" esté en sanciones_disponibles o sanciones_sugeridas, o cuando haya indicios de fuero. En "indicios" no afirmes un fuero que no conste; describe solo la pista o pon "Sin indicios en la información disponible".
- verificacion_garantias: completa SIEMPRE las seis garantías con estado "cumple", "riesgo" o "no_determinable". Usa "no_determinable" cuando la información no permita concluir (no inventes que se cumplió un trámite que no consta). Si alguna está en "riesgo", refléjalo también en consideraciones_especiales.
- Citas precisas: toda cita debe incluir el localizador exacto — número de artículo del CST (del bloque proporcionado) y, para el RIT, el capítulo/artículo/numeral específico. Si no puedes ubicar el localizador exacto, dilo expresamente ("el RIT no precisa el numeral").
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
    private function llamarGemini(string $prompt): string
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
                'maxOutputTokens' => max((int) ($config['max_tokens'] ?? 4096), 16384),
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
            Log::warning('IAAnalisisSancion: respuesta truncada por MAX_TOKENS — considera aumentar maxOutputTokens o reducir el prompt', [
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

            // Construir rango de días 1..N a partir del máximo que indica el RIT
            $maxDias = is_int($analisis['dias_suspension_max_rit']) && $analisis['dias_suspension_max_rit'] > 0
                ? $analisis['dias_suspension_max_rit']
                : null;
            $analisis['dias_suspension_sugeridos'] = $maxDias ? range(1, $maxDias) : [];

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
            'sancion_recomendada' => 'llamado_atencion',
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
                'requiere_sancion' => false,
                'sanciones_sugeridas' => ['llamado_atencion'],
                'sancion_principal' => 'llamado_atencion',
                'dias_suspension' => null,
                'confianza' => 'baja',
                'mensaje_para_decision' => 'El análisis automático no estuvo disponible. Se recomienda revisar manualmente el caso antes de tomar una decisión. Considere los hechos, los motivos seleccionados, el historial del trabajador y los descargos presentados.',
            ],
        ];
    }
}