<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Font;

class GenerarInformePrompts extends Command
{
    protected $signature   = 'informe:prompts';
    protected $description = 'Genera informe Word con inventario y análisis de todos los prompts de IA del proyecto';

    // ─── Estilos reutilizables ────────────────────────────────────────────
    private PhpWord $word;

    public function handle(): int
    {
        $this->info('Generando informe de prompts…');

        $this->word = new PhpWord();
        $this->word->getSettings()->setThemeFontLang(new \PhpOffice\PhpWord\Style\Language(\PhpOffice\PhpWord\Style\Language::ES_ES));
        $this->word->setDefaultFontName('Calibri');
        $this->word->setDefaultFontSize(11);

        // ── Estilos de párrafo ────────────────────────────────────────────
        $this->word->addParagraphStyle('Normal',   ['spaceAfter' => 120, 'lineHeight' => 1.15]);
        $this->word->addParagraphStyle('Centered', ['spaceAfter' => 80,  'alignment'  => Jc::CENTER]);
        $this->word->addParagraphStyle('Code',     ['spaceAfter' => 60,  'lineHeight' => 1.0, 'indentation' => ['left' => 720]]);

        // ── Estilos de fuente ─────────────────────────────────────────────
        $this->word->addFontStyle('H1',       ['name' => 'Calibri', 'size' => 18, 'bold' => true, 'color' => '1F3864']);
        $this->word->addFontStyle('H2',       ['name' => 'Calibri', 'size' => 14, 'bold' => true, 'color' => '2E75B6']);
        $this->word->addFontStyle('H3',       ['name' => 'Calibri', 'size' => 12, 'bold' => true, 'color' => '2F5496']);
        $this->word->addFontStyle('Label',    ['name' => 'Calibri', 'size' => 10, 'bold' => true, 'color' => '404040']);
        $this->word->addFontStyle('CodeFont', ['name' => 'Courier New', 'size' => 9, 'color' => '1A1A1A']);
        $this->word->addFontStyle('Mejor',    ['name' => 'Calibri', 'size' => 10, 'bold' => true, 'color' => '1E7E34']);
        $this->word->addFontStyle('Tag',      ['name' => 'Calibri', 'size' => 9,  'bold' => true, 'color' => 'FFFFFF']);

        $section = $this->word->addSection([
            'marginLeft'  => 1440,
            'marginRight' => 1440,
            'marginTop'   => 1440,
            'marginBottom'=> 1440,
        ]);

        $this->portada($section);
        $this->introduccion($section);
        $this->resumenEjecutivo($section);

        foreach ($this->prompts() as $i => $p) {
            $this->seccionPrompt($section, $i + 1, $p);
        }

        $this->conclusiones($section);

        $ruta = storage_path('app/informes/informe_prompts_ia_' . date('Ymd_His') . '.docx');
        if (!is_dir(dirname($ruta))) {
            mkdir(dirname($ruta), 0775, true);
        }

        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($this->word, 'Word2007');
        $writer->save($ruta);

        $this->info("Documento generado: {$ruta}");
        return self::SUCCESS;
    }

    // ─────────────────────────────────────────────────────────────────────
    // SECCIONES DEL DOCUMENTO
    // ─────────────────────────────────────────────────────────────────────

    private function portada($s): void
    {
        $s->addTextBreak(4);
        $r = $s->addTextRun(['alignment' => Jc::CENTER]);
        $r->addText("CES LEGAL S.A.S.\n", ['name' => 'Calibri', 'size' => 22, 'bold' => true, 'color' => '1F3864']);

        $s->addTextBreak(1);
        $r2 = $s->addTextRun(['alignment' => Jc::CENTER]);
        $r2->addText("INFORME DE AUDITORÍA DE PROMPTS DE INTELIGENCIA ARTIFICIAL", ['name' => 'Calibri', 'size' => 16, 'bold' => true, 'color' => '2E75B6']);

        $s->addTextBreak(2);
        $r3 = $s->addTextRun(['alignment' => Jc::CENTER]);
        $r3->addText("Inventario, análisis crítico y propuesta de mejora de los\nprompts utilizados en la plataforma de gestión disciplinaria", ['name' => 'Calibri', 'size' => 12, 'italic' => true, 'color' => '555555']);

        $s->addTextBreak(3);
        $r4 = $s->addTextRun(['alignment' => Jc::CENTER]);
        $r4->addText("Versión 1.0  ·  " . date('d/m/Y'), ['name' => 'Calibri', 'size' => 11, 'color' => '404040']);

        $s->addPageBreak();
    }

    private function introduccion($s): void
    {
        $s->addText('1. INTRODUCCIÓN', 'H1', 'Normal');
        $this->hr($s);

        $intro = "Este informe documenta todos los prompts de inteligencia artificial utilizados actualmente en la plataforma CES Legal. Para cada prompt se presenta: (1) el texto original tal como está en el código fuente, (2) un análisis crítico de su efectividad, limitaciones y riesgos, (3) una versión mejorada propuesta, y (4) una recomendación fundamentada sobre cuál versión adoptar.";
        $s->addText($intro, null, 'Normal');

        $s->addText("Metodología de evaluación:", 'Label', 'Normal');
        $criterios = [
            "Claridad de instrucción — ¿El modelo sabe exactamente qué hacer?",
            "Anclaje jurídico — ¿Las instrucciones citan normas específicas?",
            "Control de alucinaciones — ¿Se prohíbe inventar datos?",
            "Formato de salida — ¿El formato esperado está definido sin ambigüedad?",
            "Manejo de casos borde — ¿Se indican comportamientos ante situaciones atípicas?",
            "Eficiencia de tokens — ¿El prompt es lo más compacto posible sin perder precisión?",
        ];
        foreach ($criterios as $c) {
            $s->addListItem($c, 0, null, ['listType' => \PhpOffice\PhpWord\Style\ListItem::TYPE_BULLET_FILLED]);
        }
        $s->addTextBreak(1);
        $s->addPageBreak();
    }

    private function resumenEjecutivo($s): void
    {
        $s->addText('2. RESUMEN EJECUTIVO', 'H1', 'Normal');
        $this->hr($s);

        $s->addText("La plataforma CES Legal utiliza 13 prompts distribuidos en 6 servicios de IA. A continuación se presenta el estado general:", null, 'Normal');

        $tabla = $s->addTable(['borderColor' => 'CCCCCC', 'borderSize' => 6, 'cellMargin' => 80]);
        $headers = ['#', 'Servicio', 'Método / Propósito', 'Modelo', 'Estado'];
        $widths  = [400, 2000, 3200, 1500, 1200];
        $filas   = [
            ['1',  'IADescargoService',             'construirPromptGeneracionPreguntas — Pregunta dinámica tras respuesta',   'Multi-modelo', 'Mejorable'],
            ['2',  'IADescargoService',             'generarPreguntasIA — Batería inicial de preguntas',                        'Multi-modelo', 'Mejorable'],
            ['3',  'EvaluacionHechosService',       'construirSystemPrompt — Conversación guiada de hechos',                   'Multi-modelo', 'Bueno'],
            ['4',  'EvaluacionHechosService',       'generarHechosDesdeFormulario — Redacción desde formulario',               'Multi-modelo', 'Mejorable'],
            ['5',  'EvaluacionHechosService',       'mejorarRedaccion — Mejora de borrador empleador',                         'Multi-modelo', 'Bueno'],
            ['6',  'EvaluacionHechosService',       'generarRedaccionCompleta — Redacción final del expediente',               'Multi-modelo', 'Excelente'],
            ['7',  'EvaluacionHechosService',       'darFeedbackDictado — Retroalimentación en tiempo real',                   'Flash rápido', 'Excelente'],
            ['8',  'EvaluacionHechosService',       'verificarDiscriminacion — Detección de lenguaje discriminatorio',          'Flash rápido', 'Excelente'],
            ['9a', 'BibliotecaLegalService',        'extraerTextoConGeminiVision — OCR de PDFs escaneados (inline)',            'Gemini Vision','Mejorable'],
            ['9b', 'BibliotecaLegalService',        'extraerTextoConGeminiFilesAPI — OCR de PDFs grandes (Files API)',          'Gemini Vision','Mejorable'],
            ['10', 'InformeJuridicoExportService',  'construirPromptInformeLenguajeClaro — Informe ejecutivo cliente',          'Gemini Flash', 'Bueno'],
            ['11', 'DocumentGeneratorService',      'construirPromptSancionLenguajeClaro — Documento de sanción',              'Gemini Flash', 'Bueno'],
            ['12', 'IAAnalisisSancionService',      'construirPromptAnalisisSancion — Análisis de gravedad y sanción',          'Gemini Flash', 'Excelente'],
            ['13', 'IAResolucionImpugnacionService','construirPrompt — Resolución de impugnaciones',                           'Gemini Flash', 'Excelente'],
        ];

        $tabla->addRow(400);
        foreach ($headers as $i => $h) {
            $cell = $tabla->addCell($widths[$i], ['bgColor' => '1F3864']);
            $cell->addText($h, ['name' => 'Calibri', 'size' => 10, 'bold' => true, 'color' => 'FFFFFF'], ['alignment' => Jc::CENTER]);
        }

        $colores = ['Excelente' => 'C6EFCE', 'Bueno' => 'FFEB9C', 'Mejorable' => 'FFC7CE'];
        foreach ($filas as $f) {
            $tabla->addRow();
            $estado = $f[4];
            $bgEstado = $colores[$estado] ?? 'FFFFFF';
            foreach ($f as $ci => $celda) {
                $bg = ($ci === 4) ? $bgEstado : 'FFFFFF';
                $cell = $tabla->addCell($widths[$ci], ['bgColor' => $bg]);
                $cell->addText($celda, ['name' => 'Calibri', 'size' => 9], ['alignment' => ($ci === 0 || $ci === 4) ? Jc::CENTER : Jc::LEFT]);
            }
        }

        $s->addTextBreak(1);
        $s->addText("Leyenda:", 'Label', 'Normal');
        $s->addText("Excelente: prompt óptimo, listo para producción.", ['name' => 'Calibri', 'size' => 10, 'color' => '1E7E34'], 'Normal');
        $s->addText("Bueno: funciona correctamente, mejoras menores opcionales.", ['name' => 'Calibri', 'size' => 10, 'color' => 'B8860B'], 'Normal');
        $s->addText("Mejorable: necesita ajustes para mayor precisión o seguridad.", ['name' => 'Calibri', 'size' => 10, 'color' => 'C00000'], 'Normal');
        $s->addPageBreak();
    }

    private function seccionPrompt($s, int $num, array $p): void
    {
        $titulo = "3.{$num} {$p['titulo']}";
        $s->addText($titulo, 'H2', 'Normal');

        // Metadatos
        $meta = [
            'Archivo'  => $p['archivo'],
            'Método'   => $p['metodo'],
            'Modelo'   => $p['modelo'],
            'Propósito'=> $p['proposito'],
        ];
        $tbl = $s->addTable(['borderColor' => 'E0E0E0', 'borderSize' => 4, 'cellMargin' => 60]);
        foreach ($meta as $k => $v) {
            $tbl->addRow();
            $c1 = $tbl->addCell(1400, ['bgColor' => 'EBF3FB']);
            $c1->addText($k, 'Label');
            $c2 = $tbl->addCell(6500);
            $c2->addText($v, ['name' => 'Calibri', 'size' => 10]);
        }
        $s->addTextBreak(1);

        // Prompt actual
        $s->addText('PROMPT ACTUAL (versión en producción)', 'H3', 'Normal');
        $this->codeBlock($s, $p['actual']);
        $s->addTextBreak(1);

        // Análisis crítico
        $s->addText('ANÁLISIS CRÍTICO', 'H3', 'Normal');
        foreach ($p['analisis'] as $item) {
            $s->addListItem($item, 0, ['name' => 'Calibri', 'size' => 10], ['listType' => \PhpOffice\PhpWord\Style\ListItem::TYPE_BULLET_FILLED, 'spaceAfter' => 60]);
        }
        $s->addTextBreak(1);

        // Prompt mejorado
        $s->addText('PROMPT MEJORADO (versión propuesta)', 'H3', 'Normal');
        $this->codeBlock($s, $p['mejorado'], 'D5E8D4');
        $s->addTextBreak(1);

        // Cambios
        $s->addText('CAMBIOS REALIZADOS', 'H3', 'Normal');
        foreach ($p['cambios'] as $c) {
            $s->addListItem($c, 0, ['name' => 'Calibri', 'size' => 10], ['listType' => \PhpOffice\PhpWord\Style\ListItem::TYPE_BULLET_FILLED, 'spaceAfter' => 60]);
        }
        $s->addTextBreak(1);

        // Recomendación
        $tag  = $p['mejor'] === 'mejorado' ? '✔ ADOPTAR VERSIÓN MEJORADA' : '✔ MANTENER VERSIÓN ACTUAL';
        $color = $p['mejor'] === 'mejorado' ? '1E7E34' : '1F3864';
        $tbl2 = $s->addTable(['borderColor' => $color, 'borderSize' => 12, 'cellMargin' => 120]);
        $tbl2->addRow();
        $cell = $tbl2->addCell(7900, ['bgColor' => ($p['mejor'] === 'mejorado' ? 'C6EFCE' : 'DAE8FC')]);
        $cell->addText($tag, ['name' => 'Calibri', 'size' => 11, 'bold' => true, 'color' => $color], ['alignment' => Jc::CENTER]);
        $tbl2->addRow();
        $cell2 = $tbl2->addCell(7900, ['bgColor' => 'FFFFFF']);
        $cell2->addText($p['razon'], ['name' => 'Calibri', 'size' => 10], 'Normal');

        $s->addTextBreak(2);
        $s->addPageBreak();
    }

    private function conclusiones($s): void
    {
        $s->addText('4. CONCLUSIONES Y PLAN DE ACCIÓN', 'H1', 'Normal');
        $this->hr($s);

        $conclusiones = [
            "Los prompts de análisis de sanciones (IAAnalisisSancionService) e impugnaciones (IAResolucionImpugnacionService) están en estado Excelente: sus instrucciones son precisas, el formato JSON es claro y las reglas de formato son explícitas.",
            "Los prompts de extracción de texto PDF (BibliotecaLegalService) requieren mejora inmediata: un prompt de OCR de una sola línea no aprovecha las capacidades de Gemini Vision para documentos jurídicos.",
            "Los prompts de generación de preguntas (IADescargoService) son los de mayor impacto legal: una pregunta mal formulada puede comprometer el debido proceso. Se recomienda agregar la verificación de grupo protegido del trabajador.",
            "El prompt de feedback por dictado (darFeedbackDictado) es el más compacto y efectivo: sirve de modelo para futuras instrucciones de respuesta rápida.",
        ];

        foreach ($conclusiones as $c) {
            $s->addListItem($c, 0, ['name' => 'Calibri', 'size' => 11], ['listType' => \PhpOffice\PhpWord\Style\ListItem::TYPE_BULLET_FILLED, 'spaceAfter' => 120]);
        }

        $s->addTextBreak(1);
        $s->addText('PLAN DE ACCIÓN PRIORIZADO', 'H3', 'Normal');

        $plan = [
            ['Inmediato',  'Actualizar prompts 1 y 2 (preguntas dinámicas) para verificar grupo protegido del trabajador antes de formular cada pregunta.'],
            ['Inmediato',  'Mejorar prompts 9a y 9b (OCR PDF) con instrucciones específicas para documentos jurídicos colombianos.'],
            ['Corto plazo','Actualizar prompt 4 (hechos desde formulario) para incluir verificación de lenguaje presuntivo.'],
            ['Corto plazo','Revisar prompt 10 (informe ejecutivo) para reducir el riesgo de alucinaciones en métricas numéricas.'],
            ['Mediano plazo','Implementar sistema de versionamiento de prompts: guardar en BD con hash, fecha y estado (draft/producción).'],
            ['Mediano plazo','Crear tests automatizados para cada prompt con casos extremos y comparación de salidas.'],
        ];

        $tbl = $s->addTable(['borderColor' => 'CCCCCC', 'borderSize' => 6, 'cellMargin' => 80]);
        $tbl->addRow(400);
        foreach (['Prioridad', 'Acción'] as $i => $h) {
            $c = $tbl->addCell($i === 0 ? 1600 : 6300, ['bgColor' => '1F3864']);
            $c->addText($h, ['name' => 'Calibri', 'size' => 10, 'bold' => true, 'color' => 'FFFFFF']);
        }
        $bgPrio = ['Inmediato' => 'FFC7CE', 'Corto plazo' => 'FFEB9C', 'Mediano plazo' => 'C6EFCE'];
        foreach ($plan as $row) {
            $tbl->addRow();
            $c1 = $tbl->addCell(1600, ['bgColor' => $bgPrio[$row[0]] ?? 'FFFFFF']);
            $c1->addText($row[0], ['name' => 'Calibri', 'size' => 9, 'bold' => true], ['alignment' => Jc::CENTER]);
            $c2 = $tbl->addCell(6300);
            $c2->addText($row[1], ['name' => 'Calibri', 'size' => 10]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────────

    private function hr($s): void
    {
        $s->addTextRun(['borderBottomColor' => '2E75B6', 'borderBottomSize' => 6, 'spaceAfter' => 120]);
    }

    private function codeBlock($s, string $texto, string $bg = 'F5F5F5'): void
    {
        $lineas = explode("\n", $texto);
        $tbl    = $s->addTable(['borderColor' => 'CCCCCC', 'borderSize' => 4, 'cellMargin' => 80]);
        foreach ($lineas as $linea) {
            $tbl->addRow();
            $cell = $tbl->addCell(7900, ['bgColor' => $bg]);
            $cell->addText($linea ?: ' ', ['name' => 'Courier New', 'size' => 8, 'color' => '1A1A1A']);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // DATOS DE PROMPTS
    // ─────────────────────────────────────────────────────────────────────

    private function prompts(): array
    {
        return [

            // ── PROMPT 1 ──────────────────────────────────────────────────
            [
                'titulo'   => 'Generación de Pregunta Dinámica tras Respuesta',
                'archivo'  => 'app/Services/IADescargoService.php — construirPromptGeneracionPreguntas()',
                'metodo'   => 'construirPromptGeneracionPreguntas()',
                'modelo'   => 'Multi-modelo (OpenAI / Claude / Gemini según config)',
                'proposito'=> 'Analiza la última respuesta del trabajador y decide si se requiere una pregunta de seguimiento. Es el prompt de mayor impacto legal: una pregunta mal formulada puede nulificar la diligencia.',

                'actual' => <<<'PROMPT'
Eres un abogado especialista en derecho laboral colombiano, con enfoque estrictamente garantista conforme al Art. 29 de la Constitución Política y al Art. 115 del CST (modificado por Ley 2466 de 2025).

PRINCIPIOS IRRENUNCIABLES
• Presunción de inocencia
• Derecho a la defensa y a la contradicción (Art. 29 C.P.)
• Dignidad humana
• Imparcialidad
• In dubio pro disciplinado
Fundamento: Sentencia T-239/2021, SL1861-2024, C-1270/2000.

CONTEXTO DEL PROCESO
Trabajador: {nombre} — Cargo: {cargo}
Hechos presuntos: {hechos}
Artículos incumplidos: {articulos}
Preguntas ya respondidas: {preguntas_respuestas}
ÚLTIMA PREGUNTA: {pregunta}
RESPUESTA: {respuesta}
TODAS LAS PREGUNTAS: {lista_completa}

PREGUNTAS ABSOLUTAMENTE PROHIBIDAS
1. SUGESTIVAS O CAPCIOSAS  2. ACUSATORIAS  3. IMPERTINENTES
4. SOBRE VIDA PRIVADA  5. QUE VIOLEN LA DIGNIDAD
6. SOBRE AUTOEVALUACIÓN

CUÁNDO GENERAR UNA PREGUNTA ADICIONAL
Solo genera UNA pregunta si se cumplen SIMULTÁNEAMENTE:
1. La respuesta abre un aspecto relevante de defensa no explorado.
2. La aclaración beneficia al trabajador o es necesaria para el expediente.
3. No existe ya una pregunta que cubra ese punto.

FORMATO
Si hay pregunta válida: PREGUNTA_1: [texto]
Si no se requiere: NO_REQUIERE
PROMPT,

                'analisis' => [
                    'FORTALEZA: La prohibición de 6 categorías de preguntas está bien definida y con ejemplos.',
                    'FORTALEZA: La condición de triple cumplimiento simultáneo es una guardia eficaz.',
                    'DEBILIDAD: No verifica si el trabajador pertenece a un grupo protegido (mujer embarazada, persona con discapacidad, indígena, LGTBIQ+). Esto puede resultar en preguntas que ignoran fueros especiales.',
                    'DEBILIDAD: No menciona explícitamente el límite máximo de preguntas dinámicas (riesgo de interrogatorio exhaustivo).',
                    'DEBILIDAD: El formato de salida no especifica qué hacer si la respuesta del trabajador tiene múltiples ángulos que explorar.',
                    'RIESGO JURÍDICO: Sin verificación de grupo protegido, una pregunta sobre datos personales puede vulnerar la Ley 1010/2006 (acoso laboral).',
                ],

                'mejorado' => <<<'PROMPT'
Eres un abogado laboralista colombiano especialista en debido proceso disciplinario. Marco normativo: Art. 29 C.P., Art. 115 CST (Ley 2466/2025), Sentencias T-239/2021 y SL1861-2024.

VERIFICACIÓN PREVIA OBLIGATORIA — GRUPO PROTEGIDO
Antes de formular cualquier pregunta, verifica si el trabajador pertenece a un grupo con protección reforzada:
• Mujer embarazada o en lactancia (Ley 1822/2017, C-005/2017)
• Persona con discapacidad (Ley 361/1997)
• Prepensionado (a 3 años o menos de pensión)
• Trabajador sindicalizado con fuero
• Persona LGBTIQ+ si el hecho involucra su identidad
• Indígena o minoría étnica (resguardos, consulta previa)
Si aplica algún grupo → incluye en la pregunta un recordatorio de que tiene el derecho de ser asistido/a por un representante de su elección.

CONTEXTO DEL PROCESO
Trabajador: {nombre} — Cargo: {cargo}
Grupo protegido detectado: {grupo_protegido o "ninguno"}
Hechos presuntos: {hechos}
Artículos incumplidos: {articulos}
Preguntas respondidas (máx. últimas 5): {preguntas_respuestas}
ÚLTIMA PREGUNTA: {pregunta}
RESPUESTA DEL TRABAJADOR: {respuesta}
TODAS LAS PREGUNTAS DEL FORMULARIO: {lista_completa}
TOTAL PREGUNTAS DINÁMICAS YA GENERADAS: {contador_dinamicas}

LÍMITE DE PREGUNTAS DINÁMICAS
Si ya se generaron 3 o más preguntas dinámicas → responde directamente NO_REQUIERE.
Un número excesivo de preguntas convierte la diligencia en un interrogatorio.

PREGUNTAS ABSOLUTAMENTE PROHIBIDAS
1. SUGESTIVAS O CAPCIOSAS — inducen la respuesta.
   ✗ "¿Verdad que actuó de forma negligente?"
2. ACUSATORIAS — dan por hecho la culpa.
   ✗ "¿Por qué cometió esa falta?"
3. IMPERTINENTES — sin relación con los hechos.
4. SOBRE VIDA PRIVADA — sin incidencia en la falta.
5. QUE VIOLEN LA DIGNIDAD O INTIMIDATORIO.
6. AUTOEVALUACIÓN DE DESEMPEÑO.
   ✗ "¿Usted cumple con sus funciones?"

CRITERIOS PARA GENERAR PREGUNTA (los 3 deben cumplirse)
1. La respuesta abre un aspecto relevante de defensa que el trabajador NO ha podido explicar.
2. La aclaración puede beneficiar al trabajador o es necesaria para el expediente.
3. No existe ya una pregunta en la lista que cubra ese punto.
EN CASO DE DUDA → NO_REQUIERE. Es mejor no preguntar que vulnerar el debido proceso.

FORMATO DE SALIDA
Una sola pregunta válida: PREGUNTA_1: [texto en lenguaje sencillo, máx. 2 líneas]
No se requiere pregunta: NO_REQUIERE
PROMPT,

                'cambios' => [
                    'Se añadió bloque "VERIFICACIÓN PREVIA OBLIGATORIA — GRUPO PROTEGIDO" con 6 categorías y sus normas específicas. Esto protege al sistema de vulnerar fueros especiales.',
                    'Se añadió campo "{grupo_protegido}" en el contexto para forzar al sistema a incluir el dato en la decisión.',
                    'Se añadió "{contador_dinamicas}" y regla de límite máximo (3 preguntas dinámicas) para prevenir el interrogatorio excesivo.',
                    'Se redujo el contexto de "preguntas respondidas" a "últimas 5" para optimizar tokens sin perder contexto relevante.',
                    'Se eliminaron las separaciones visuales con ═══ para reducir el conteo de tokens manteniendo la estructura.',
                ],

                'mejor'  => 'mejorado',
                'razon'  => 'La versión mejorada agrega la verificación de grupo protegido, un límite explícito de preguntas dinámicas y es más eficiente en tokens. Ambas prohíben las mismas categorías de preguntas, pero la mejorada reduce el riesgo de nulidad por violación de fueros especiales.',
            ],

            // ── PROMPT 2 ──────────────────────────────────────────────────
            [
                'titulo'   => 'Generación de Batería Inicial de Preguntas de Descargos',
                'archivo'  => 'app/Services/IADescargoService.php — generarPreguntasIA()',
                'metodo'   => 'generarPreguntasIA()',
                'modelo'   => 'Multi-modelo',
                'proposito'=> 'Genera el conjunto inicial de preguntas que se formulan al trabajador al comenzar la diligencia de descargos, basándose en los hechos y artículos del reglamento.',

                'actual' => <<<'PROMPT'
Eres un abogado laboral experto en procesos disciplinarios colombianos, enfoque garantista (Art. 29 C.P., Art. 115 CST, Ley 2466/2025). Fundamento: T-239/2021, SL1861-2024, C-1270/2000.

OBJETIVO
Generar {cantidad} preguntas abiertas que:
• Permitan al trabajador explicar su versión.
• Indaguen atenuantes, justificaciones o eximentes.
• Exploren si hubo autorización, aviso previo o fuerza mayor.

CONTEXTO
Trabajador: {nombre} — Cargo: {cargo}
Hechos presuntos: {hechos}
Artículos incumplidos: {articulos}

PREGUNTAS ABSOLUTAMENTE PROHIBIDAS
1. SUGESTIVAS O CAPCIOSAS  2. ACUSATORIAS  3. IMPERTINENTES
4. SOBRE VIDA PRIVADA  5. VIOLACIÓN DE DIGNIDAD
6. AUTOEVALUACIÓN DE DESEMPEÑO

LENGUAJE: sencillo, sin tecnicismos. Máx. 2 oraciones por pregunta.

FORMATO
PREGUNTA_1: [texto]
PREGUNTA_2: [texto]
...
PREGUNTA_{cantidad}: [texto]
PROMPT,

                'analisis' => [
                    'FORTALEZA: Las 6 categorías prohibidas están correctamente definidas.',
                    'FORTALEZA: El lenguaje sencillo está explícitamente requerido.',
                    'DEBILIDAD: No instruve al modelo sobre el orden pedagógico de las preguntas (de general a específico). Preguntas desordenadas confunden al trabajador.',
                    'DEBILIDAD: No incluye las preguntas administrativas estándar que siempre se hacen (¿Va a asistir acompañado? ¿Cuál es su cargo actual?). El modelo puede omitirlas o inventar variantes.',
                    'DEBILIDAD: No verifica grupo protegido, igual que el Prompt 1.',
                    'OPORTUNIDAD: Se pueden separar "preguntas estándar obligatorias" de "preguntas del caso específico" para mayor control editorial.',
                ],

                'mejorado' => <<<'PROMPT'
Eres un abogado laboralista colombiano experto en diligencias de descargos. Marco: Art. 29 C.P., Art. 115 CST (Ley 2466/2025), T-239/2021, SL1861-2024.

VERIFICACIÓN PREVIA: GRUPO PROTEGIDO
Revisa si el trabajador pertenece a: mujer embarazada/lactancia, persona con discapacidad, prepensionado, sindicalizado con fuero, LGBTIQ+, o minoría étnica.
Si aplica → la primera pregunta específica del caso debe incluir una frase que le recuerde su derecho a ser asistido/a.

CONTEXTO DEL CASO
Trabajador: {nombre} — Cargo: {cargo}
Grupo protegido: {grupo_protegido o "ninguno"}
Hechos presuntos (versión empleador, no probados): {hechos}
Artículos del reglamento supuestamente incumplidos: {articulos}

ESTRUCTURA DE PREGUNTAS (orden obligatorio)
BLOQUE A — Administrativas (siempre las mismas, 4 preguntas fijas):
A1. ¿Para qué empresa trabaja usted?
A2. ¿Cuál es su cargo actual?
A3. ¿Quién es su jefe directo?
A4. ¿Va a asistir acompañado/a por alguien? (si sí, ¿en qué calidad?)
[Genera exactamente estas 4 preguntas fijas sin modificarlas]

BLOQUE B — Específicas del caso ({cantidad} preguntas de fondo):
Genera preguntas específicas sobre los hechos presuntos, en orden:
1. Primero: versión general del trabajador sobre lo sucedido.
2. Luego: circunstancias, contexto, justificaciones posibles.
3. Después: si avisó, pidió permiso o documentó algo.
4. Finalmente: si tiene pruebas, testigos o documentos a su favor.

PREGUNTAS ABSOLUTAMENTE PROHIBIDAS
1. SUGESTIVAS — inducen la respuesta. ✗ "¿Verdad que actuó de forma negligente?"
2. ACUSATORIAS — presuponen culpa. ✗ "¿Por qué cometió esa falta?"
3. IMPERTINENTES — sin relación con los hechos.
4. SOBRE VIDA PRIVADA.
5. QUE VIOLEN LA DIGNIDAD.
6. DE AUTOEVALUACIÓN. ✗ "¿Cumple con sus funciones?"

LENGUAJE: sencillo, sin tecnicismos. Máximo 2 oraciones por pregunta.

FORMATO DE SALIDA (exacto)
PREGUNTA_1: ¿Para qué empresa trabaja usted?
PREGUNTA_2: ¿Cuál es su cargo actual?
PREGUNTA_3: ¿Quién es su jefe directo?
PREGUNTA_4: ¿Va a asistir acompañado/a por alguien?
PREGUNTA_5: [primera pregunta de fondo sobre los hechos]
...
PREGUNTA_{4+cantidad}: [última pregunta de fondo]
PROMPT,

                'cambios' => [
                    'Se dividió en BLOQUE A (administrativas fijas) y BLOQUE B (preguntas de fondo): garantiza consistencia entre diligencias y evita que la IA omita las preguntas estándar.',
                    'Se añadió un orden pedagógico explícito para el Bloque B: de general a específico, siguiendo la estructura natural de una declaración.',
                    'Se añadió la verificación de grupo protegido con instrucción sobre cómo adaptar la primera pregunta.',
                    'Se incluyó el formato de salida con los primeros 4 prompts ya escritos, eliminando ambigüedad.',
                ],

                'mejor'  => 'mejorado',
                'razon'  => 'La versión mejorada garantiza que las 4 preguntas administrativas siempre estén presentes y en el mismo orden, facilita el análisis estadístico de respuestas y elimina la posibilidad de que el modelo omita la verificación de acompañante.',
            ],

            // ── PROMPT 3 ──────────────────────────────────────────────────
            [
                'titulo'   => 'System Prompt — Conversación Guiada de Hechos (Chat IA)',
                'archivo'  => 'app/Services/EvaluacionHechosService.php — construirSystemPrompt()',
                'metodo'   => 'construirSystemPrompt()',
                'modelo'   => 'Multi-modelo',
                'proposito'=> 'Instrucciones persistentes para el chat de recopilación de hechos con el empleador. El modelo actúa como abogado que hace preguntas hasta tener suficiente información para redactar el descargo.',

                'actual' => <<<'PROMPT'
Eres un abogado laboralista experto de CES Legal (Colombia). Estás ayudando al empleador a documentar los hechos de un proceso disciplinario mediante conversación empática.

FECHA ACTUAL: {hoy}
TRABAJADOR: {nombre} — Cargo: {cargo}
{contexto_antecedentes}
{contexto_reglamento}

TU MISIÓN: Obtener TODA la información necesaria para redactar un descargo completo.

5 ELEMENTOS REQUERIDOS ANTES DE FINALIZAR:
1. ¿Qué conducta exactamente ocurrió?
2. ¿Cuándo ocurrió?
3. ¿El trabajador avisó, pidió permiso o dio justificación?
4. ¿Hay contexto especial o antecedente inmediato?
5. ¿Hay testigos, evidencia o documentos?

REGLA CRÍTICA: Solo marca "listo: true" cuando puedas responder SÍ a los 5 elementos.

RESPONDE SIEMPRE EN JSON VÁLIDO:
Conversando: {"mensaje": "...", "listo": false, "datos": null}
Al finalizar: {"mensaje": "...", "listo": true, "datos": {"hechos": "...", "fecha_ocurrencia": "YYYY-MM-DD o null", "resumen": "Una oración"}}
PROMPT,

                'analisis' => [
                    'FORTALEZA: El formato JSON estructurado con "listo" como bandera binaria es elegante y evita ambigüedades.',
                    'FORTALEZA: Los 5 elementos requeridos son suficientes para construir un expediente válido.',
                    'FORTALEZA: La instrucción de "una pregunta a la vez" es correcta y evita abrumar al empleador.',
                    'OBSERVACIÓN MENOR: No se menciona qué hacer si el empleador da información claramente falsa o contradictoria (ej. dice "no sé la fecha" dos veces).',
                    'OBSERVACIÓN MENOR: No especifica el máximo de turnos de conversación para evitar bucles infinitos.',
                ],

                'mejorado' => <<<'PROMPT'
Eres un abogado laboralista senior de CES Legal Colombia. Tu tarea es ayudar al empleador a documentar los hechos para un proceso disciplinario mediante conversación empática y directa. El empleador NO conoce de leyes; tú sí.

FECHA ACTUAL DEL SISTEMA: {hoy}
TRABAJADOR: {nombre} — Cargo: {cargo}
{contexto_antecedentes}
{contexto_reglamento}

MISIÓN: Obtener, en el mínimo de turnos posible, TODA la información para redactar un descargo sólido.

5 ELEMENTOS QUE DEBES TENER ANTES DE MARCAR "listo: true":
1. CONDUCTA: ¿Qué hizo exactamente el trabajador? (específico, no "llegó tarde" sino cuándo, cuánto)
2. FECHA/HORA: Fecha exacta o aproximada del hecho.
3. AVISO: ¿El trabajador avisó, pidió permiso o justificó antes o después?
4. CONTEXTO: ¿Hubo alguna circunstancia especial o antecedente inmediato?
5. EVIDENCIA: ¿Hay testigos, cámara, correo, registro de entrada u otro soporte?

ESTRATEGIA DE CONVERSACIÓN:
• Haz UNA pregunta concreta a la vez, la más urgente.
• Si el empleador da una respuesta vaga ("no recuerdo", "no sé"), acéptala literalmente para ese elemento y marca ese campo como null en datos.
• Si el empleador contradice información previa, señálalo empáticamente y pide aclaración.
• Máximo 8 turnos: si aún faltan datos, redacta con lo disponible y usa [PENDIENTE: dato] en el texto.
• Los antecedentes ya están en el bloque de arriba — NO los preguntes.

REGLA CRÍTICA: Solo marca "listo: true" cuando puedas responder SÍ a los 5 elementos (null es válido si el empleador no tiene el dato).

RESPONDE SIEMPRE EN JSON VÁLIDO sin bloques de código:
Conversando: {"mensaje": "...", "listo": false, "datos": null}
Al finalizar: {"mensaje": "...", "listo": true, "datos": {"hechos": "...", "fecha_ocurrencia": "YYYY-MM-DD o null", "resumen": "Una oración que resume los hechos en tercera persona"}}
PROMPT,

                'cambios' => [
                    'Se añadió estrategia de conversación explícita: qué hacer si la respuesta es vaga, contradictoria o el empleador no recuerda.',
                    'Se introdujo el límite de 8 turnos con instrucción de redactar con lo disponible si se agota, usando [PENDIENTE: dato] como marcador.',
                    'Se aclaró que null es un valor válido para elementos que el empleador no puede proporcionar, evitando que el modelo quede en un bucle preguntando lo mismo.',
                    'Se añadió "tercera persona" al campo resumen para garantizar consistencia con el resto del expediente.',
                ],

                'mejor'  => 'mejorado',
                'razon'  => 'La versión actual es muy buena. La mejora es incremental: agrega manejo de casos borde (respuestas vagas, contradicciones) y un límite de turnos. Se recomienda la versión mejorada para producción de alto volumen.',
            ],

            // ── PROMPT 4 ──────────────────────────────────────────────────
            [
                'titulo'   => 'Generación de Hechos desde Formulario Estático',
                'archivo'  => 'app/Services/EvaluacionHechosService.php — generarHechosDesdeFormulario()',
                'metodo'   => 'generarHechosDesdeFormulario()',
                'modelo'   => 'Multi-modelo',
                'proposito'=> 'Cuando el empleador completa el formulario directamente (sin chat), este prompt convierte los datos del formulario en una redacción jurídica de los hechos.',

                'actual' => <<<'PROMPT'
Con base en los siguientes datos, redacta los hechos del proceso disciplinario en lenguaje jurídico-laboral formal colombiano (mínimo 3 párrafos, tercera persona). Usa EXACTAMENTE los datos proporcionados — NO uses corchetes, NO inventes ni omitas información.

DATOS DEL TRABAJADOR:
- Nombre: {nombre} — Identificación: {identificacion} — Cargo: {cargo}

DATOS DE LA EMPRESA:
- Razón social: {razon_social}

DATOS DEL HECHO:
- Descripción: {descripcion}
- Fecha: {fecha}
- Lugar: {lugar}
- ¿El trabajador avisó?: {notifico} — Detalle: {detalle}
- Evidencias: {evidencias}

Responde ÚNICAMENTE en JSON válido sin bloques de código:
{"hechos": "...", "fecha_ocurrencia": "YYYY-MM-DD o null", "resumen": "Una oración"}
PROMPT,

                'analisis' => [
                    'FORTALEZA: La instrucción "NO inventes ni omitas" es la guardia más importante de este prompt y está bien posicionada.',
                    'FORTALEZA: El formato JSON con tres campos es correcto y mínimo.',
                    'DEBILIDAD CRÍTICA: No incluye la instrucción de lenguaje presuntivo. Los hechos deben redactarse como "presuntamente" o "al parecer" ya que el trabajador no ha sido encontrado culpable.',
                    'DEBILIDAD: No instruve al modelo sobre qué hacer si la descripción del hecho contiene groserías, insultos o lenguaje discriminatorio.',
                    'DEBILIDAD: No especifica el mínimo de palabras por párrafo ni el tono exacto.',
                ],

                'mejorado' => <<<'PROMPT'
Redacta los hechos de un proceso disciplinario laboral colombiano con base EXCLUSIVAMENTE en los datos proporcionados. No inventes ni omitas información.

DATOS DEL TRABAJADOR:
- Nombre: {nombre} — Identificación: {identificacion} — Cargo: {cargo}

DATOS DE LA EMPRESA:
- Razón social: {razon_social}

DATOS DEL HECHO:
- Descripción: {descripcion}
- Fecha: {fecha}
- Lugar: {lugar}
- ¿El trabajador notificó previamente?: {notifico} — Detalle: {detalle}
- Evidencias disponibles: {evidencias}

REGLAS DE REDACCIÓN OBLIGATORIAS:
1. LENGUAJE PRESUNTIVO: Toda conducta del trabajador se redacta como "presuntamente [acción]" o "se evidencia que al parecer [acción]". Nunca como hecho probado.
2. TERCERA PERSONA, tono objetivo y factual.
3. LENGUAJE JURÍDICO LIMPIO: Si la descripción contiene groserías o insultos, reemplázalos por la descripción objetiva de la conducta.
4. PROHIBICIÓN ANTIDISCRIMINATORIA: Elimina cualquier referencia a raza, etnia, orientación sexual, discapacidad o apariencia física. Solo describe la conducta.
5. Mínimo 3 párrafos: (1) identificación y contexto, (2) descripción de la conducta presunta, (3) consecuencias o evidencias disponibles.
6. NO uses corchetes, NO inventes datos, NO añadas información no proporcionada.

Responde ÚNICAMENTE en JSON válido sin bloques de código:
{"hechos": "Redacción jurídica completa...", "fecha_ocurrencia": "YYYY-MM-DD o null", "resumen": "Una oración resumen en tercera persona"}
PROMPT,

                'cambios' => [
                    'Se añadió REGLA 1: lenguaje presuntivo obligatorio — crítico para que los hechos no perjudiquen prematuramente al trabajador.',
                    'Se añadió REGLA 3: limpieza de lenguaje soez — el sistema ya lo hace en generarRedaccionCompleta() pero no en este prompt.',
                    'Se añadió REGLA 4: prohibición antidiscriminatoria — coherente con el resto del sistema.',
                    'Se especificó la estructura de 3 párrafos con contenido de cada uno.',
                ],

                'mejor'  => 'mejorado',
                'razon'  => 'La adición del lenguaje presuntivo es no negociable: redactar los hechos como hechos probados antes de la diligencia viola el debido proceso y puede nulificar el proceso. Esta es la mejora más urgente del informe.',
            ],

            // ── PROMPT 5 ──────────────────────────────────────────────────
            [
                'titulo'   => 'Mejora de Redacción del Borrador del Empleador',
                'archivo'  => 'app/Services/EvaluacionHechosService.php — mejorarRedaccion()',
                'metodo'   => 'mejorarRedaccion()',
                'modelo'   => 'Multi-modelo (flash rápido)',
                'proposito'=> 'El empleador escribe un borrador libre de los hechos. Este prompt lo transforma en redacción jurídica válida, marcando con [COMPLETAR] los datos que faltan.',

                'actual' => <<<'PROMPT'
Eres un especialista en documentación de procesos disciplinarios laborales colombianos.

CONTEXTO NORMATIVO: {reglamento} {datos_conocidos}

TAREA: Reescribir el borrador del empleador de forma ESPECÍFICA y ÚTIL para el expediente.

REGLAS:
1. Conserva todos los hechos CONCRETOS.
2. NO amplíes con frases genéricas.
3. Usa DATOS YA CONOCIDOS; para lo desconocido usa [COMPLETAR: qué falta].
4. Al final escribe: "Norma aplicable: [artículo concreto]"
5. Tercera persona, tono factual. Máximo 220 palabras.

DATOS SIEMPRE REQUERIDOS (con [COMPLETAR] si faltan):
- Fecha y hora exacta  - Lugar específico
- Cómo se enteró el supervisor  - Notificación del trabajador
- Consecuencia concreta para la empresa

FORMATO: Solo texto en párrafos. Sin JSON, listas ni asteriscos.
PROMPT,

                'analisis' => [
                    'FORTALEZA: El uso de [COMPLETAR] como marcador editorial es una práctica profesional correcta.',
                    'FORTALEZA: La instrucción de identificar "Norma aplicable" al final añade valor jurídico inmediato.',
                    'FORTALEZA: El límite de 220 palabras es adecuado para este propósito.',
                    'OBSERVACIÓN: La instrucción "conserva los hechos concretos" puede llevar al modelo a conservar también lenguaje soez o discriminatorio si no se indica lo contrario. Se requiere la misma prohibición que en otros prompts.',
                    'OBSERVACIÓN: No especifica si debe incluir lenguaje presuntivo en la reescritura.',
                ],

                'mejorado' => <<<'PROMPT'
Eres un especialista en documentación de procesos disciplinarios laborales colombianos.

CONTEXTO NORMATIVO: {reglamento} {datos_conocidos}

TAREA: Reescribir el borrador del empleador de forma ESPECÍFICA, VÁLIDA y ÚTIL para el expediente.

REGLAS CRÍTICAS:
1. Conserva todos los hechos CONCRETOS que ya están escritos.
2. LENGUAJE PRESUNTIVO: Toda acción del trabajador se redacta como "presuntamente [acción]".
3. NO amplíes con frases genéricas ("omitió sus funciones" no sirve).
4. LENGUAJE LIMPIO: Si el borrador contiene groserías o lenguaje discriminatorio, reemplázalos por la descripción objetiva de la conducta.
5. Usa los DATOS YA CONOCIDOS; para los desconocidos usa [COMPLETAR: qué dato falta].
6. Al final escribe en línea separada: "Norma aplicable: [artículo o numeral concreto]"
7. Tercera persona, tono factual y objetivo. Máximo 220 palabras.

DATOS QUE SIEMPRE DEBEN APARECER (con [COMPLETAR] solo si no están disponibles):
- Fecha y hora exacta del hecho
- Lugar específico dentro de la empresa
- Cómo se enteró el supervisor o la empresa
- Si el trabajador notificó previamente o justificó
- Consecuencia concreta para la empresa u operación

FORMATO: Solo texto en párrafos. Sin JSON, listas, asteriscos ni encabezados.
PROMPT,

                'cambios' => [
                    'Se añadió REGLA 2: lenguaje presuntivo en la reescritura.',
                    'Se añadió REGLA 4: prohibición de conservar groserías o lenguaje discriminatorio del borrador original.',
                    'Los demás cambios son de claridad y orden de las reglas.',
                ],

                'mejor'  => 'mejorado',
                'razon'  => 'Cambios menores pero importantes. La regla de lenguaje presuntivo es especialmente necesaria en este prompt porque el modelo puede simplemente reescribir el borrador del empleador manteniendo el tono acusatorio.',
            ],

            // ── PROMPT 6 ──────────────────────────────────────────────────
            [
                'titulo'   => 'Generación de Redacción Final Completa del Expediente',
                'archivo'  => 'app/Services/EvaluacionHechosService.php — generarRedaccionCompleta()',
                'metodo'   => 'generarRedaccionCompleta()',
                'modelo'   => 'Multi-modelo',
                'proposito'=> 'Genera la redacción jurídica final de los hechos para el expediente, integrando todos los datos disponibles del caso.',

                'actual' => <<<'PROMPT'
Eres abogado laboralista colombiano especializado en expedientes disciplinarios.

CONTEXTO NORMATIVO: {reglamento}
DATOS DEL CASO: {datos}
BORRADOR DEL EMPLEADOR: {borrador}

TAREA: Redacta los hechos disciplinarios para el expediente en 2-3 párrafos.

REGLAS ABSOLUTAS:
1. Usa TODOS los datos disponibles.
2. Si un dato NO está disponible, omítelo — NUNCA uses placeholders.
3. Tercera persona, tono objetivo.
4. Incluye: conducta, cuándo, dónde, cómo se enteró la empresa, consecuencia.
5. Solo texto plano en párrafos. Máximo 200 palabras.
6. LENGUAJE PRESUNTIVO: "presuntamente [acción]" para lo no probado.
7. LENGUAJE JURÍDICO: Reemplaza groserías por terminología objetiva.
8. PROHIBICIÓN ANTIDISCRIMINATORIA: Elimina referencias a raza, etnia, orientación sexual, discapacidad o apariencia física.
9. NO cites artículos al final.
PROMPT,

                'analisis' => [
                    'FORTALEZA: Es el prompt más completo del sistema. Ya incluye lenguaje presuntivo, limpieza de lenguaje y prohibición antidiscriminatoria.',
                    'FORTALEZA: La instrucción de NO usar placeholders cuando no hay datos evita la generación de redacciones con corchetes vacíos.',
                    'FORTALEZA: El límite de 200 palabras es apropiado para la función.',
                    'OBSERVACIÓN MÍNIMA: El orden de las reglas (1-9) podría reorganizarse para que las más críticas (presuntivo, antidiscriminatorio) estén primero.',
                    'ESTADO: Excelente. Requiere solo ajustes menores de presentación.',
                ],

                'mejorado' => <<<'PROMPT'
Eres abogado laboralista colombiano especializado en expedientes disciplinarios.

CONTEXTO NORMATIVO: {reglamento}
DATOS DEL CASO: {datos}
BORRADOR DEL EMPLEADOR: {borrador}

TAREA: Redacta los hechos disciplinarios para el expediente en 2-3 párrafos.

REGLAS ABSOLUTAS (en orden de prioridad):
1. PRESUNTIVO: Toda acción del trabajador que no sea hecho probado se redacta como "presuntamente [acción]". Este principio es irrenunciable.
2. ANTIDISCRIMINATORIO: Elimina cualquier referencia a raza, etnia, origen nacional, orientación sexual, identidad de género, discapacidad o apariencia física. Describe únicamente la conducta objetiva.
3. LENGUAJE LIMPIO: Reemplaza groserías e insultos por terminología jurídica objetiva.
4. SOLO DATOS DISPONIBLES: Si un dato no está disponible, omítelo completamente — NUNCA uses corchetes, placeholders ni [COMPLETAR].
5. Usa TODOS los datos disponibles: conducta, cuándo, dónde, cómo se enteró la empresa, consecuencia para la operación.
6. Tercera persona, tono factual. Sin adornos ni frases genéricas. Máximo 200 palabras.
7. Solo texto plano en párrafos. Sin HTML, listas, asteriscos ni JSON.
8. NO cites artículos, sentencias ni normas al final.
PROMPT,

                'cambios' => [
                    'Reorganización: Las reglas de mayor impacto legal (presuntivo, antidiscriminatorio) ahora están en posiciones 1 y 2.',
                    'Se fusionaron las reglas 3 y 5 del original para mayor claridad.',
                    'Sin cambios sustanciales de contenido — el prompt original es excelente.',
                ],

                'mejor'  => 'actual',
                'razon'  => 'Este prompt ya está en estado Excelente. La versión mejorada solo reordena las reglas para mayor énfasis, pero el comportamiento del modelo es equivalente. Se puede adoptar cualquiera de las dos versiones.',
            ],

            // ── PROMPT 7 ──────────────────────────────────────────────────
            [
                'titulo'   => 'Feedback en Tiempo Real sobre Dictado del Empleador',
                'archivo'  => 'app/Services/EvaluacionHechosService.php — darFeedbackDictado()',
                'metodo'   => 'darFeedbackDictado()',
                'modelo'   => 'Flash rápido (baja latencia)',
                'proposito'=> 'Mientras el empleador dicta o escribe los hechos, este prompt analiza el texto en tiempo real y le indica qué dato concreto le falta para completar el expediente. La respuesta se sintetiza en voz por TTS.',

                'actual' => <<<'PROMPT'
Eres un abogado laboralista colombiano con 15 años en procesos disciplinarios.

CONTEXTO NORMATIVO: {reglamento} {datos_capturados} {normas}

Evalúa el relato e identifica EL criterio más urgente que falta:
1. CONDUCTA CONCRETA: ¿Es específica?
2. IMPACTO: ¿Consecuencia real para la empresa?
3. PRUEBAS: ¿Testigos, cámara, correo, registro?

Responde con 1-2 frases directas. Cita norma SOLO si está en la lista y aplica con certeza.
Si el relato ya está completo, confírmalo brevemente.

REGLAS ABSOLUTAS:
- SOLO el texto que se leerá en voz alta.
- Máximo 2 frases. Sin saludos, listas, numeración.
- PROHIBIDO: JSON, llaves, corchetes, formatos especiales.
- Comienza directamente con la recomendación.
PROMPT,

                'analisis' => [
                    'FORTALEZA: Es el prompt más eficiente del sistema. Compacto, directo y optimizado para TTS.',
                    'FORTALEZA: La instrucción "comienza directamente" evita los saludos que añaden latencia al TTS.',
                    'FORTALEZA: La prohibición explícita de JSON y formatos es necesaria en prompts de respuesta libre.',
                    'ESTADO: Excelente. No requiere cambios sustanciales.',
                ],

                'mejorado' => <<<'PROMPT'
Eres un abogado laboralista colombiano con 15 años en procesos disciplinarios. Tu respuesta se leerá en voz alta (TTS) — sé directo y conciso.

CONTEXTO (no lo repitas en tu respuesta):
{reglamento} {datos_capturados} {normas}

TAREA: Identifica EL criterio más urgente que falta en el relato. Evalúa en este orden:
1. CONDUCTA CONCRETA: ¿Es específica? "No cumplió funciones" no sirve — ¿qué hizo exactamente?
2. IMPACTO: ¿Consecuencia real y concreta para la empresa, el equipo o el servicio?
3. PRUEBAS: ¿Testigos, cámara, correo, registro de entrada u otro soporte tangible?
Nota: El historial disciplinario ya está registrado — NO lo solicites.

Si el relato ya es completo → confírmalo en una frase breve.

REGLAS DE FORMATO ABSOLUTAS:
• Máximo 2 frases en español colombiano profesional.
• Sin saludos, listas, numeración ni encabezados.
• PROHIBIDO: JSON, llaves {}, corchetes [], XML, markdown.
• Comienza directamente con la recomendación o confirmación.
• Si citas una norma, hazlo en lenguaje simple: "según el reglamento" (no "Art. 115 CST").
PROMPT,

                'cambios' => [
                    'Se añadió aviso inicial "Tu respuesta se leerá en voz alta (TTS)" para forzar mayor naturalidad en el lenguaje.',
                    'Se instruve al modelo a no repetir el contexto en su respuesta, reduciendo el riesgo de que lea los datos del contexto en voz alta.',
                    'La cita de normas ahora se pide en lenguaje simple ("según el reglamento") en lugar de la notación técnica.',
                    'El resto es ajuste de presentación — el comportamiento es equivalente.',
                ],

                'mejor'  => 'actual',
                'razon'  => 'Ambas versiones son excelentes para su propósito. La versión mejorada agrega el aviso de TTS y simplifica la cita normativa, pero el impacto en el comportamiento del modelo es mínimo. Se puede mantener la versión actual.',
            ],

            // ── PROMPT 8 ──────────────────────────────────────────────────
            [
                'titulo'   => 'Verificación de Lenguaje Discriminatorio',
                'archivo'  => 'app/Services/EvaluacionHechosService.php — verificarDiscriminacion()',
                'metodo'   => 'verificarDiscriminacion()',
                'modelo'   => 'Flash rápido',
                'proposito'=> 'Analiza el texto escrito por el empleador y detecta si contiene lenguaje discriminatorio, peyorativo o referencias innecesarias a características protegidas del trabajador.',

                'actual' => <<<'PROMPT'
Eres un experto en derecho antidiscriminatorio colombiano y venezolano. Analiza el siguiente texto de un proceso disciplinario laboral.

Determina si el texto contiene lenguaje discriminatorio incluyendo:
- Raza o etnia (jerga: "veneco", "chamo", "negro", "indio", etc.)
- Orientación sexual o identidad de género
- Discapacidad física o mental
- Religión o creencias
- Origen nacional o migratorio
- Apariencia física usada de forma peyorativa
- Cualquier otro calificativo discriminatorio

Detecta también jerga regional, apodos étnicos, eufemismos y frases implícitas.

Responde ÚNICAMENTE en JSON válido:
{"discriminatorio": true/false, "categoria": "nombre o null", "termino": "término exacto o null", "sugerencia": "cómo describir sin discriminación, máx. 15 palabras, o null"}
PROMPT,

                'analisis' => [
                    'FORTALEZA: La detección de jerga regional y eufemismos implícitos es una instrucción sofisticada que pocos sistemas implementan.',
                    'FORTALEZA: El formato JSON de 4 campos es mínimo y suficiente.',
                    'FORTALEZA: La instrucción de proporcionar "sugerencia" de redacción correcta es muy útil para el empleador.',
                    'OBSERVACIÓN: Podría agregar términos adicionales de poblaciones vulnerables específicas de Colombia (p.ej. "parce" en contexto peyorativo, términos hacia comunidades afrodescendientes).',
                    'ESTADO: Excelente. Listo para producción.',
                ],

                'mejorado' => <<<'PROMPT'
Eres un experto en derecho antidiscriminatorio colombiano. Analiza el texto de un proceso disciplinario laboral en busca de lenguaje discriminatorio, peyorativo o innecesariamente identificatorio.

CATEGORÍAS A DETECTAR:
- Raza, etnia o color de piel (jerga: "veneco", "chamo", "negro", "indio", "mono", "zambo", afro*, etc.)
- Orientación sexual o identidad de género (incluso implícita)
- Discapacidad física o mental (incluso eufemismos)
- Religión o creencias (incluye "sectario", "fanático", etc.)
- Origen nacional, migratorio o regional ("provinciano", "costeño" en sentido peyorativo)
- Apariencia física sin relación con la falta ("gordo", "feo", etc.)
- Situación económica personal del trabajador
- Cualquier apodo, jerga o eufemismo que identifique a una persona por una característica protegida

IMPORTANTE: Detecta también frases implícitamente discriminatorias y lenguaje aparentemente neutral con intención peyorativa.

Responde ÚNICAMENTE en JSON válido, sin texto adicional:
{"discriminatorio": true/false, "categoria": "nombre de la categoría o null", "termino": "término o frase exacta encontrada o null", "sugerencia": "cómo describir la conducta sin discriminación, máx. 15 palabras, o null"}
PROMPT,

                'cambios' => [
                    'Se amplió la lista de términos étnicos/raciales con más ejemplos colombianos específicos.',
                    'Se añadió la categoría "situación económica personal" como característica protegida.',
                    'Se añadió aclaración de que se detectan frases "aparentemente neutrales con intención peyorativa".',
                    'Sin cambios en el formato JSON de salida.',
                ],

                'mejor'  => 'mejorado',
                'razon'  => 'El prompt actual es excelente pero la lista de términos puede ampliarse con jerga colombiana más específica. La adición de la situación económica como característica protegida es relevante dado el contexto socioeconómico del país.',
            ],

            // ── PROMPTS 9a y 9b ──────────────────────────────────────────
            [
                'titulo'   => 'Extracción de Texto de PDFs Escaneados (Gemini Vision)',
                'archivo'  => 'app/Services/BibliotecaLegalService.php — extraerTextoConGeminiVision() y extraerTextoConGeminiFilesAPI()',
                'metodo'   => 'extraerTextoConGeminiVision() / extraerTextoConGeminiFilesAPI()',
                'modelo'   => 'Gemini Vision 2.5 Flash',
                'proposito'=> 'Extrae el texto completo de documentos PDF escaneados (imágenes). Se usa cuando smalot/pdfparser no puede extraer texto porque el PDF es una imagen. Es la puerta de entrada de los documentos a la base de conocimiento jurídico (RAG).',

                'actual' => <<<'PROMPT'
Extrae TODO el texto de este documento de forma literal y completa. No resumas ni omitas nada. Transcribe el contenido íntegro tal como aparece. Devuelve SOLO el texto extraído, sin comentarios ni explicaciones adicionales.
PROMPT,

                'analisis' => [
                    'PROBLEMA CRÍTICO: Con una sola instrucción genérica, Gemini puede "limpiar" el texto, corregir ortografía o interpretar en lugar de transcribir. Para documentos legales esto es inadmisible.',
                    'PROBLEMA: No especifica cómo manejar tablas, notas al pie, números de expediente o sellos — elementos frecuentes en jurisprudencia.',
                    'PROBLEMA: No indica qué hacer con texto ilegible o degradado.',
                    'PROBLEMA: No instruye sobre la preservación de la estructura del documento (párrafos, numeración, puntos).',
                    'RIESGO RAG: Si el texto extraído tiene errores sutiles no detectados, los fragmentos indexados para búsqueda serán incorrectos y citarán mal la jurisprudencia.',
                ],

                'mejorado' => <<<'PROMPT'
Eres un sistema especializado en OCR de documentos jurídicos colombianos. Tu única tarea es transcribir fielmente el texto de este documento.

INSTRUCCIONES ESTRICTAS:
1. Transcribe el texto EXACTAMENTE como aparece. No corrijas ortografía, puntuación ni gramática.
2. Preserva la estructura: numeración de párrafos, artículos, considerandos, resuelve, fundamentos jurídicos.
3. Para TABLAS: transcribe fila por fila, separando celdas con " | " y filas con salto de línea.
4. Para SELLOS, FIRMAS o ELEMENTOS NO TEXTUALES: escribe [SELLO], [FIRMA], [GRÁFICO] según corresponda.
5. Para texto ilegible o degradado: escribe [ILEGIBLE].
6. Para notas al pie: transcríbelas al final precedidas de "NOTA AL PIE N°:".
7. Preserva los números de radicado, expediente, NIT, fecha y cédula exactamente como aparecen.
8. NO resumas, NO omitas, NO interpretes, NO añadas comentarios.

Devuelve ÚNICAMENTE el texto transcrito, sin encabezados ni explicaciones adicionales.
PROMPT,

                'cambios' => [
                    'Se especificaron instrucciones para 6 tipos de elementos documentales frecuentes en jurisprudencia (tablas, sellos, firmas, texto ilegible, notas al pie, datos numéricos).',
                    'Se añadió la instrucción de NO corregir ortografía ni gramática — la corrección podría cambiar el sentido de citas jurídicas.',
                    'Se instruve sobre cómo manejar texto ilegible en lugar de dejar que el modelo invente el contenido.',
                    'Se añadió "Eres un sistema especializado en OCR" para orientar el comportamiento del modelo desde el inicio.',
                ],

                'mejor'  => 'mejorado',
                'razon'  => 'Esta es la mejora más urgente del sistema. Un prompt de OCR genérico en una base de conocimiento jurídico es un riesgo serio: el modelo puede "mejorar" sutilmente el texto de una sentencia, alterando su contenido y generando citas falsas. La versión mejorada es la única aceptable para un RAG de documentos legales.',
            ],

            // ── PROMPT 10 ─────────────────────────────────────────────────
            [
                'titulo'   => 'Informe Ejecutivo para el Cliente (Lenguaje Claro)',
                'archivo'  => 'app/Services/InformeJuridicoExportService.php — construirPromptInformeLenguajeClaro()',
                'metodo'   => 'construirPromptInformeLenguajeClaro()',
                'modelo'   => 'Gemini 2.5 Flash — 16.384 tokens máx.',
                'proposito'=> 'Genera un informe de gestión completo para el cliente empresa, explicando en lenguaje simple las gestiones realizadas por CES Legal durante el periodo.',

                'actual' => <<<'PROMPT'
[Prompt extenso de ~900 palabras con 8 secciones: carta de presentación, resumen ejecutivo, detalle de gestiones, resultados, análisis, recomendaciones, plan de trabajo futuro y conclusión. Incluye instrucciones de formato Calibri 11pt sin markdown.]
PROMPT,

                'analisis' => [
                    'FORTALEZA: La estructura de 8 secciones es profesional y completa.',
                    'FORTALEZA: La instrucción de describir TODAS las gestiones sin omitir ninguna evita informes incompletos.',
                    'FORTALEZA: El énfasis en "beneficio para el cliente" en cada gestión es valor diferencial.',
                    'DEBILIDAD: Con 16.384 tokens de salida máximos, el modelo puede truncar el informe si hay muchas gestiones. No hay instrucción de qué hacer si se acerca al límite.',
                    'DEBILIDAD: No hay instrucción de manejo de métricas numéricas sospechosas (ej. si la base de datos envía un "tiempo_promedio" de 0 horas).',
                    'OBSERVACIÓN: El prompt prohíbe markdown pero no menciona el formato de salida esperado (texto plano). Agregar esta aclaración evita que el modelo use formateo inesperado.',
                ],

                'mejorado' => <<<'PROMPT'
[Versión mejorada: misma estructura de 8 secciones + 3 adiciones]

ADICIONES CLAVE SOBRE LA VERSIÓN ACTUAL:

MANEJO DE MÉTRICAS CERO O ANÓMALAS:
Si alguna métrica tiene valor 0 o parece incoherente (ej. tiempo_promedio = 0 horas), escríbela como "No disponible" en lugar de cero. No inventes justificaciones para valores anómalos.

MANEJO DE LÍMITE DE LONGITUD:
Si hay muchas gestiones y no alcanza el espacio para describir todas con el mismo detalle, describe las primeras con detalle completo y las últimas con un párrafo más conciso. Nunca omitas una gestión completamente — al menos menciona su nombre y propósito.

VERIFICACIÓN FINAL DE FORMATO:
Antes de finalizar, verifica que tu respuesta no contenga: asteriscos (**), almohadillas (#), guiones largos (---), ni bloques de código. Si detectas alguno, elimínalo.
PROMPT,

                'cambios' => [
                    'Se añadió bloque de manejo de métricas cero o anómalas: previene que el modelo genere texto como "En promedio las gestiones tomaron 0 horas".',
                    'Se añadió instrucción de manejo de límite de longitud: qué hacer si el informe es muy largo.',
                    'Se añadió verificación final de formato para eliminar markdown residual.',
                ],

                'mejor'  => 'mejorado',
                'razon'  => 'El prompt actual es bueno y funciona correctamente. Las mejoras son preventivas para casos borde que ocurren en producción con clientes de alta actividad (muchas gestiones) o datos incompletos en la BD.',
            ],

            // ── PROMPT 11 ─────────────────────────────────────────────────
            [
                'titulo'   => 'Documento de Sanción en Lenguaje Claro',
                'archivo'  => 'app/Services/DocumentGeneratorService.php — construirPromptSancionLenguajeClaro()',
                'metodo'   => 'construirPromptSancionLenguajeClaro()',
                'modelo'   => 'Gemini 2.5 Flash — 8.192 tokens máx.',
                'proposito'=> 'Genera el documento oficial de sanción (llamado de atención, suspensión o terminación) con toda la información legal pero en lenguaje accesible para el trabajador.',

                'actual' => <<<'PROMPT'
[Prompt de ~700 palabras con instrucciones de redacción, HTML template detallado de 7 secciones y tabla de sanciones del Art. 20.]
PROMPT,

                'analisis' => [
                    'FORTALEZA: El template HTML explícito garantiza consistencia visual entre documentos.',
                    'FORTALEZA: La instrucción de lenguaje claro ("oraciones máx. 25 palabras, voz activa, habla directo al trabajador") está correctamente especificada.',
                    'FORTALEZA: El manejo del caso "trabajador no respondió descargos" está cubierto explícitamente.',
                    'OBSERVACIÓN: El template HTML tiene placeholders [entre corchetes] para que el modelo los complete. Si el modelo no completa todos los placeholders, el documento queda con corchetes visibles.',
                    'OBSERVACIÓN: No especifica qué hacer si los descargos del trabajador son muy extensos y superan el espacio razonable en el documento.',
                    'ESTADO: Bueno. Funciona correctamente en producción.',
                ],

                'mejorado' => <<<'PROMPT'
[Versión mejorada: mismo template HTML + 2 adiciones]

ADICIONES SOBRE LA VERSIÓN ACTUAL:

VERIFICACIÓN DE PLACEHOLDERS:
Antes de finalizar, revisa que TODOS los textos [entre corchetes] hayan sido reemplazados con contenido real del caso. Si un dato no está disponible, usa "No consta en el expediente" en lugar del placeholder.

EXTENSIÓN DE DESCARGOS:
Si los descargos del trabajador son extensos (más de 200 palabras), resúmelos en máximo 3 oraciones conservando los puntos más relevantes de su defensa. Nunca omitas la existencia de los descargos.
PROMPT,

                'cambios' => [
                    'Se añadió verificación de placeholders para eliminar el riesgo de que el documento final tenga texto [entre corchetes] visible.',
                    'Se añadió instrucción de extensión de descargos para casos donde el trabajador fue muy detallado.',
                ],

                'mejor'  => 'mejorado',
                'razon'  => 'El prompt actual funciona bien. La verificación de placeholders es una mejora de calidad importante: un documento entregado al trabajador con texto "[Completa aquí]" es un error profesional grave.',
            ],

            // ── PROMPT 12 ─────────────────────────────────────────────────
            [
                'titulo'   => 'Análisis de Gravedad y Sanción Apropiada',
                'archivo'  => 'app/Services/IAAnalisisSancionService.php — construirPromptAnalisisSancion()',
                'metodo'   => 'construirPromptAnalisisSancion()',
                'modelo'   => 'Gemini 2.5 Flash',
                'proposito'=> 'Analiza la falta disciplinaria, su gravedad y determina qué sanciones son jurídicamente apropiadas. No decide — presenta las opciones con su razonamiento legal para que el cliente decida.',

                'actual' => <<<'PROMPT'
[Prompt de ~800 palabras con 2 categorías de gravedad, 5 rangos de suspensión, análisis de motivos individuales y JSON de 14 campos.]
PROMPT,

                'analisis' => [
                    'FORTALEZA: La distinción de 5 rangos de días de suspensión es precisa y ayuda al cliente a calibrar la sanción.',
                    'FORTALEZA: El campo "mensaje_para_decision" orientado al cliente es un diferencial de UX que pocas plataformas tienen.',
                    'FORTALEZA: El análisis individual de cada motivo del reglamento es fundamental para la solidez jurídica.',
                    'FORTALEZA: El JSON de 14 campos es exhaustivo sin ser redundante.',
                    'ESTADO: Excelente. Es el prompt de mayor madurez jurídica del sistema.',
                    'OBSERVACIÓN MENOR: El límite de 150 palabras por campo puede ser insuficiente para el campo "fundamento_juridico" en casos complejos. Se podría aumentar a 200.',
                ],

                'mejorado' => <<<'PROMPT'
[Versión prácticamente idéntica al actual — solo ajuste en el límite de palabras]

ÚNICO CAMBIO:
En REGLAS DE FORMATO, cambiar:
"Sé CONCISO: máximo 150 palabras por campo de texto"
Por:
"Sé CONCISO: máximo 150 palabras por campo, excepto razonamiento_legal y consideraciones_especiales que pueden tener hasta 200 palabras."
PROMPT,

                'cambios' => [
                    'Única modificación: el límite de palabras de razonamiento_legal y consideraciones_especiales se aumenta de 150 a 200 para casos complejos con múltiple reincidencia o fueros especiales.',
                ],

                'mejor'  => 'actual',
                'razon'  => 'Este prompt está en estado Excelente. El único ajuste propuesto es el límite de palabras, que es un cambio mínimo. Se recomienda mantener la versión actual y hacer el ajuste en la próxima revisión programada de prompts.',
            ],

            // ── PROMPT 13 ─────────────────────────────────────────────────
            [
                'titulo'   => 'Análisis de Recursos de Impugnación',
                'archivo'  => 'app/Services/IAResolucionImpugnacionService.php — construirPrompt()',
                'metodo'   => 'construirPrompt()',
                'modelo'   => 'Gemini 2.5 Flash',
                'proposito'=> 'Analiza el expediente completo y el recurso de impugnación presentado por el trabajador. Recomienda si confirmar, revocar o modificar la sanción y genera el fundamento jurídico para el documento de resolución.',

                'actual' => <<<'PROMPT'
[Prompt de ~600 palabras con cronología del proceso, 3 instrucciones de análisis y JSON de 9 campos incluyendo fundamento_juridico listo para insertar en el documento.]
PROMPT,

                'analisis' => [
                    'FORTALEZA: La auditoría cronológica del proceso como primer paso es una práctica jurídica sólida.',
                    'FORTALEZA: El campo "fundamento_juridico" como texto listo para insertar en el documento final ahorra un paso de redacción.',
                    'FORTALEZA: El campo "riesgos_nulidad" es un diferencial de valor para el cliente.',
                    'FORTALEZA: Las tres opciones de decisión (confirmar/revocar/modificar) cubren todos los escenarios posibles.',
                    'ESTADO: Excelente. Es junto con el Prompt 12 el de mayor sofisticación jurídica.',
                    'OBSERVACIÓN MENOR: No especifica qué hacer si el expediente está incompleto (ej. no hay descargos registrados porque el trabajador no respondió).',
                ],

                'mejorado' => <<<'PROMPT'
[Versión prácticamente idéntica al actual + manejo de expediente incompleto]

ADICIÓN AL BLOQUE DE INSTRUCCIONES:

MANEJO DE EXPEDIENTE INCOMPLETO:
Si los descargos del trabajador están vacíos o el trabajador no respondió, indica en "auditoria_proceso" que el trabajador ejerció su derecho a no declararse y que esto no perjudica automáticamente su posición. Analiza la solidez de las pruebas del empleador en ausencia de descargos.

Si la cronología del proceso muestra irregularidades en los términos legales (ej. la sanción se notificó fuera del plazo), menciónalo en "riesgos_nulidad" con el término específico vulnerado.
PROMPT,

                'cambios' => [
                    'Se añadió instrucción sobre manejo de expediente incompleto (trabajador que no respondió descargos).',
                    'Se clarificó que las irregularidades de plazo deben mencionarse en riesgos_nulidad con el término específico.',
                ],

                'mejor'  => 'mejorado',
                'razon'  => 'El prompt actual es excelente. La mejora sobre expediente incompleto es relevante porque es un caso frecuente: el trabajador no responde el formulario. La instrucción actual podría llevar al modelo a indicar que la ausencia de descargos agrava la situación del trabajador, lo cual no es jurídicamente correcto.',
            ],

        ];
    }
}
