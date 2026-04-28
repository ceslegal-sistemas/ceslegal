<?php

namespace App\Services;

use App\Models\Empresa;
use App\Services\BibliotecaLegalService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;

class RITGeneratorService
{
    /**
     * Genera el texto completo del RIT usando Gemini a partir de las respuestas del cuestionario F2.
     */
    public function generarTextoRIT(array $respuestas, Empresa $empresa): string
    {
        $config  = config('services.ia.gemini', []);
        $apiKey  = $config['api_key'] ?? '';
        $model   = $config['model'] ?? 'gemini-2.5-flash';
        $url     = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $prompt  = $this->construirPrompt($respuestas, $empresa);

        // Limpiar bytes UTF-8 inválidos que provienen de fragmentos de PDFs/DOCX
        // y que rompen json_encode al construir el payload
        $prompt = preg_replace(
            '/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u',
            '',
            $prompt
        ) ?? iconv('UTF-8', 'UTF-8//IGNORE', $prompt);

        $payload = [
            'contents' => [
                ['parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => [
                'temperature'     => 0.3,
                'maxOutputTokens' => 32768,
                'topP'            => 0.95,
                'topK'            => 40,
            ],
        ];

        Log::info('RITGeneratorService: generando texto con Gemini', [
            'empresa_id' => $empresa->id,
            'model'      => $model,
        ]);

        // Reintentos con backoff exponencial para errores transitorios (503, 429)
        $maxIntentos = 3;
        $esperas     = [5, 15, 30]; // segundos entre intentos
        $lastError   = null;

        for ($intento = 1; $intento <= $maxIntentos; $intento++) {
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(120)
                ->post($url, $payload);

            if ($response->successful()) {
                $data  = $response->json();
                $parts = $data['candidates'][0]['content']['parts'] ?? [];
                // gemini-2.5-flash (thinking model): el texto real está en el último part sin "thought"
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
                if (empty($texto)) {
                    throw new \RuntimeException('Respuesta de Gemini sin contenido válido');
                }
                return trim($texto);
            }

            $status    = $response->status();
            $lastError = $response->body();

            // Solo reintentar en errores transitorios de servidor
            $esTransitorio = in_array($status, [429, 500, 502, 503, 504]);

            Log::warning('RITGeneratorService: fallo en intento ' . $intento, [
                'empresa_id' => $empresa->id,
                'status'     => $status,
                'reintento'  => $esTransitorio && $intento < $maxIntentos,
            ]);

            if (!$esTransitorio || $intento === $maxIntentos) {
                break;
            }

            sleep($esperas[$intento - 1]);
        }

        throw new \RuntimeException('Error en API Gemini: ' . $lastError);
    }

    /**
     * Genera el documento Word (.docx) con el texto del RIT.
     * Retorna la ruta relativa dentro de storage/app/private/.
     */
    /**
     * Genera el DOCX y lo guarda en storage/app/private/rits/{id}/reglamento.docx.
     * Retorna la ruta relativa. Lanza excepción si no puede escribir.
     */
    public function generarDocumentoWord(string $textoRIT, Empresa $empresa): string
    {
        $directorio = "private/rits/{$empresa->id}";
        Storage::makeDirectory($directorio);

        $rutaRelativa = "{$directorio}/reglamento.docx";
        $rutaAbsoluta = storage_path("app/{$rutaRelativa}");

        $this->escribirDocx($textoRIT, $empresa, $rutaAbsoluta);

        Log::info('RITGeneratorService: documento Word guardado', [
            'empresa_id' => $empresa->id,
            'ruta'       => $rutaRelativa,
        ]);

        return $rutaRelativa;
    }

    /**
     * Genera el DOCX en un archivo temporal del sistema y retorna su ruta absoluta.
     * Usar para descargas en servidores con permisos restringidos en storage.
     */
    public function generarDocumentoWordTemp(string $textoRIT, Empresa $empresa): string
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'rit_') . '.docx';
        $this->escribirDocx($textoRIT, $empresa, $tmpPath);
        return $tmpPath;
    }

    /**
     * Genera el DOCX, lo almacena en el disco 'public' y retorna la ruta relativa.
     * Usa un temp file intermedio para evitar errores de permisos en directorios storage.
     * Retorna null si no pudo guardar en disco.
     */
    public function guardarDocxPublico(string $textoRIT, Empresa $empresa): ?string
    {
        try {
            $tmpPath     = tempnam(sys_get_temp_dir(), 'rit_') . '.docx';
            $this->escribirDocx($textoRIT, $empresa, $tmpPath);

            $rutaPublica = "rits/{$empresa->id}/reglamento.docx";
            Storage::disk('public')->put($rutaPublica, file_get_contents($tmpPath));
            @unlink($tmpPath);

            return $rutaPublica;
        } catch (\Throwable $e) {
            Log::warning('RITGeneratorService: no se pudo guardar DOCX en disco público', [
                'empresa_id' => $empresa->id,
                'error'      => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function escribirDocx(string $textoRIT, Empresa $empresa, string $rutaAbsoluta): void
    {
        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Times New Roman');
        $phpWord->setDefaultFontSize(12);

        $section = $phpWord->addSection([
            'marginTop'    => Converter::cmToTwip(2.5),
            'marginBottom' => Converter::cmToTwip(2.5),
            'marginLeft'   => Converter::cmToTwip(3),
            'marginRight'  => Converter::cmToTwip(2.5),
        ]);

        $section->addText(
            'REGLAMENTO INTERNO DE TRABAJO',
            ['bold' => true, 'size' => 14, 'name' => 'Times New Roman'],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 120]
        );

        $section->addText(
            strtoupper($empresa->razon_social),
            ['bold' => true, 'size' => 12, 'name' => 'Times New Roman'],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 240]
        );

        $lineas = explode("\n", $textoRIT);
        foreach ($lineas as $linea) {
            $linea = rtrim($linea);

            if ($linea === '') {
                $section->addTextBreak(1);
                continue;
            }

            // Detectar líneas completamente en negrita markdown (**texto**)
            $esNegritaMarkdown = preg_match('/^\*{1,2}(.+?)\*{1,2}$/', $linea, $m);
            $textoLimpio = $esNegritaMarkdown
                ? trim($m[1])
                : preg_replace('/\*{1,2}([^*]+)\*{1,2}/', '$1', $linea); // quitar ** inline

            // Quitar guiones, asteriscos, almohadillas al inicio (e.g. "- ARTÍCULO 1.")
            $textoLimpio = ltrim($textoLimpio, '-*# ');
            $textoLimpio = trim($textoLimpio);

            // Detectar títulos: CAPÍTULO, ARTÍCULO, o línea markdown-bold
            $esTitulo = $esNegritaMarkdown
                || preg_match('/^(CAPÍTULO|ARTÍCULO|ART\.)\s*/ui', $textoLimpio);

            if ($esTitulo) {
                $section->addText(
                    $textoLimpio,
                    ['bold' => true, 'size' => 12, 'name' => 'Times New Roman'],
                    ['spaceAfter' => 80, 'spaceBefore' => 120]
                );
            } else {
                $section->addText(
                    $textoLimpio,
                    ['size' => 12, 'name' => 'Times New Roman'],
                    ['spaceAfter' => 60, 'lineHeight' => 1.5]
                );
            }
        }

        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($rutaAbsoluta);
    }

    private function construirPrompt(array $r, Empresa $empresa): string
    {
        // RAG por área temática: múltiples consultas para mayor cobertura de la biblioteca
        $biblioteca = app(BibliotecaLegalService::class);

        $queriesTematicas = [
            'admisión período de prueba jornada laboral horas extras recargos nocturnos',
            'vacaciones licencias maternidad paternidad salario forma de pago periodicidad',
            'régimen disciplinario faltas sanciones procedimiento descargos suspensión',
            'seguridad salud trabajo SG-SST COPASST vigía accidentes exámenes médicos EPP Decreto 1072',
            'acoso laboral sexual comité convivencia Ley 1010 Ley 2365 protocolo denuncia',
        ];

        $fragmentosPorTema = [];
        $yaVisto = [];
        foreach ($queriesTematicas as $query) {
            $resultado = $biblioteca->buscarFragmentos($query, limite: 5, umbral: 0.35);
            if ($resultado && !in_array(md5($resultado), $yaVisto)) {
                $fragmentosPorTema[] = $resultado;
                $yaVisto[] = md5($resultado);
            }
        }
        $contextoBiblioteca = implode("\n\n---\n\n", array_filter($fragmentosPorTema));
        // Limitar el contexto de biblioteca para no saturar el prompt
        if (strlen($contextoBiblioteca) > 8000) {
            $contextoBiblioteca = substr($contextoBiblioteca, 0, 8000) . "\n[...fragmentos adicionales omitidos por límite de longitud]";
        }

        $razonSocial = $empresa->razon_social;
        $nit         = $empresa->nit;

        // Helpers para aplanar arrays a texto legible
        $lista  = fn($arr) => is_array($arr) ? implode(', ', array_filter($arr)) : ($arr ?? '');
        $lineas = fn($arr) => is_array($arr) ? implode("\n  ", array_filter($arr)) : ($arr ?? '');

        // Cargos: array de {nombre_cargo, puede_sancionar}
        $cargosTexto = '';
        foreach ((array)($r['cargos'] ?? []) as $c) {
            $nombre   = $c['nombre_cargo'] ?? '';
            $sanciona = ($c['puede_sancionar'] ?? false) ? 'puede sancionar' : 'no sanciona';
            if ($nombre) $cargosTexto .= "  - {$nombre} ({$sanciona})\n";
        }

        // Sucursales: array de {ciudad, direccion, num_trabajadores}
        $sucursalesTexto = '';
        foreach ((array)($r['sucursales'] ?? []) as $s) {
            $ciudad = $s['ciudad'] ?? '';
            $dir    = $s['direccion'] ?? '';
            $trab   = $s['num_trabajadores'] ?? '';
            if ($ciudad) $sucursalesTexto .= "  - {$ciudad}: {$dir}, {$trab} trabajadores\n";
        }

        // Beneficios extralegalesdocument: array de {nombre_beneficio, descripcion}
        $beneficiosTexto = '';
        foreach ((array)($r['beneficios_extralegales'] ?? []) as $b) {
            $nb = $b['nombre_beneficio'] ?? '';
            $db = $b['descripcion'] ?? '';
            if ($nb) $beneficiosTexto .= "  - {$nb}: {$db}\n";
        }

        $representante = $empresa->representante_legal ?? '';
        $fechaHoy      = now()->locale('es')->translatedFormat('j \d\e F \d\e Y');

        $infoEmpresa = "
EMPRESA Y ACTIVIDAD
- Razón social: {$razonSocial}
- NIT: {$nit}
- Domicilio: " . ($r['domicilio'] ?? '') . "
- Representante Legal: {$representante}
- Actividad económica principal: " . ($r['actividad_economica'] ?? '') . "
- Actividades secundarias: " . ($r['actividades_secundarias'] ?? 'N/A') . "
- Número de trabajadores: " . ($r['num_trabajadores'] ?? '') . "
- Tiene sucursales: " . ($r['tiene_sucursales'] === 'si' ? "Sí\n{$sucursalesTexto}" : 'No') . "

ESTRUCTURA ORGANIZACIONAL
- Cargos:\n{$cargosTexto}
- Tiene manual de funciones: " . ($r['tiene_manual_funciones'] ?? '') . "
- Tipos de contrato: " . $lista($r['tipos_contrato'] ?? []) . "
- Usa trabajadores de misión (temporal): " . ($r['tiene_trabajadores_mision'] ?? 'no') . "

JORNADA LABORAL
- Horario de entrada: " . ($r['horario_entrada'] ?? '') . "
- Horario de salida (L-V): " . ($r['horario_salida'] ?? '') . "
- Jornada sábados: " . ($r['jornada_sabado'] ?? 'no') . "
- Hora salida sábados: " . ($r['horario_salida_sabado'] ?? 'N/A') . "
- Trabaja dominicales/festivos: " . ($r['trabaja_dominicales'] ?? 'no') . "
- Tiene turnos rotativos/nocturnos: " . ($r['tiene_turnos'] ?? 'no') . "
- Descripción turnos: " . ($r['descripcion_turnos'] ?? 'N/A') . "
- Control de asistencia: " . ($r['control_asistencia'] ?? '') . "
- Política horas extras: " . ($r['politica_horas_extras'] ?? '') . "

SALARIO Y BENEFICIOS
- Forma de pago: " . ($r['forma_pago'] ?? '') . "
- Periodicidad de pago: " . ($r['periodicidad_pago'] ?? '') . "
- Maneja comisiones/bonificaciones: " . ($r['maneja_comisiones'] ?? 'no') . "
- Tipo de comisiones: " . ($r['tipo_comisiones'] ?? 'N/A') . "
- Beneficios extralegales:\n" . ($beneficiosTexto ?: "  - Ninguno\n") . "
- Política de permisos personales: " . ($r['politica_permisos'] ?? '') . "
- Licencias especiales: " . ($r['tiene_licencias_especiales'] ?? 'no') . "
- Descripción licencias: " . ($r['descripcion_licencias'] ?? 'N/A') . "

RÉGIMEN DISCIPLINARIO
- Faltas leves: " . $lista($r['faltas_leves'] ?? []) . "
- Faltas graves: " . $lista($r['faltas_graves'] ?? []) . "
- Faltas muy graves: " . $lista($r['faltas_muy_graves'] ?? []) . "
- Sanciones contempladas: " . $lista($r['sanciones_contempladas'] ?? []) . "

SEGURIDAD Y SALUD EN EL TRABAJO
- Tiene SG-SST implementado: " . ($r['tiene_sg_sst'] ?? '') . "
- Riesgos principales: " . $lista($r['riesgos_principales'] ?? []) . "
- Usa EPP: " . ($r['tiene_epp'] ?? 'no') . "
- EPP requerido: " . ($r['epp_descripcion'] ?? 'N/A') . "

CONDUCTA Y CONVIVENCIA
- Política uso celular: " . ($r['politica_celular'] ?? '') . "
- Usa uniforme: " . ($r['usa_uniforme'] ?? 'no') . "
- Tiene código de ética: " . ($r['tiene_codigo_etica'] ?? 'no') . "
- Política de confidencialidad: " . ($r['politica_confidencialidad'] ?? '') . "
- Qué quiere prevenir: " . ($r['que_quiere_prevenir'] ?? '') . "
";

        $seccionBiblioteca = $contextoBiblioteca
            ? "\nFRAGMENTOS DE LA BIBLIOTECA JURÍDICA (FUENTE AUTORIZADA PARA CITAS DE ARTÍCULOS):\n"
              . "REGLA DE CITAS: Usa números de artículo y referencias normativas ÚNICAMENTE cuando\n"
              . "aparezcan en estos fragmentos. Si un artículo específico no está en los fragmentos,\n"
              . "omite el número pero REDACTA EL CONTENIDO IGUAL — no suprimas el capítulo.\n"
              . "REGLA DE CONTENIDO: TODOS los capítulos son OBLIGATORIOS independientemente de los\n"
              . "fragmentos disponibles. La falta de fragmentos sobre un tema nunca justifica omitir\n"
              . "o reducir un capítulo — usa el texto de referencia indicado en cada capítulo.\n\n"
              . $contextoBiblioteca . "\n"
            : "\nADVERTENCIA: La biblioteca legal no devolvió fragmentos. Redacta TODOS los capítulos\n"
              . "con contenido completo sin citar números de artículos específicos.\n";

        return <<<PROMPT
Eres un abogado laboral colombiano experto en reglamentos internos de trabajo.

Redacta el Reglamento Interno de Trabajo de {$razonSocial} (NIT: {$nit}) con cumplimiento estricto del Artículo 105 y siguientes del Código Sustantivo del Trabajo de Colombia.

INSTRUCCIONES DE CONTENIDO:
- Usa lenguaje formal y técnico-jurídico
- Numera cada artículo de forma consecutiva (Artículo 1, Artículo 2, ...)
- Incluye TODOS los capítulos obligatorios del CST
- Incluye capítulo sobre Política de Prevención de Acoso Sexual según la Ley 2365 de 2024
- Redacta de manera lista para presentar ante el Ministerio del Trabajo
- Regla de citas: usa números de artículo y nombres de ley ÚNICAMENTE cuando aparezcan en los fragmentos de biblioteca adjuntos; si no aparecen, omite el número pero redacta el contenido completo
- Si alguna información no fue proporcionada, usa valores razonables y típicos para una empresa colombiana
- NO incluyas comentarios ni aclaraciones fuera del texto del reglamento
- NUNCA uses corchetes ni placeholders ([DÍA], [MES], [AÑO], [NOMBRE], [NIT], etc.); usa siempre los datos reales
- La fecha de elaboración es: {$fechaHoy}
- El representante legal firmante es: {$representante}

INSTRUCCIONES DE FORMATO — CRÍTICAS, INCUMPLIRLAS INVALIDA EL DOCUMENTO:
1. CADA artículo es un párrafo independiente de mínimo 60 palabras. NUNCA un resumen de una línea.
2. NUNCA colapses varios artículos en una sola línea. Ejemplo PROHIBIDO: "Artículo 5-8: SST..."
3. NUNCA uses guiones (-), asteriscos (*), almohadillas (#) ni viñetas al inicio de línea.
4. El título del capítulo va en línea propia en MAYÚSCULAS: CAPÍTULO I — DENOMINACIÓN, DOMICILIO Y OBJETO
5. Cada artículo comienza en línea propia así: ARTÍCULO 1. NOMBRE DEL ARTÍCULO. Seguido del texto completo en el mismo párrafo.
6. Para listas dentro de un artículo usa numeración interna: "1) ... 2) ... 3) ..." integrada en el párrafo.
7. Sin Markdown: sin asteriscos, sin # ni **.

EJEMPLO DE FORMATO CORRECTO (sigue este modelo exactamente):
CAPÍTULO II — ADMISIÓN Y PERÍODO DE PRUEBA

ARTÍCULO 4. REQUISITOS DE INGRESO. Para ingresar como trabajador de {$razonSocial} se requerirá la presentación de hoja de vida con soportes, fotocopia del documento de identidad, certificados de estudios y experiencia laboral, certificado de antecedentes judiciales y disciplinarios, y los demás documentos que la empresa estime pertinentes conforme a la naturaleza del cargo. Queda expresamente prohibido solicitar prueba de embarazo o estado de gravidez como requisito de ingreso, así como cualquier otra condición que configure discriminación en el proceso de selección.

ARTÍCULO 5. PERÍODO DE PRUEBA. El período de prueba deberá pactarse siempre por escrito como cláusula expresa del contrato de trabajo. En contratos a término indefinido, el período de prueba no podrá exceder de dos (2) meses. En contratos a término fijo, el período de prueba no podrá exceder de la quinta parte del término pactado, sin que pueda exceder de dos (2) meses. Durante el período de prueba cualquiera de las partes podrá dar por terminado el contrato en cualquier momento, sin previo aviso y sin indemnización, pero la terminación debe ser fundamentada y comunicada por escrito.

CAPÍTULOS OBLIGATORIOS — redacta CADA artículo como párrafo completo, no como resumen:

CAPÍTULO I — DENOMINACIÓN, DOMICILIO Y OBJETO
Artículos requeridos: ámbito de aplicación del reglamento, denominación y NIT de la empresa, domicilio principal y sucursales, actividad económica, representante legal y su facultad para sancionar.

CAPÍTULO II — ADMISIÓN Y PERÍODO DE PRUEBA
Artículos requeridos: documentos exigidos para ingreso (incluir que el certificado de antecedentes judiciales ES permitido pero la prueba de embarazo NO); período de prueba estipulado siempre por escrito — máximo 2 meses en indefinidos y proporcional al plazo en fijos; prohibición expresa de discriminación en selección.
ARTÍCULO OBLIGATORIO VERBATIM — incluir esta regla exacta: "El período de prueba deberá pactarse siempre por escrito como cláusula expresa del contrato de trabajo. La terminación durante el período de prueba debe comunicarse con fundamentación y por escrito."

CAPÍTULO III — JORNADA ORDINARIA DE TRABAJO
Artículos requeridos: jornada máxima semanal (47h con reducción progresiva a 42h — Ley 2101/2021); definición de trabajo diurno y nocturno con horas exactas; descanso obligatorio en dominicales y festivos; horario específico de la empresa (usar datos del cuestionario); empleados de dirección y confianza excluidos de jornada máxima.

CAPÍTULO IV — TRABAJO SUPLEMENTARIO, DOMINICALES Y FESTIVOS
Artículos requeridos: límite de 2 horas extras diarias y 12 semanales (texto literal: "El trabajo suplementario no podrá exceder de dos (2) horas diarias ni de doce (12) horas semanales"); recargos exactos — extra diurno 25%, extra nocturno 75%, dominical/festivo 75%; autorización previa y escrita para horas extras; registro de trabajo suplementario por trabajador.

CAPÍTULO V — REMUNERACIÓN Y FORMA DE PAGO
Artículos requeridos: modalidades de salario (por unidad de tiempo, por obra o tarea, variable); período de pago (jornales: semanal o quincenalmente; sueldos: mensualmente); salario en especie máximo 50% del total; prohibición de pago con bebidas alcohólicas ni sustancias alucinógenas; prohibición de trueque de salario por mercancías o víveres; comprobante de pago discriminado.

CAPÍTULO VI — VACACIONES Y PERMISOS
Artículos requeridos: 15 días hábiles remunerados por año de servicio; período de disfrute acordado entre partes con aviso previo de 15 días; registro especial de vacaciones que lleva la empresa; acumulación hasta 4 años por acuerdo escrito entre las partes; compensación en dinero solo en los casos autorizados por ley; permisos remunerados (calamidad doméstica, sufragio, diligencias personales con aviso previo).

CAPÍTULO VII — LICENCIAS ESPECIALES
Artículos requeridos: licencia de maternidad 18 semanas remuneradas (Ley 2114/2021); licencia de paternidad 2 semanas remuneradas (Ley 2114/2021); licencia de luto 5 días hábiles por cónyuge, compañero permanente o familiar hasta segundo grado de consanguinidad (Ley 1280/2009); licencia por calamidad doméstica grave; licencias no remuneradas.

CAPÍTULO VIII — RÉGIMEN DISCIPLINARIO: CLASIFICACIÓN DE FALTAS
Artículos requeridos: definición de falta disciplinaria; catálogo completo de faltas LEVES con ejemplos concretos de la empresa; catálogo completo de faltas GRAVES con ejemplos concretos; catálogo de faltas MUY GRAVES con ejemplos concretos; procedimiento garantista completo: comunicación escrita de cargos, traslado de pruebas, plazo mínimo 5 días hábiles para descargos, audiencia de descargos, fallo motivado por escrito.

CAPÍTULO IX — ESCALA DE SANCIONES
Artículos requeridos: amonestación verbal (faltas leves primera vez); amonestación escrita (faltas leves reiteradas); suspensión sin remuneración hasta 8 días primera vez y hasta 2 meses en reincidencia; multas por faltas leves máximo 1/5 del salario diario destinadas a premios para trabajadores; terminación con justa causa; garantía del debido proceso y proporcionalidad en toda sanción; derecho del trabajador a impugnar la sanción.

CAPÍTULO X — RECLAMOS Y PROCEDIMIENTOS
Artículos requeridos: instancias internas para presentar reclamos; plazos de respuesta máximo 15 días hábiles; procedimiento cuando el reclamo involucra al superior jerárquico; acceso a Ministerio del Trabajo o jurisdicción laboral cuando no hay acuerdo.

CAPÍTULO XI — NORMAS DE CONDUCTA Y COMPORTAMIENTO
Artículos requeridos: obligaciones especiales del trabajador (puntualidad, cuidado de bienes, respeto, confidencialidad, obediencia razonable); obligaciones del empleador (instrumentos, seguridad, pago oportuno, respeto a la dignidad); prohibiciones del trabajador (sustracción de bienes, actividades personales en jornada, consumo de alcohol/sustancias, proselitismo, uso ilícito de recursos); política de uso de celulares/dispositivos personales en jornada; política de confidencialidad de información empresarial.

CAPÍTULO XII — SEGURIDAD Y SALUD EN EL TRABAJO (SG-SST)
ARTÍCULOS OBLIGATORIOS — cada uno como párrafo completo de mínimo 60 palabras:
A) Política de SST: compromiso de la alta dirección, recursos asignados, ámbito de aplicación
B) Obligaciones del empleador en SST: afiliar a ARL, proveer EPP, garantizar condiciones seguras, realizar exámenes médicos ocupacionales de ingreso/periódicos/egreso, investigar accidentes y enfermedades laborales
C) Obligaciones del trabajador en SST: usar correctamente el EPP, reportar condiciones inseguras, asistir a capacitaciones, no manipular equipos de seguridad sin autorización
D) Vigía de SST (empresas con menos de 10 trabajadores) o COPASST (10 o más): designación, período de 2 años, reunión mensual, funciones de vigilancia
E) Exámenes médicos ocupacionales: ingreso, periódicos y egreso; obligatorios; reserva absoluta de la información médica
F) Reporte de accidentes: el trabajador notifica al empleador el mismo día; la empresa notifica a la ARL dentro de los 2 días hábiles siguientes; investigación interna obligatoria
G) EPP: uso obligatorio según matriz de riesgos del cargo; incumplimiento = falta disciplinaria grave
H) Prohibición absoluta de presentarse o permanecer en el trabajo bajo efectos de alcohol, sustancias psicoactivas o medicamentos que alteren el estado de alerta

CAPÍTULO XIII — USO DE EQUIPOS, UNIFORMES Y BIENES DE LA EMPRESA
Artículos requeridos: asignación formal de equipos con acta; responsabilidad del trabajador por daño causado por negligencia o mal uso; política de uniformes (si aplica) o presentación personal; devolución formal de todos los bienes al terminar el contrato.

CAPÍTULO XIV — COMITÉ DE CONVIVENCIA LABORAL Y PREVENCIÓN DE ACOSO
ARTÍCULOS OBLIGATORIOS — cada uno como párrafo completo de mínimo 60 palabras:
A) Definición y modalidades de acoso laboral: persecución, discriminación, entorpecimiento, inequidad y desprotección (Ley 1010/2006, Art. 2)
B) Comité de Convivencia Laboral: conformación paritaria, elección democrática de representantes de los trabajadores, período de 2 años, reunión mensual ordinaria y extraordinaria cuando se presente un caso (Resolución 652/2012)
C) Funciones del Comité: recibir quejas, examinar conductas, facilitar diálogo entre las partes, formular recomendaciones, hacer seguimiento, informar a la dirección
D) Procedimiento interno de queja: presentación escrita al Comité, investigación en 30 días, audiencia de conciliación, informe final con medidas correctivas concretas
E) Política de prevención del acoso sexual (Ley 2365/2024): definición, conductas que lo constituyen, canal confidencial de denuncia, protocolo de atención, prohibición de represalias
F) Sanciones por acoso laboral o sexual: falta muy grave con terminación justificada, denuncia ante Inspector del Trabajo, acciones penales según gravedad

CAPÍTULO XV — PROTECCIÓN DE SUJETOS DE ESPECIAL PROTECCIÓN
Artículos requeridos: mujer embarazada y en lactancia — prohibición de despido sin autorización del Inspector del Trabajo, licencia de maternidad de 18 semanas; prohibición de solicitar prueba de embarazo para acceso al empleo o continuidad; personas en situación de discapacidad — estabilidad laboral reforzada; trabajadores con fuero sindical — prohibición de despido, traslado o desmejora sin autorización judicial; no discriminación por orientación sexual, raza, religión o ideología.

CAPÍTULO XVI — DISPOSICIONES FINALES
Artículos requeridos: vigencia desde la publicación a los trabajadores; procedimiento para modificaciones (comunicación a trabajadores y depósito ante Ministerio del Trabajo); obligación de publicar en lugar visible y entregar copia a cada trabajador; depósito ante la Dirección Territorial del Ministerio del Trabajo competente; incorporación del RIT a todos los contratos individuales de trabajo.
{$seccionBiblioteca}
INFORMACIÓN DE LA EMPRESA PROPORCIONADA POR EL ADMINISTRADOR:
{$infoEmpresa}

VERIFICACIÓN OBLIGATORIA ANTES DE TERMINAR:
Antes de concluir, confirma que tu respuesta contiene estos 4 capítulos críticos con contenido sustancial (mínimo 4 artículos cada uno):
- CAPÍTULO XII (SG-SST): política SST, obligaciones empleador, obligaciones trabajador, COPASST/Vigía, exámenes médicos, reporte accidentes, EPP
- CAPÍTULO XIV (Acoso): definición acoso, Comité Convivencia Laboral, procedimiento queja, política acoso sexual Ley 2365/2024
Si alguno está ausente o tiene menos de 4 artículos, complétalo antes de enviar la respuesta.

Redacta el Reglamento Interno de Trabajo completo:
PROMPT;
    }
}
