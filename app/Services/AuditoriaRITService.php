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
 * Fuente normativa: ÚNICAMENTE articulos_legales (scrapeados).
 * - codigos_obligatorios: lookup exacto por código (fuente primaria).
 * - buscarArticulosPorTema(): RAG por palabras clave (misma fuente que el generador).
 * No se usa BibliotecaLegalService para evitar incorporar fragmentos externos no controlados.
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
            // Muchos RIT separan "Admisión" y "Período de Prueba" en dos capítulos
            // consecutivos (en vez de uno combinado, como el estándar del generador
            // propio) - captura ambos para no dejar fuera el segundo si el primero
            // que coincide por palabra clave es "Admisión".
            'num_capitulos'        => 2,
        ],
        'jornada' => [
            'titulo'               => 'Jornada Laboral y Horas Extras',
            'query'                => 'jornada laboral horas extras trabajo nocturno dominicales festivos trabajo suplementario recargo descanso dominical remunerado trabajo habitual domingo',
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
            // Muchos RIT ponen la prohibición de deducciones no autorizadas en un
            // capítulo general de "Prohibiciones" (junto con prohibiciones de otros
            // temas), no en el propio capítulo de salario - sin esto, la sección
            // "salario" nunca ve ese artículo y lo marca "falta" aunque exista.
            'capitulos_extra'      => ['PROHIBICIONES ESPECIALES', 'PROHIBICIONES A LOS EMPLEADORES', 'PROHIBICIONES'],
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
            'query'                => 'seguridad salud trabajo SG-SST obligaciones empleador COPASST vigía EPP accidentes laborales exámenes médicos responsabilidades trabajadores',
            'codigos_obligatorios' => ['Art. 56 CST', 'Art. 57 CST'],
            'palabras_clave'       => ['seguridad', 'salud', 'riesgo', 'accidente', 'SST', 'ARL', 'EPP', 'alcoholemia', 'psicoactiv', 'médico'],
            // Incluye títulos de capítulo de RIT antiguos, previos a la terminología
            // "SG-SST" (p. ej. "SERVICIO MÉDICO, MEDIDAS DE SEGURIDAD, RIESGOS
            // LABORALES..."), para no caer al método de respaldo por palabras clave
            // (que no delimita capítulo y puede arrastrar la mitad del documento).
            'capitulos'            => ['SEGURIDAD Y SALUD', 'SG-SST', 'SST', 'RIESGOS LABORALES', 'MEDIDAS DE SEGURIDAD'],
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
            // Art. 236-238 (duración licencia maternidad/paternidad) + 239-241A (fuero, no despido)
            'codigos_obligatorios' => ['Art. 236 CST', 'Art. 237 CST', 'Art. 238 CST', 'Art. 239 CST', 'Art. 240 CST', 'Art. 241 CST', 'Art. 241A CST'],
            'palabras_clave'       => ['maternidad', 'paternidad', 'embarazo', 'discapacidad', 'fuero', 'sindical', 'sujetos especial'],
            'capitulos'            => ['SUJETOS DE ESPECIAL', 'ESPECIAL PROTECCIÓN', 'GRUPOS PROTEGIDOS', 'TRABAJADORES PROTEGIDOS'],
            // El contenido de maternidad/paternidad también está en Cap VII (LICENCIAS ESPECIALES)
            'capitulos_extra'      => ['LICENCIAS ESPECIALES', 'LICENCIAS'],
        ],
    ];

    public function __construct(
        private RITGeneratorService $ritGenerator,
    ) {}

    /**
     * Crea el registro de auditoría en estado 'pendiente'.
     * El procesamiento real lo hace procesarAuditoria() (llamado desde Job o síncronamente).
     */
    public function iniciar(Empresa $empresa, ?string $textoExternoRIT = null): AuditoriaRIT
    {
        // El RIT VIGENTE es el marcado activo=true (mismo criterio que usa el resto del
        // sistema: MiReglamentoInterno, ReglamentoInternoService::procesarDocumento()).
        // Sin este filtro, "Nueva auditoría" podía terminar auditando el RIT MEJORADO
        // (fuente='mejora_ia', activo=false hasta que se adopta) en vez del vigente, si
        // ese mejorado tenía un updated_at más reciente - dando un score distinto (más
        // alto, por tratarse de un documento ya corregido) al mismo botón en otra página
        // que sí filtraba correctamente. El fallback sin filtro cubre el caso borde de
        // que, por algún motivo, ningún registro esté marcado activo todavía.
        $rit = ReglamentoInterno::where('empresa_id', $empresa->id)
            ->where('activo', true)
            ->orderByDesc('updated_at')
            ->first()
            ?? ReglamentoInterno::where('empresa_id', $empresa->id)
                ->orderByDesc('updated_at')
                ->first();

        // 'externo' si: se subió un archivo externo al momento de auditar, O
        // si el RIT fue cargado manualmente durante el registro (fuente='subido').
        $fuente = ($textoExternoRIT || $rit?->fuente === 'subido') ? 'externo' : 'sistema';

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
     * Crea una auditoría TEMPORAL (sin empresa) a partir del texto de un RIT.
     * Se usa en el wizard de registro de empresa: se audita el RIT subido ANTES de que
     * la empresa exista; al crearla, se enlaza con enlazarConEmpresa().
     */
    public function iniciarDesdeTexto(string $texto, string $razonSocial, ?int $userId = null): AuditoriaRIT
    {
        $auditoria = AuditoriaRIT::create([
            'empresa_id'            => null,
            'estado'                => 'pendiente',
            'fuente'                => 'externo',
            'texto_auditado'        => $texto,
            'razon_social_snapshot' => $razonSocial,
            'iniciado_por_user_id'  => $userId,
        ]);

        cache()->put("auditoria_rit_texto_{$auditoria->id}", $texto, now()->addHours(2));

        return $auditoria;
    }

    /**
     * Enlaza una auditoría temporal (empresa_id null) con la empresa recién creada.
     * La persistencia del RIT ya la hace procesarDocumento() en CreateEmpresa::afterCreate;
     * aquí solo se asocia la empresa y su reglamento vigente. Idempotente.
     */
    public function enlazarConEmpresa(AuditoriaRIT $auditoria, Empresa $empresa): void
    {
        $rit = ReglamentoInterno::where('empresa_id', $empresa->id)
            ->orderByDesc('updated_at')
            ->first();

        $auditoria->update([
            'empresa_id'            => $empresa->id,
            'reglamento_interno_id' => $rit?->id ?? $auditoria->reglamento_interno_id,
        ]);
    }

    /**
     * Procesa la auditoría sección por sección.
     * Actualiza el registro en BD después de cada sección para mostrar progreso en tiempo real.
     */
    public function procesarAuditoria(AuditoriaRIT $auditoria): void
    {
        // Reanudable: si el worker re-tomó el job (p. ej. por el retry_after de la cola
        // o por un reinicio del proceso en hosting compartido), se conserva el progreso
        // previo y se continúa en vez de reiniciar la auditoría desde 0/8.
        $secciones = is_array($auditoria->secciones) ? $auditoria->secciones : [];
        $auditoria->update(['estado' => 'procesando', 'secciones' => $secciones]);

        try {
            // Razón social: de la empresa si ya está enlazada, o del snapshot capturado
            // cuando la auditoría se creó desde el wizard (aún sin empresa).
            $razonSocial = $auditoria->razonSocialParaAuditoria();

            // Obtener texto del RIT
            // Nota: texto_auditado persiste el texto en BD como fuente primaria/fallback.
            // Para fuente 'externo' la caché puede no existir si el worker arrancó tarde.
            $textoRIT = $auditoria->fuente === 'externo'
                ? (cache()->pull("auditoria_rit_texto_{$auditoria->id}", '') ?: ($auditoria->texto_auditado ?? ''))
                : ($auditoria->texto_auditado ?: ($auditoria->reglamento?->texto_completo ?? ''));

            if (empty(trim($textoRIT))) {
                throw new \RuntimeException('No se encontró texto del RIT para auditar.');
            }

            foreach (self::SECCIONES as $clave => $config) {
                // Reanudación: saltar secciones ya resueltas con éxito en una pasada previa.
                if (isset($secciones[$clave]) && ($secciones[$clave]['calificacion'] ?? '') !== 'Error') {
                    continue;
                }

                Log::info("AuditoriaRIT: procesando sección '{$config['titulo']}'", [
                    'auditoria_id' => $auditoria->id,
                ]);

                try {
                    $resultado = $this->auditarSeccion(
                        textoRIT: $textoRIT,
                        config: $config,
                        razonSocial: $razonSocial,
                        seccion: $clave,
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
                            razonSocial: $razonSocial,
                            seccion:     $clave,
                        );
                        Log::info("AuditoriaRIT: sección '{$clave}' recuperada en segunda pasada");
                    } catch (\Throwable $e) {
                        Log::error("AuditoriaRIT: sección '{$clave}' falló también en segunda pasada", [
                            'error' => substr($e->getMessage(), 0, 300),
                        ]);
                    }
                    $auditoria->update(['secciones' => $secciones]);
                }

            }

            // Score final calculado desde el estado acumulado de las secciones
            // (robusto ante reanudaciones: no depende de un acumulador en el bucle).
            $numSecciones = count(self::SECCIONES);
            $scoreTotal   = collect($secciones)->sum(fn($s) => $s['score'] ?? 0);
            $scoreGeneral = $numSecciones > 0 ? (int) round($scoreTotal / $numSecciones) : 0;
            $resumen      = $this->generarResumen($secciones, $razonSocial, $scoreGeneral);

            $auditoria->update([
                'estado'          => 'completado',
                'score'           => $scoreGeneral,
                'resumen_general' => $resumen,
                'secciones'       => $secciones,
            ]);

            Log::info("AuditoriaRIT: completada con score {$scoreGeneral}/100", [
                'auditoria_id' => $auditoria->id,
                'empresa_id'   => $auditoria->empresa_id,
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
     * Audita una sección temática del RIT usando exclusivamente articulos_legales.
     */
    private function auditarSeccion(string $textoRIT, array $config, string $razonSocial, string $seccion = ''): array
    {
        // 1. Extraer fragmento relevante del RIT para esta sección (sin enviar todo el documento)
        $fragmentoRIT = $this->extraerFragmentoRIT(
            $textoRIT,
            $config['palabras_clave'],
            $config['capitulos'] ?? [],
            $config['num_capitulos'] ?? 1
        );

        // Capítulos extra: secciones cuyo contenido está dividido en varios capítulos del RIT
        // (ej: protección de maternidad está en Cap XV y también en Cap VII Licencias).
        if (!empty($config['capitulos_extra'])) {
            $fragmentoExtra = $this->extraerFragmentoRIT($textoRIT, [], $config['capitulos_extra'], 1);
            if (!empty(trim($fragmentoExtra))) {
                $fragmentoRIT = trim($fragmentoRIT . "\n\n" . $fragmentoExtra);
            }
        }

        // 2. Contexto normativo - espejo del generador:
        //    2a. Artículos por código exacto (mismos que usa el generador para esta sección).
        $codigosObligatorios   = $config['codigos_obligatorios'] ?? [];
        $articulosObligatorios = !empty($codigosObligatorios)
            ? $this->ritGenerator->obtenerArticulosObligatorios($codigosObligatorios)
            : '';

        //    2b. RAG semántico complementario - excluye los ya obtenidos por código exacto.
        //        También excluir el formato alternativo "Artículo." para evitar duplicados del scraper.
        $articuloCodes       = array_map(fn($c) => preg_replace('/^Art\./', 'Artículo.', $c), $codigosObligatorios);
        $codigosParaExcluir  = array_unique(array_merge($codigosObligatorios, $articuloCodes));
        $limiteSematico      = !empty($codigosObligatorios) ? 6 : 12;
        $articulosSemant     = $this->ritGenerator->buscarArticulosPorEmbedding(
            queryTema:   $config['query'],
            yaObtenidos: $codigosParaExcluir,
            limite:      $limiteSematico,
            umbral:      0.35,
        );

        // Combinar: exactos primero (alta precisión), semánticos después (cobertura complementaria).
        $articulosCst = trim($articulosObligatorios . ($articulosSemant ? "\n\n" . $articulosSemant : ''));

        // 3. Sin normativa → abortar
        if (empty(trim($articulosCst))) {
            Log::warning("AuditoriaRIT: sin normativa en articulos_legales para '{$config['titulo']}'");
            return [
                'titulo'               => $config['titulo'],
                'cumple'               => false,
                'calificacion'         => 'Sin base normativa',
                'score'                => 0,
                'hallazgos'            => ['No se encontró normativa para auditar esta sección. Ejecute el scraper de artículos legales.'],
                'recomendaciones'      => ['Ejecute php artisan cst:scraper para poblar la base normativa.'],
                'articulos_referencia' => [],
                'seccion_encontrada'   => !empty(trim($fragmentoRIT)),
            ];
        }

        // 4. Construir prompt - única fuente: articulos_legales (scrapeados)
        $seccionEncontrada = !empty(trim($fragmentoRIT));
        $contextoRIT = $seccionEncontrada
            ? "TEXTO DEL RIT - SECCIÓN RELEVANTE:\n{$fragmentoRIT}"
            : "TEXTO DEL RIT: Esta sección NO fue encontrada en el documento - calificar como Ausente.";

        $seccionArticulos = "\nCONTEXTO LEGAL (normativa colombiana vigente - ÚNICA referencia normativa válida para esta auditoría):\n{$articulosCst}\n";

        // Estándar de oro: elementos de contenido que un RIT de primer nivel debe cubrir.
        // Se evalúan UNO POR UNO (no como lista libre de hallazgos) para poder mostrar un
        // panel de análisis de brechas: qué está cubierto, qué falta, y su corrección al lado.
        $goldItems = \App\Support\RitGoldStandard::paraSeccion($seccion);
        $numItems  = count($goldItems);

        $reglasComunes = <<<PROMPT
Eres un auditor legal que revisa el Reglamento Interno de Trabajo de "{$razonSocial}".

REGLA FUNDAMENTAL - ANTI-ALUCINACIÓN (INCUMPLIRLA INVALIDA LA AUDITORÍA):

PROHIBICIÓN 1 - REFERENCIAS: En "hallazgo" y "recomendacion" NUNCA menciones ningún
número de artículo, ley, decreto, resolución, numeral, parágrafo, sentencia, porcentaje,
plazo en días ni salario mínimo que NO aparezca LITERALMENTE en el CONTEXTO LEGAL de abajo.
Esto incluye sub-referencias como "Num. 7", "Parágrafo 2°", "literal b" no presentes.
Si el contexto es insuficiente, describe el hallazgo en términos generales SIN citar norma.

PROHIBICIÓN 2 - REVELACIÓN DE CONTEXTO: NUNCA uses en "hallazgo" o "recomendacion"
frases como "no fue proporcionado", "no está en el contexto", "no aparece en el contexto",
"mencionado/a en el contexto", "el contexto legal no especifica", "según el contexto",
"CONTEXTO LEGAL", "BASE NORMATIVA", ni ninguna referencia — directa o indirecta — a que tu
información viene de un material que se te proporcionó o que tiene límites. El lector de
este hallazgo es la empresa auditada: para ella, "el contexto" no significa nada y revela
el mecanismo interno de la auditoría. Si el RIT cita un artículo que no puedes verificar con
la normativa disponible, descríbelo en términos del cumplimiento (ej: "Se recomienda
verificar que las condiciones de trabajo dominical habitual cumplan con los requisitos
legales aplicables") o compara directamente el hecho SIN mencionar de dónde sale tu
comparación (ej: en vez de "esto contradice la jurisprudencia mencionada en el contexto",
escribe "esto contradice el criterio judicial vigente sobre la materia").

PROHIBICIÓN 3 - ALCANCE DE LA EVALUACIÓN: SOLO puedes crear "hallazgo" y "recomendacion"
basados en obligaciones que aparezcan EXPLÍCITAMENTE en el CONTEXTO LEGAL proporcionado.
NUNCA crees un hallazgo para una obligación que conozcas de tu entrenamiento pero que NO
esté mencionada en los artículos del CONTEXTO LEGAL. Si el CONTEXTO LEGAL no menciona un
requisito (ej. sala de lactancia, ampliación de licencia por determinada ley, instalaciones
físicas especiales), NO lo evalúes aunque lo conozcas. Evalúa SOLO lo que dice el texto de
los artículos proporcionados.

Para "articulos_referencia": copia TEXTUALMENTE los encabezados "--- CODIGO: ..." que aparecen
en CONTEXTO LEGAL (ej: "Art. 115 CST", "Art. 7 Ley 1010"). NUNCA reformatees ni añadas
numerales, parágrafos ni sub-referencias. Si no hay artículos relevantes, devuelve [].
{$seccionArticulos}
SECCIÓN A AUDITAR: {$config['titulo']}

{$contextoRIT}
NO PENALICE por:
- Detalles operativos (provisión de asientos, muebles o equipos físicos, avisos en carteleras,
  registros administrativos internos) que no son contenido estándar de un RIT.
- Obligaciones de infraestructura física de higiene/seguridad (condiciones de locales,
  ventilación, iluminación, instalaciones especiales por actividad productiva, alojamiento
  de trabajadores en zonas remotas) - estas son condiciones de trabajo reguladas aparte.
- Cualquier requisito que no esté mencionado en el CONTEXTO LEGAL proporcionado (PROHIBICIÓN 3).
Un RIT de calidad en SST cubre: compromiso con el SG-SST, COPASST/Vigía, EPP, reporte de
accidentes, exámenes médicos de ingreso/retiro, prohibición de sustancias psicoactivas.
PROMPT;

        if ($numItems > 0) {
            // ── Formato por ítem: habilita el panel de análisis de brechas ────────
            $listaGold = \App\Support\RitGoldStandard::comoLista($goldItems);

            $prompt = <<<PROMPT
{$reglasComunes}

ELEMENTOS QUE UN RIT DE PRIMER NIVEL DEBE CUBRIR EN ESTA SECCIÓN (numerados; evalúa CADA
UNO, sin omitir ninguno, en el mismo orden):
{$listaGold}

Para CADA uno de los {$numItems} elementos numerados, clasifica su estado:
- "cubierto": el RIT lo aborda de forma adecuada y consistente con el CONTEXTO LEGAL.
- "parcial": el RIT lo aborda pero omite un detalle significativo.
- "incorrecto": el RIT lo aborda pero de forma MENOS protectora que, o que CONTRADICE, el
  CONTEXTO LEGAL (incluye cifras, porcentajes o plazos que no coinciden con el contexto).
- "falta": el RIT no lo aborda en absoluto.
Si el estado NO es "cubierto", agrega un "hallazgo" (qué pasa) y una "recomendacion" (qué
hacer), ambos SOLO con base en el CONTEXTO LEGAL (PROHIBICIÓN 3). Si es "cubierto", deja
ambos como cadena vacía "".

Responde ÚNICAMENTE con JSON válido (sin texto adicional antes ni después):
{
  "items": [
    {"n": integer (1 a {$numItems}, uno por cada elemento numerado, EN ORDEN, ninguno omitido),
     "estado": "cubierto" | "parcial" | "incorrecto" | "falta",
     "hallazgo": "string, \\"\\" si cubierto, sin citar artículos fuera del contexto, máx 140 chars",
     "recomendacion": "string, \\"\\" si cubierto, sin citar artículos fuera del contexto, máx 140 chars"}
  ],
  "articulos_referencia": [ códigos copiados textualmente del contexto, máximo 5, o [] ]
}
PROMPT;
        } else {
            // ── Fallback: sección sin checklist propio (no ocurre hoy, las 8 secciones
            // tienen su lista en RitGoldStandard; se conserva por seguridad) ──────────
            $prompt = <<<PROMPT
{$reglasComunes}

Evalúa si el RIT cumple lo que establece el contexto jurídico.

CRITERIO DE PUNTUACIÓN:
- 95-100 (Completo): El RIT cubre correctamente todos los temas principales.
- 80-94 (Parcial alto): El RIT cubre el tema principal pero omite algún elemento de detalle.
- 60-79 (Parcial): El RIT cubre parcialmente el tema; falta un elemento significativo.
- 0-59 (Ausente/Incorrecto): El tema está ausente o contiene información claramente errónea.

Responde ÚNICAMENTE con JSON válido (sin texto adicional antes ni después):
{
  "calificacion": "Completo" | "Parcial" | "Ausente",
  "score": integer 0-100,
  "hallazgos": [ máximo 3 strings sin citar artículos fuera del contexto, máx 120 chars c/u ],
  "recomendaciones": [ máximo 3 strings sin citar artículos fuera del contexto, máx 120 chars c/u ],
  "articulos_referencia": [ códigos copiados textualmente del contexto, máximo 5, o [] ]
}
PROMPT;
        }

        $respuesta = $this->llamarIA($prompt, true);
        $datos     = $this->parsearJSON($respuesta);

        // Validar articulos_referencia: solo conservar códigos que aparezcan literalmente en el contexto
        if (!empty($datos['articulos_referencia'])) {
            $datos['articulos_referencia'] = array_values(array_filter(
                $datos['articulos_referencia'],
                fn($codigo) => is_string($codigo) && str_contains($articulosCst, $codigo)
            ));
        }

        if ($numItems > 0) {
            // Segunda pasada: antes de aceptar un "falta"/"incorrecto" definitivo, se
            // relee el fragmento solo para esos ítems (menos ítems compitiendo por
            // atención que en la pasada de {$numItems} a la vez) - reduce falsos
            // negativos de contenido que sí está presente pero se pasó por alto.
            if (!empty($datos['items'])) {
                $datos['items'] = $this->verificarItemsFaltantes(
                    $datos['items'],
                    $goldItems,
                    $fragmentoRIT,
                    $articulosCst,
                    $razonSocial,
                );
            }

            $datos = $this->normalizarItemsSeccion($datos, $goldItems);
        }

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
     * Segunda pasada de verificación: antes de aceptar un "falta"/"incorrecto" definitivo
     * para algún ítem, se le pide a la IA releer el fragmento COMPLETO otra vez, pero
     * concentrándose SOLO en los ítems marcados como faltantes (no en todos los de la
     * pasada inicial) - una lista más corta compite menos por la atención del
     * modelo y reduce falsos negativos de contenido que sí está presente en el texto
     * pero se pasó por alto en la primera lectura (caso real detectado: el RIT de RENBEL
     * tenía medidas de protección al denunciante de acoso desarrolladas en un artículo
     * propio, y la pasada inicial de 6 ítems a la vez las marcó "falta" igual).
     *
     * Solo sobrescribe los ítems que efectivamente se reenviaron a verificar; si la IA
     * no revierte su conclusión o la llamada falla, se conserva la conclusión original.
     */
    private function verificarItemsFaltantes(
        array  $itemsIA,
        array  $goldItems,
        string $fragmentoRIT,
        string $articulosCst,
        string $razonSocial,
    ): array {
        $porNumero = [];
        foreach ($itemsIA as $entrada) {
            $n = (int) ($entrada['n'] ?? 0);
            if ($n >= 1 && $n <= count($goldItems)) {
                $porNumero[$n] = $entrada;
            }
        }

        $aVerificar = array_filter(
            $porNumero,
            fn($e) => in_array($e['estado'] ?? null, ['falta', 'incorrecto'], true)
        );

        if (empty($aVerificar)) {
            return array_values($porNumero);
        }

        $listaVerificar = '';
        foreach ($aVerificar as $n => $e) {
            $textoItem = $goldItems[$n - 1] ?? '';
            $hallazgoPrevio = trim((string) ($e['hallazgo'] ?? ''));
            $listaVerificar .= "{$n}) {$textoItem} [conclusión preliminar: {$e['estado']}" . ($hallazgoPrevio ? " - {$hallazgoPrevio}" : '') . "]\n";
        }

        $prompt = <<<PROMPT
Eres un auditor legal en una SEGUNDA REVISIÓN del Reglamento Interno de Trabajo de
"{$razonSocial}". Una primera lectura concluyó que los siguientes elementos NO están
cubiertos adecuadamente:

{$listaVerificar}
Antes de confirmar, RELEE con atención el TEXTO COMPLETO del RIT de abajo buscando
específicamente cualquier artículo, cláusula o mención que SÍ aborde cada elemento -
puede estar en otra parte del texto, con otra redacción, o mezclado con otro tema. Es
común que una primera lectura de varios elementos a la vez pase por alto contenido real
que sí está presente.

TEXTO DEL RIT:
{$fragmentoRIT}

CONTEXTO LEGAL (normativa vigente colombiana - única referencia normativa válida):
{$articulosCst}

Para cada elemento: si al releer confirmas que efectivamente falta o es incorrecto,
mantén esa conclusión; si encuentras que SÍ está cubierto (total o parcialmente),
corrige el estado. Reglas para "hallazgo"/"recomendacion" (si el estado final no es
"cubierto"): nunca cites artículo, ley, cifra o plazo que no aparezca literalmente en
el CONTEXTO LEGAL; nunca menciones que esto es una "segunda revisión", "conclusión
preliminar" ni nada del proceso interno de auditoría - debe leerse igual que cualquier
otro hallazgo.

Responde ÚNICAMENTE con JSON válido (sin texto adicional antes ni después):
{
  "items": [
    {"n": integer (solo los números listados arriba),
     "estado": "cubierto" | "parcial" | "incorrecto" | "falta",
     "hallazgo": "string, \\"\\" si cubierto, máx 140 chars",
     "recomendacion": "string, \\"\\" si cubierto, máx 140 chars"}
  ]
}
PROMPT;

        try {
            $respuesta = $this->llamarIA($prompt, true);
            $revisado  = $this->parsearJSON($respuesta);
        } catch (\Throwable $e) {
            Log::warning('AuditoriaRIT: verificación de ítems faltantes falló, se conserva la conclusión inicial', [
                'error' => $e->getMessage(),
            ]);
            return array_values($porNumero);
        }

        foreach ((is_array($revisado['items'] ?? null) ? $revisado['items'] : []) as $entrada) {
            $n = (int) ($entrada['n'] ?? 0);
            if (isset($porNumero[$n]) && in_array($entrada['estado'] ?? null, ['cubierto', 'parcial', 'incorrecto', 'falta'], true)) {
                $porNumero[$n] = $entrada;
            }
        }

        return array_values($porNumero);
    }

    /**
     * Red de seguridad para la PROHIBICIÓN 2 del prompt (nunca revelar el mecanismo
     * interno de la auditoría al cliente). El refuerzo por instrucción reduce la fuga
     * pero no la elimina del todo (confirmado: reaparece en corridas distintas, hasta
     * 3 veces en una sola sección real - "...con referencia a una ley no presente en
     * el contexto legal", "...con referencia a un artículo no presente en el contexto
     * legal", "...con una referencia no presente en el contexto"). Como reescribir la
     * frase de forma segura por regex no es confiable (la redacción varía demasiado),
     * si se detecta la fuga se descarta ESE hallazgo/recomendación puntual y se
     * reemplaza por un mensaje genérico pero seguro, en vez de arriesgar una frase
     * rota o seguir mostrando el mecanismo interno al cliente.
     */
    private function limpiarFugaDeContexto(string $texto): string
    {
        if ($texto === '') {
            return $texto;
        }

        // "contexto laboral/empresarial/de trabajo/normativo" son términos legales legítimos
        // (ej. la Ley 2365/2024 habla de acoso sexual "en el contexto laboral") - no son la
        // fuga que buscamos. Solo se marca "contexto" a secas o seguido de otra palabra,
        // que es como se cuela la referencia al material interno de la auditoría.
        if (preg_match('/\bcontexto\b(?!\s+(laboral|empresarial|normativo|de\s+trabajo))|\bbase normativa\b/ui', $texto)) {
            Log::warning('AuditoriaRIT: fuga de PROHIBICIÓN 2 detectada y sustituida', ['texto_original' => $texto]);
            return 'Se recomienda verificar que este punto sea consistente con la normativa laboral vigente.';
        }

        return $texto;
    }

    /**
     * Normaliza la respuesta de la IA en formato "por ítem" (análisis de brechas):
     * - Mapea cada entrada por su número "n" al texto CANÓNICO del checklist (nunca se
     *   confía en que la IA reescriba el ítem igual; solo en su número).
     * - Rellena con estado "falta" cualquier ítem que la IA haya omitido.
     * - Calcula score/calificación/cumple desde los estados (no desde un número que la
     *   IA "sienta" - más trazable: el cliente puede contar los ítems y verificar el score).
     * - Deriva "hallazgos"/"recomendaciones" planos (compatibilidad con RITMejoradoService
     *   y GAPReporteService, que ya consumen esas dos listas).
     */
    private function normalizarItemsSeccion(array $datos, array $goldItems): array
    {
        $puntosPorEstado = ['cubierto' => 100, 'parcial' => 55, 'incorrecto' => 35, 'falta' => 0];

        $porNumero = [];
        foreach ((is_array($datos['items'] ?? null) ? $datos['items'] : []) as $entrada) {
            $n = (int) ($entrada['n'] ?? 0);
            if ($n >= 1 && $n <= count($goldItems)) {
                $porNumero[$n] = $entrada;
            }
        }

        $items = [];
        $hallazgos = [];
        $recomendaciones = [];
        $sumaPuntos = 0;

        foreach (array_values($goldItems) as $i => $textoItem) {
            $n       = $i + 1;
            $entrada = $porNumero[$n] ?? null;
            $estado  = in_array($entrada['estado'] ?? null, array_keys($puntosPorEstado), true)
                ? $entrada['estado']
                : 'falta'; // la IA omitió este ítem → se asume el peor caso, no se oculta

            $hallazgo      = $estado !== 'cubierto' ? $this->limpiarFugaDeContexto(trim((string) ($entrada['hallazgo'] ?? ''))) : '';
            $recomendacion = $estado !== 'cubierto' ? $this->limpiarFugaDeContexto(trim((string) ($entrada['recomendacion'] ?? ''))) : '';

            $items[] = [
                'item'          => $textoItem,
                'estado'        => $estado,
                'hallazgo'      => $hallazgo,
                'recomendacion' => $recomendacion,
            ];

            if ($hallazgo !== '') $hallazgos[] = $hallazgo;
            if ($recomendacion !== '') $recomendaciones[] = $recomendacion;
            $sumaPuntos += $puntosPorEstado[$estado];
        }

        $score = count($items) > 0 ? (int) round($sumaPuntos / count($items)) : 0;
        $calificacion = $score >= 95 ? 'Completo' : ($score >= 60 ? 'Parcial' : 'Ausente');

        $datos['items']            = $items;
        $datos['hallazgos']        = $hallazgos;
        $datos['recomendaciones']  = $recomendaciones;
        $datos['score']            = $score;
        $datos['calificacion']     = $calificacion;
        $datos['cumple']           = $score >= 80;

        return $datos;
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
        // El regex de encabezado se ancla al INICIO de línea (^\s*CAP[IÍ]TULO\b): un
        // encabezado real siempre empieza la línea con esa palabra. Sin el ancla,
        // cualquier mención normal en prosa ("...el presente capítulo tiene por
        // objeto...", común como primera frase de un artículo) se confundía con un
        // encabezado y cortaba el fragmento a 1-2 líneas (bug detectado auditando un
        // RIT real: la sección SST quedaba con ~70 caracteres en vez de su capítulo
        // completo, calificando como "Ausente" un capítulo en realidad completo).
        $esEncabezado = fn(string $linea): bool => (bool) preg_match('/^\s*CAP[IÍ]TULO\b/ui', $linea);

        if (!empty($capitulos)) {
            $inicio = null;
            foreach ($lineas as $i => $linea) {
                if (!$esEncabezado($linea)) continue;

                // El título puede venir en la misma línea (raro), en la siguiente
                // (convención del generador: "CAPÍTULO III" + "JORNADA...") o después
                // de una o más líneas en blanco (frecuente en documentos subidos/PDF:
                // "CAPITULO II" + línea vacía + "DE LA ADMISIÓN..."). Se revisan las
                // líneas siguientes saltando blancos, pero se PARA en cuanto aparece
                // contenido de artículo (ARTÍCULO/PARÁGRAFO): de ahí en adelante ya es
                // cuerpo del capítulo, no título, y buscar palabras clave ahí produce
                // falsos positivos (una mención de "salario" dentro del primer artículo
                // de un capítulo de jornada, por ejemplo).
                $lineaUp  = mb_strtoupper($linea);
                $tituloUp = '';
                $revisadas = 0;
                for ($j = $i + 1; $j < $total && $revisadas < 4; $j++) {
                    if (trim($lineas[$j]) === '') continue;
                    if (preg_match('/^\s*(ART[IÍ]CULO|PAR[AÁ]GRAFO)\b/ui', $lineas[$j])) break;
                    $tituloUp .= ' ' . mb_strtoupper($lineas[$j]);
                    $revisadas++;
                }

                foreach ($capitulos as $keyword) {
                    $kw = mb_strtoupper($keyword);
                    if (str_contains($lineaUp, $kw) || str_contains($tituloUp, $kw)) {
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
                    if ($esEncabezado($lineas[$i])) {
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
