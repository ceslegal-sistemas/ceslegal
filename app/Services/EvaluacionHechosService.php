<?php

namespace App\Services;

use App\Models\ArticuloLegal;
use App\Models\ProcesoDisciplinario;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EvaluacionHechosService
{
    protected string $provider;
    protected array $config;

    public function __construct()
    {
        $this->provider = config('services.ia.provider', 'openai');
        $this->config   = config("services.ia.{$this->provider}", []);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // API pública
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Genera el primer mensaje de la IA para iniciar la conversación.
     *
     * @return array ['mensaje' => string, 'listo' => false, 'datos' => null]
     */
    public function obtenerMensajeInicial(int $empresaId, string $nombreTrabajador, string $cargo, int $trabajadorId): array
    {
        $systemPrompt = $this->construirSystemPrompt($empresaId, $nombreTrabajador, $cargo, $trabajadorId);

        $instruccion = "El empleador acaba de abrir el formulario de proceso disciplinario para {$nombreTrabajador} ({$cargo}). Saluda brevemente y haz tu primera pregunta para entender qué situación ocurrió.";

        try {
            $respuesta = $this->llamarIA($systemPrompt, [], $instruccion);
            return $this->parsearRespuesta($respuesta);
        } catch (\Exception $e) {
            Log::error('EvaluacionHechosService: error en mensaje inicial', ['error' => $e->getMessage()]);

            return [
                'mensaje' => "Hola. Para documentar el proceso disciplinario de {$nombreTrabajador}, necesito que me cuente ¿qué situación ocurrió?",
                'listo'   => false,
                'datos'   => null,
            ];
        }
    }

    /**
     * Procesa un mensaje del empleador y retorna la siguiente respuesta de la IA.
     *
     * @param  string  $mensajeUsuario   Lo que escribió el empleador
     * @param  array   $historial        [['rol' => 'ia'|'usuario', 'texto' => string], ...]
     * @return array   ['mensaje' => string, 'listo' => bool, 'datos' => array|null]
     */
    public function procesarMensaje(
        string $mensajeUsuario,
        array  $historial,
        int    $empresaId,
        string $nombreTrabajador,
        string $cargo,
        int    $trabajadorId
    ): array {
        $systemPrompt = $this->construirSystemPrompt($empresaId, $nombreTrabajador, $cargo, $trabajadorId);

        try {
            $respuesta = $this->llamarIA($systemPrompt, $historial, $mensajeUsuario);
            return $this->parsearRespuesta($respuesta);
        } catch (\Exception $e) {
            Log::error('EvaluacionHechosService: error procesando mensaje', [
                'empresa_id' => $empresaId,
                'error'      => $e->getMessage(),
            ]);

            return [
                'mensaje' => 'Lo siento, tuve un problema de conexión. ¿Puede repetir su respuesta?',
                'listo'   => false,
                'datos'   => null,
            ];
        }
    }

    /**
     * Genera la redacción jurídica de los hechos a partir de un formulario estructurado (llamada única).
     *
     * @return array{hechos: string, fecha_ocurrencia: string|null, resumen: string}
     */
    public function generarHechosDesdeFormulario(
        array  $datosFormulario,
        int    $empresaId,
        string $nombreTrabajador,
        string $cargo,
        int    $trabajadorId
    ): array {
        $systemPrompt = $this->construirSystemPrompt($empresaId, $nombreTrabajador, $cargo, $trabajadorId);

        $notifico   = $datosFormulario['trabajador_notifico'] ? 'Sí' : 'No';
        $detalle    = $datosFormulario['detalle_notificacion']
            ? "\n  Justificación: " . $datosFormulario['detalle_notificacion']
            : '';
        $lugar      = $datosFormulario['lugar_hecho']
            ? "\n- Lugar: " . $datosFormulario['lugar_hecho']
            : '';
        $evidencias = $datosFormulario['evidencias_disponibles']
            ? "\n- Evidencias: " . $datosFormulario['evidencias_disponibles']
            : '';

        $prompt = <<<PROMPT
Con base en los siguientes datos del formulario, redacta los hechos del proceso disciplinario en lenguaje jurídico-laboral formal colombiano (mínimo 3 párrafos, tercera persona). Incluye los antecedentes del trabajador que tienes en el contexto.

DATOS DEL FORMULARIO:
- Descripción del hecho: {$datosFormulario['descripcion_hecho']}
- Fecha del hecho: {$datosFormulario['fecha_hecho']}{$lugar}
- ¿El trabajador dio aviso o justificación?: {$notifico}{$detalle}{$evidencias}

Responde ÚNICAMENTE en JSON válido sin bloques de código:
{"hechos": "Redacción jurídica completa...", "fecha_ocurrencia": "YYYY-MM-DD o null", "resumen": "Una oración resumen"}
PROMPT;

        $rawJson = $this->llamarIA($systemPrompt, [], $prompt);
        $rawJson = trim(preg_replace(['/^```(?:json)?\s*/m', '/\s*```$/m'], '', $rawJson));
        $datos   = json_decode($rawJson, true, 512, JSON_THROW_ON_ERROR);

        if (empty($datos['hechos'])) {
            throw new \Exception('La IA no devolvió hechos válidos');
        }

        return [
            'hechos'           => $datos['hechos'],
            'fecha_ocurrencia' => $datos['fecha_ocurrencia'] ?? null,
            'resumen'          => $datos['resumen'] ?? '',
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Construcción del prompt
    // ──────────────────────────────────────────────────────────────────────────

    private function construirSystemPrompt(int $empresaId, string $nombreTrabajador, string $cargo, int $trabajadorId): string
    {
        $contextoReglamento    = $this->obtenerContextoReglamento($empresaId);
        $contextoAntecedentes  = $this->obtenerContextoAntecedentes($trabajadorId);
        $hoy                   = now()->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY');

        return <<<SYSTEM
Eres un abogado laboralista experto de CES Legal (Colombia). Estás ayudando al empleador a documentar los hechos de un proceso disciplinario mediante una conversación empática. El empleador no conoce de leyes; tú sí.

FECHA ACTUAL DEL SISTEMA: {$hoy}
Usa esta fecha para resolver expresiones relativas como "ayer", "la semana pasada", "el viernes", etc.

TRABAJADOR: {$nombreTrabajador} — Cargo: {$cargo}

{$contextoAntecedentes}

{$contextoReglamento}

TU MISIÓN: Obtener por conversación TODA la información necesaria para redactar un descargo completo y sólido. Tú eres quien debe garantizar que las preguntas cubran todo — no el empleador. Si una respuesta es vaga o insuficiente, pregunta de nuevo hasta tener el detalle necesario.

ELEMENTOS QUE DEBES TENER ANTES DE FINALIZAR (evalúa cada uno):
1. ¿Qué conducta exactamente ocurrió? (con suficiente detalle para el expediente — no "llegó tarde" sino cuándo, cuánto, cómo se enteró el jefe)
2. ¿Cuándo ocurrió? (fecha exacta o aproximada)
3. ¿El trabajador avisó, pidió permiso o dio alguna justificación antes o después?
4. ¿Hay algún contexto, circunstancia especial o antecedente inmediato que explique lo sucedido?
5. ¿Hay testigos, evidencia física, registros o documentos que soporten los hechos?

REGLA CRÍTICA: Solo marca "listo: true" cuando puedas responder SÍ a los 5 elementos con la información que te dio el empleador. Si alguno es vago o falta, haz UNA pregunta concreta para obtenerlo. No acumules preguntas: una a la vez.

Los antecedentes disciplinarios ya los tienes en el bloque de arriba: no los preguntes al empleador.

Cuando tengas los 5 elementos, redacta los hechos en lenguaje jurídico-laboral formal (mínimo 3 párrafos, tercera persona) e incluye los antecedentes en el párrafo de contexto.

RESPONDE SIEMPRE EN JSON VÁLIDO sin bloques de código:
— Mientras conversas: {"mensaje": "...", "listo": false, "datos": null}
— Al finalizar: {"mensaje": "...", "listo": true, "datos": {"hechos": "...", "fecha_ocurrencia": "YYYY-MM-DD o null", "resumen": "Una oración que resume los hechos"}}
SYSTEM;
    }

    /**
     * Construye un bloque de texto con los datos ya capturados en el formulario,
     * para evitar que la IA los pida de nuevo.
     */
    private function buildDatosCapturadosBloque(array $contexto, string $encabezado = 'DATOS YA CAPTURADOS:'): string
    {
        if (empty($contexto)) return '';
        $lineas = [$encabezado];
        if (!empty($contexto['trabajador_nombre'])) {
            $cargo = !empty($contexto['trabajador_cargo']) ? " ({$contexto['trabajador_cargo']})" : '';
            $lineas[] = "- Trabajador: {$contexto['trabajador_nombre']}{$cargo}";
        }
        if (!empty($contexto['fecha_hecho']))  $lineas[] = "- Fecha del hecho: {$contexto['fecha_hecho']}";
        if (!empty($contexto['hora_hecho']))   $lineas[] = "- Hora aproximada: {$contexto['hora_hecho']}";
        if (!empty($contexto['lugar']))        $lineas[] = "- Lugar: {$contexto['lugar']}";
        if (!empty($contexto['en_horario']))   $lineas[] = "- En horario laboral: {$contexto['en_horario']}";
        return count($lineas) > 1 ? "\n\n" . implode("\n", $lineas) : '';
    }

    private function obtenerContextoReglamento(int $empresaId): string
    {
        $texto = app(ReglamentoInternoService::class)->getTextoReglamento($empresaId);

        if ($texto) {
            $textoLimitado = mb_substr($texto, 0, 12000);
            return "REGLAMENTO INTERNO DE LA EMPRESA (úsalo como contexto para entender la gravedad de la conducta):\n{$textoLimitado}";
        }

        return "NOTA: Esta empresa no tiene Reglamento Interno cargado. Usa el Código Sustantivo del Trabajo como marco de referencia.";
    }

    private function obtenerContextoAntecedentes(int $trabajadorId): string
    {
        $etiquetasEstado = [
            'apertura'               => 'En apertura',
            'descargos_pendientes'   => 'Citación enviada',
            'descargos_realizados'   => 'Descargos realizados',
            'sancion_emitida'        => 'Sanción emitida',
            'impugnacion_realizada'  => 'Impugnación realizada',
            'cerrado'                => 'Proceso cerrado',
            'archivado'              => 'Archivado',
        ];

        $etiquetasSancion = [
            'llamado_atencion' => 'Llamado de atención',
            'suspension'       => 'Suspensión sin goce de salario',
            'despido'          => 'Despido con justa causa',
        ];

        $procesos = ProcesoDisciplinario::where('trabajador_id', $trabajadorId)
            ->orderBy('created_at', 'desc')
            ->get(['codigo', 'estado', 'tipo_sancion', 'fecha_ocurrencia', 'hechos', 'created_at']);

        if ($procesos->isEmpty()) {
            return "ANTECEDENTES DEL SISTEMA (base de datos — NO preguntes al empleador):\nSin antecedentes. Es el primer proceso disciplinario registrado para este trabajador.";
        }

        $lineas = ["ANTECEDENTES DEL SISTEMA (base de datos — NO preguntes al empleador):"];
        $lineas[] = "Este trabajador tiene {$procesos->count()} proceso(s) disciplinario(s) previo(s):";

        foreach ($procesos as $i => $p) {
            $num     = $i + 1;
            $fecha   = $p->fecha_ocurrencia
                ? \Carbon\Carbon::parse($p->fecha_ocurrencia)->format('d/m/Y')
                : ($p->created_at ? $p->created_at->format('d/m/Y') : 'fecha no registrada');
            $estado  = $etiquetasEstado[$p->estado] ?? $p->estado;
            $sancion = isset($p->tipo_sancion) && isset($etiquetasSancion[$p->tipo_sancion])
                ? $etiquetasSancion[$p->tipo_sancion]
                : 'Sin sanción registrada';
            $resumen = $p->hechos
                ? mb_substr(strip_tags($p->hechos), 0, 200)
                : 'Sin descripción';

            $lineas[] = "  {$num}. [{$p->codigo}] Ocurrencia: {$fecha} — Estado: {$estado} — {$sancion}";
            $lineas[] = "     Hechos: {$resumen}";
        }

        return implode("\n", $lineas);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Parseo de respuesta
    // ──────────────────────────────────────────────────────────────────────────

    private function parsearRespuesta(string $respuesta): array
    {
        $respuesta = trim($respuesta);

        // Quitar bloques de código markdown si el LLM los genera
        $respuesta = preg_replace('/^```(?:json)?\s*/m', '', $respuesta);
        $respuesta = preg_replace('/\s*```$/m', '', $respuesta);
        $respuesta = trim($respuesta);

        try {
            $datos = json_decode($respuesta, true, 512, JSON_THROW_ON_ERROR);

            $listo = (bool) ($datos['listo'] ?? false);

            return [
                'mensaje' => $datos['mensaje'] ?? 'Continúe describiendo la situación.',
                'listo'   => $listo,
                'datos'   => $listo ? ($datos['datos'] ?? null) : null,
            ];
        } catch (\JsonException) {
            Log::warning('EvaluacionHechosService: respuesta no es JSON válido', [
                'respuesta' => substr($respuesta, 0, 400),
            ]);

            // Fallback: usar la respuesta cruda como mensaje
            return [
                'mensaje' => $respuesta ?: 'Por favor, continúe describiendo la situación.',
                'listo'   => false,
                'datos'   => null,
            ];
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Mejora de redacción (asistente de escritura)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Toma un borrador en lenguaje coloquial y devuelve una versión expandida,
     * factual y objetiva (sin lenguaje jurídico aún — eso lo hace generarHechos).
     */
    public function mejorarRedaccion(string $textoBorrador, int $empresaId = 0, array $contexto = []): string
    {
        $contextoReglamento = $empresaId > 0
            ? $this->obtenerContextoReglamento($empresaId)
            : 'NOTA: Usa el Código Sustantivo del Trabajo colombiano como marco de referencia.';

        // Build "datos conocidos" block so the AI doesn't mark them as [COMPLETAR]
        $datosConocidos = '';
        if (!empty($contexto)) {
            $lineas = ['DATOS YA CONOCIDOS DEL FORMULARIO (úsalos directamente — NO uses [COMPLETAR] para estos):'];
            if (!empty($contexto['trabajador_nombre'])) {
                $cargo = !empty($contexto['trabajador_cargo']) ? " — Cargo: {$contexto['trabajador_cargo']}" : '';
                $lineas[] = "- Trabajador: {$contexto['trabajador_nombre']}{$cargo}";
            }
            if (!empty($contexto['fecha_hecho'])) {
                $lineas[] = "- Fecha del hecho: {$contexto['fecha_hecho']}";
            }
            if (!empty($contexto['hora_hecho'])) {
                $lineas[] = "- Hora aproximada: {$contexto['hora_hecho']}";
            }
            if (!empty($contexto['lugar'])) {
                $lineas[] = "- Lugar: {$contexto['lugar']}";
            }
            $datosConocidos = "\n\n" . implode("\n", $lineas);
        }

        $system = <<<SYSTEM
Eres un especialista en documentación de procesos disciplinarios laborales colombianos.

CONTEXTO NORMATIVO (solo para identificar la norma aplicable):
{$contextoReglamento}{$datosConocidos}

TAREA: Reescribir el borrador del empleador de forma ESPECÍFICA y ÚTIL para el expediente disciplinario.

REGLAS CRÍTICAS:
1. Conserva todos los hechos CONCRETOS que ya están escritos.
2. NO amplíes con frases genéricas como "omitió sus funciones" o "no cumplió sus responsabilidades" — eso no sirve en un expediente.
3. Usa los DATOS YA CONOCIDOS listados arriba directamente en el texto. Donde falte información DESCONOCIDA que el proceso necesita, usa: [COMPLETAR: descripción breve de qué dato falta].
4. Al final del texto, en una línea separada, escribe: "Norma aplicable: [artículo o numeral concreto del reglamento interno o del CST que aplica]"
5. Tercera persona, tono factual y objetivo.
6. Máximo 220 palabras.

DATOS QUE SIEMPRE DEBEN APARECER (con [COMPLETAR] solo si no están en DATOS YA CONOCIDOS):
- Fecha y hora exacta del hecho
- Lugar específico dentro de la empresa
- Cómo se enteró el supervisor o la empresa
- Si el trabajador notificó previamente o dio alguna justificación
- Consecuencia concreta para la empresa u operación

FORMATO DE SALIDA:
- Solo texto en párrafos. Sin JSON, sin listas, sin asteriscos, sin encabezados.
SYSTEM;

        try {
            $raw = $this->llamarIA($system, [], "Borrador del empleador:\n{$textoBorrador}", textoPlano: true, modeloRapido: true);
            return trim($this->extraerTextoPlano($raw));
        } catch (\Exception $e) {
            Log::error('EvaluacionHechosService::mejorarRedaccion', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Para cada marcador [COMPLETAR: ...] en el texto genera 3 opciones cortas.
     * Retorna array de ['marker' => '[COMPLETAR: ...]', 'label' => '...', 'opciones' => [...]]
     */
    public function generarSugerenciasCompletado(string $texto, array $contexto = []): array
    {
        preg_match_all('/\[COMPLETAR:\s*([^\]]+)\]/', $texto, $matches, PREG_SET_ORDER);
        if (empty($matches)) return [];

        // Palabras clave de datos ya capturados — no generar sugerencias para ellos
        $excluirSiContexto = [];
        if (!empty($contexto['fecha_hecho']))       $excluirSiContexto[] = 'fecha';
        if (!empty($contexto['hora_hecho']))        $excluirSiContexto[] = 'hora';
        if (!empty($contexto['lugar']))             $excluirSiContexto[] = ['lugar', 'ubicación', 'ubicacion', 'sitio'];
        if (!empty($contexto['trabajador_nombre'])) $excluirSiContexto[] = ['nombre', 'trabajador', 'nombre completo'];
        $excluirSiContexto = array_merge(...array_map(
            fn($v) => is_array($v) ? $v : [$v],
            $excluirSiContexto
        ));

        // Deduplicar por label y excluir datos ya capturados
        $vistos = [];
        $campos = [];
        foreach ($matches as $m) {
            $label = trim($m[1]);
            if (isset($vistos[$label])) continue;
            // Omitir si el label menciona datos ya capturados
            $labelLower = mb_strtolower($label);
            $omitir = false;
            foreach ($excluirSiContexto as $kw) {
                if (str_contains($labelLower, $kw)) { $omitir = true; break; }
            }
            if ($omitir) continue;
            $vistos[$label] = true;
            $campos[] = ['marker' => $m[0], 'label' => $label];
        }

        $lista = implode("\n", array_map(
            fn($i, $c) => ($i + 1) . '. ' . $c['label'],
            array_keys($campos),
            $campos
        ));

        $system = <<<SYS
Eres asistente de procesos disciplinarios laborales colombianos.
Para cada campo faltante, genera 3 opciones MUY CORTAS (máx. 6 palabras), concretas y plausibles en español.
Responde SOLO JSON válido:
[{"idx":1,"opciones":["op1","op2","op3"]},{"idx":2,"opciones":["op1","op2","op3"]}]
SYS;

        $user = "Texto:\n{$texto}\n\nCampos:\n{$lista}";

        try {
            $raw = $this->llamarIA($system, [], $user, textoPlano: false, modeloRapido: true);
            // Extraer array JSON aunque venga envuelto
            preg_match('/\[.*\]/s', $raw, $jm);
            $decoded = json_decode($jm[0] ?? '[]', true);
            if (!is_array($decoded)) return [];

            foreach ($decoded as $item) {
                $i = (int)($item['idx'] ?? 0) - 1;
                if (isset($campos[$i])) {
                    $campos[$i]['opciones'] = array_slice((array)($item['opciones'] ?? []), 0, 3);
                }
            }

            return array_values(array_filter($campos, fn($c) => !empty($c['opciones'])));
        } catch (\Exception $e) {
            Log::warning('generarSugerenciasCompletado falló', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Analiza el texto dictado y devuelve 1-2 frases de retroalimentación
     * indicando qué elementos narrativos faltarían para fortalecer el caso.
     */
    public function darFeedbackDictado(string $texto, int $empresaId = 0, array $contexto = []): string
    {
        if (mb_strlen(trim($texto)) < 30) {
            return '';
        }

        $contextoReglamento = $empresaId > 0
            ? $this->obtenerContextoReglamento($empresaId)
            : 'NOTA: Usa el Código Sustantivo del Trabajo colombiano como marco de referencia.';

        // Construir bloque de datos ya capturados
        $datosYaCapturados = $this->buildDatosCapturadosBloque($contexto,
            'DATOS YA REGISTRADOS EN EL FORMULARIO (NO pidas estos datos — ya están capturados):'
        );

        // RAG: recuperar artículos legales semánticamente relevantes al relato
        $normasRag = $this->buscarNormasRelevantes($texto);
        $normasBloque = $normasRag
            ? "NORMAS LEGALES RECUPERADAS (extractos reales de la base de datos — cita SOLO estas, con su texto exacto):\n{$normasRag}"
            : <<<NORMAS
TABLA DE NORMAS APLICABLES (usa SOLO estas — no inventes artículos):
- Acoso laboral (hostigamiento, intimidación, maltrato psicológico): Ley 1010 de 2006
- Acoso sexual en el trabajo: Ley 1257 de 2008 + Art. 62 num. 3 CST
- Violencia física, amenazas o agresiones a compañeros/jefes: Art. 62 num. 2 CST
- Hurto, apropiación indebida de bienes de la empresa: Art. 60 num. 1 + Art. 62 num. 6 CST
- Insubordinación o desobediencia reiterada: Art. 62 num. 4 CST
- Embriaguez o consumo de drogas en el trabajo: Art. 60 num. 2 + Art. 62 num. 6 CST
- Abandono del puesto o ausencia sin justificar: Art. 62 num. 6 CST
- Incumplimiento grave de obligaciones: Art. 62 num. 10 CST
- Si hay reglamento interno aplicable, cita primero el artículo del reglamento.
- Si no estás seguro del artículo exacto, NO lo cites — enfócate en la conducta.
NORMAS;

        $system = <<<SYSTEM
Eres un abogado laboralista colombiano con 15 años en procesos disciplinarios.

CONTEXTO NORMATIVO:
{$contextoReglamento}{$datosYaCapturados}

{$normasBloque}

Evalúa el relato y señala EL criterio más urgente que falta.
ENFÓCATE SOLO en lo que falta — NO menciones datos ya capturados arriba:
1. CONDUCTA CONCRETA: ¿Es específica? "No cumplió funciones" no sirve — ¿qué hizo exactamente?
2. IMPACTO: ¿Se menciona consecuencia real para la empresa, el equipo o el servicio?
3. PRUEBAS: ¿Hay testigos, cámara, correo, registro u otro soporte que obtener?
4. HISTORIAL: ¿Es reincidente? ¿Hubo llamado de atención anterior?

Responde con 1 o 2 frases directas. Cita norma SOLO si está en las normas listadas arriba y aplica con certeza.
Si el relato ya está completo, confírmalo brevemente.

REGLAS ABSOLUTAS:
- SOLO el texto que se leerá en voz alta. Sin saludos, sin listas, sin numeración.
- Máximo 2 frases. Español colombiano profesional y directo.
- PROHIBIDO: JSON, llaves {}, corchetes [], XML, formatos especiales.
- Comienza directamente con la recomendación.
SYSTEM;

        try {
            $raw = $this->llamarIA($system, [], $texto, textoPlano: true, modeloRapido: true);
            return trim($this->extraerTextoPlano($raw));
        } catch (\Exception $e) {
            Log::error('EvaluacionHechosService::darFeedbackDictado', ['error' => $e->getMessage()]);
            return '';
        }
    }

    /**
     * Si el modelo devuelve JSON a pesar de las instrucciones, extrae el texto del primer campo string.
     */
    private function extraerTextoPlano(string $raw): string
    {
        $raw = trim($raw);

        // Si no parece JSON, devolver tal cual
        if (!str_starts_with($raw, '{') && !str_starts_with($raw, '[')) {
            return $raw;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return $raw;
        }

        // Si es un array de objetos (e.g. [{borrador,reescrito}, ...]), tomar el primer elemento
        if (isset($decoded[0]) && is_array($decoded[0])) {
            $decoded = $decoded[0];
        }

        // Buscar el primer valor string largo en el objeto
        $candidatos = ['reescrito', 'suggestion', 'mensaje', 'feedback', 'message', 'texto', 'text', 'content', 'respuesta', 'resultado'];
        foreach ($candidatos as $key) {
            if (!empty($decoded[$key]) && is_string($decoded[$key])) {
                return $decoded[$key];
            }
        }

        // Fallback: primer string largo del array/objeto
        foreach ($decoded as $valor) {
            if (is_string($valor) && mb_strlen($valor) > 20) {
                return $valor;
            }
        }

        return $raw;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // RAG — Recuperación de normas legales por similitud semántica
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Recupera los artículos legales más relevantes para el texto dado,
     * usando cosine similarity sobre los embeddings almacenados en la BD.
     *
     * @return string Bloque de texto con los artículos encontrados, listo para inyectar en prompt.
     *                Cadena vacía si no hay embeddings o hay error.
     */
    private function buscarNormasRelevantes(string $texto, int $limite = 4): string
    {
        try {
            $queryEmbedding = $this->obtenerEmbeddingTexto($texto);
            if (!$queryEmbedding) {
                return '';
            }

            $articulos = ArticuloLegal::whereNotNull('embedding')->activos()->get();
            if ($articulos->isEmpty()) {
                return '';
            }

            $scored = [];
            foreach ($articulos as $articulo) {
                $emb = $articulo->embedding; // cast 'array' ya decodifica el JSON
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

            // Solo incluir artículos con similitud significativa (>= 0.55)
            $top = array_filter(
                array_slice($scored, 0, $limite),
                fn($s) => $s['score'] >= 0.55
            );

            if (empty($top)) {
                return '';
            }

            $lineas = [];
            foreach ($top as $item) {
                $art      = $item['articulo'];
                $textoArt = $art->getRawOriginal('texto_completo') ?? $art->descripcion ?? '';
                $fuente   = $art->fuente ? " — {$art->fuente}" : '';
                $lineas[] = "[{$art->codigo}{$fuente}]";
                $lineas[] = $art->titulo;
                if ($textoArt) {
                    $lineas[] = mb_substr($textoArt, 0, 600);
                }
                $lineas[] = '';
            }

            return trim(implode("\n", $lineas));
        } catch (\Exception $e) {
            Log::warning('EvaluacionHechosService::buscarNormasRelevantes', ['error' => $e->getMessage()]);
            return '';
        }
    }

    /**
     * Genera el embedding vectorial de un texto de consulta (RETRIEVAL_QUERY)
     * usando Gemini gemini-embedding-001.
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
                'content'  => ['parts' => [['text' => $texto]]],
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

    // ──────────────────────────────────────────────────────────────────────────
    // Llamadas a IA — patrón multi-turno con system prompt
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * @param bool $textoPlano  true = respuesta texto libre (mejorar/feedback), false = JSON (conversación principal)
     * @param bool $modeloRapido true = usar modelo flash/rápido en vez del principal
     */
    private function llamarIA(string $systemPrompt, array $historial, string $mensajeActual, bool $textoPlano = false, bool $modeloRapido = false): string
    {
        return match ($this->provider) {
            'openai'    => $this->llamarOpenAI($systemPrompt, $historial, $mensajeActual, $textoPlano),
            'anthropic' => $this->llamarAnthropic($systemPrompt, $historial, $mensajeActual),
            'gemini'    => $this->llamarGemini($systemPrompt, $historial, $mensajeActual, $textoPlano, $modeloRapido),
            default     => throw new \Exception("Proveedor de IA no soportado: {$this->provider}"),
        };
    }

    private function llamarOpenAI(string $systemPrompt, array $historial, string $mensajeActual, bool $textoPlano = false): string
    {
        $messages = [['role' => 'system', 'content' => $systemPrompt]];

        foreach ($historial as $entrada) {
            $messages[] = [
                'role'    => $entrada['rol'] === 'ia' ? 'assistant' : 'user',
                'content' => $entrada['texto'],
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $mensajeActual];

        $payload = [
            'model'       => $this->config['model'],
            'messages'    => $messages,
            'max_tokens'  => $this->config['max_tokens'] ?? 1500,
            'temperature' => 0.3,
        ];

        // JSON mode solo cuando se espera JSON estructurado
        if (!$textoPlano && str_contains($this->config['model'] ?? '', 'gpt')) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->config['api_key'],
            'Content-Type'  => 'application/json',
        ])->timeout(30)->post('https://api.openai.com/v1/chat/completions', $payload);

        if (!$response->successful()) {
            throw new \Exception("Error en API OpenAI: " . $response->body());
        }

        return $response->json('choices.0.message.content');
    }

    private function llamarAnthropic(string $systemPrompt, array $historial, string $mensajeActual): string
    {
        $messages = [];

        foreach ($historial as $entrada) {
            $messages[] = [
                'role'    => $entrada['rol'] === 'ia' ? 'assistant' : 'user',
                'content' => $entrada['texto'],
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $mensajeActual];

        $response = Http::withHeaders([
            'x-api-key'         => $this->config['api_key'],
            'anthropic-version' => '2023-06-01',
            'Content-Type'      => 'application/json',
        ])->timeout(30)->post('https://api.anthropic.com/v1/messages', [
            'model'      => $this->config['model'],
            'max_tokens' => $this->config['max_tokens'] ?? 1500,
            'system'     => $systemPrompt,
            'messages'   => $messages,
        ]);

        if (!$response->successful()) {
            throw new \Exception("Error en API Anthropic: " . $response->body());
        }

        return $response->json('content.0.text');
    }

    private function llamarGemini(string $systemPrompt, array $historial, string $mensajeActual, bool $textoPlano = false, bool $modeloRapido = false): string
    {
        $apiKey = $this->config['api_key'];

        // Para tareas rápidas (feedback, mejora de texto) usar flash; para conversación principal usar el modelo configurado
        if ($modeloRapido) {
            $baseModel = $this->config['model'] ?? 'gemini-2.5-pro';
            $model = str_contains($baseModel, 'flash') ? $baseModel : 'gemini-2.5-flash';
        } else {
            $model = $this->config['model'];
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $contents = [];

        foreach ($historial as $entrada) {
            $contents[] = [
                'role'  => $entrada['rol'] === 'ia' ? 'model' : 'user',
                'parts' => [['text' => $entrada['texto']]],
            ];
        }

        $contents[] = [
            'role'  => 'user',
            'parts' => [['text' => $mensajeActual]],
        ];

        $generationConfig = [
            'temperature'     => 0.3,
            'maxOutputTokens' => $this->config['max_tokens'] ?? 1500,
        ];

        // JSON mode solo para la conversación principal que espera JSON estructurado
        if (! $textoPlano) {
            $generationConfig['responseMimeType'] = 'application/json';
        }

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->timeout($modeloRapido ? 20 : 60)->post($url, [
            'system_instruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'contents'         => $contents,
            'generationConfig' => $generationConfig,
        ]);

        if (!$response->successful()) {
            throw new \Exception("Error en API Gemini: " . $response->body());
        }

        $data = $response->json();

        if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            throw new \Exception("Respuesta de Gemini sin contenido válido");
        }

        return $data['candidates'][0]['content']['parts'][0]['text'];
    }
}
