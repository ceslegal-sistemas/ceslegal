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
        $payload = [
            'contents' => [
                ['parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => [
                'temperature'     => 0.3,
                'maxOutputTokens' => 16384,
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
            'admisión período de prueba contrato trabajo requisitos ingreso',
            'jornada laboral horas extras trabajo nocturno dominicales festivos recargos',
            'vacaciones descanso remunerado licencias maternidad paternidad luto',
            'salario forma de pago remuneración periodicidad modalidades',
            'régimen disciplinario faltas sanciones descargos procedimiento due process',
            'seguridad salud trabajo SG-SST COPASST accidentes laborales EPP obligaciones',
            'acoso laboral sexual comité convivencia Ley 1010 Ley 2365 prevención',
            'protección maternidad embarazo discapacidad fuero sindical sujetos especiales',
        ];

        $fragmentosPorTema = [];
        $yaVisto = [];
        foreach ($queriesTematicas as $query) {
            $resultado = $biblioteca->buscarFragmentos($query, limite: 3, umbral: 0.40);
            if ($resultado && !in_array(md5($resultado), $yaVisto)) {
                $fragmentosPorTema[] = $resultado;
                $yaVisto[] = md5($resultado);
            }
        }
        $contextoBiblioteca = implode("\n\n---\n\n", array_filter($fragmentosPorTema));

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
            ? "\nFRAGMENTOS DE LA BIBLIOTECA JURÍDICA (ÚNICA FUENTE AUTORIZADA PARA CITAS):\n"
              . "Cita artículos, leyes y sentencias ÚNICAMENTE si aparecen en estos fragmentos.\n"
              . "Si un tema no está cubierto por los fragmentos, redacta la obligación en términos\n"
              . "generales sin inventar números de artículos ni referencias normativas.\n\n"
              . $contextoBiblioteca . "\n"
            : "\nADVERTENCIA: La biblioteca legal no devolvió fragmentos. Redacta el RIT en términos\n"
              . "generales sin citar artículos ni leyes específicas.\n";

        return <<<PROMPT
Eres un abogado laboral colombiano experto en reglamentos internos de trabajo.

Redacta el Reglamento Interno de Trabajo de {$razonSocial} (NIT: {$nit}) con cumplimiento estricto del Artículo 105 y siguientes del Código Sustantivo del Trabajo de Colombia.

INSTRUCCIONES:
- Usa lenguaje formal y técnico-jurídico
- Numera cada artículo de forma consecutiva
- Incluye TODOS los capítulos obligatorios del CST
- Incluye capítulo sobre Política de Prevención de Acoso Sexual según la Ley 2365 de 2024
- Redacta de manera lista para presentar ante el Ministerio del Trabajo
- Basa TODAS las citas de artículos y leyes exclusivamente en los fragmentos de la biblioteca jurídica que se adjuntan; NO inventes ni uses artículos de tu entrenamiento que no aparezcan en esos fragmentos
- Si alguna información no fue proporcionada, usa valores razonables y típicos para una empresa colombiana
- NO incluyas comentarios ni aclaraciones fuera del texto del reglamento
- NUNCA uses corchetes ni placeholders como [DÍA], [MES], [AÑO], [NOMBRE], [NÚMERO], [NIT], ni ningún otro; usa siempre los datos reales proporcionados
- La fecha de elaboración es: {$fechaHoy}
- El representante legal firmante es: {$representante}
- NO uses formato Markdown: sin asteriscos (*), sin almohadillas (#), sin guiones de lista al inicio de línea; escribe los títulos de capítulo y artículo completamente en MAYÚSCULAS

CAPÍTULOS OBLIGATORIOS — incluye el contenido mínimo indicado en cada uno:

CAPÍTULO I — DENOMINACIÓN, DOMICILIO Y OBJETO
Identificación completa de la empresa, domicilio principal, sucursales, actividad económica y representante legal.

CAPÍTULO II — ADMISIÓN Y PERÍODO DE PRUEBA
- Requisitos de ingreso sin solicitar documentos prohibidos por ley (certificado de antecedentes judiciales sí; prueba de embarazo NO, Art. 26 Ley 1010/2006)
- Período de prueba: máximo 2 meses en contratos indefinidos; proporcional al plazo en fijos (Art. 78 CST)
- Prohibición de discriminación en el proceso de selección

CAPÍTULO III — JORNADA ORDINARIA DE TRABAJO
- Jornada máxima: 47 horas semanales (Art. 161 CST, Ley 2101/2021 — reducción gradual hasta 42h en 2026)
- Definición de trabajo nocturno: entre las 9 p.m. y las 6 a.m. (Art. 160 CST)
- Descanso obligatorio dominical (Art. 172 CST)
- Horario específico de la empresa (usar datos proporcionados)

CAPÍTULO IV — TRABAJO SUPLEMENTARIO, DOMINICALES Y FESTIVOS
- Límite legal: máximo 2 horas extras diarias y 12 semanales (Art. 167A CST)
- Recargo trabajo extra diurno: 25% sobre el valor hora ordinaria (Art. 168 CST)
- Recargo trabajo extra nocturno: 75% sobre el valor hora ordinaria (Art. 168 CST)
- Recargo trabajo dominical o festivo habitual: 75% (Art. 179 CST)
- Procedimiento de autorización de horas extras

CAPÍTULO V — REMUNERACIÓN Y FORMA DE PAGO
- Modalidades de salario: fijo, variable, en especie (Art. 127-132 CST)
- Períodos de pago: jornales semanal o quincenalmente; sueldos mensualmente (Art. 134 CST)
- Salario en especie: máximo 50% del salario total (Art. 129 CST)
- Lugar y forma de pago; prohibición de pago en especie de bebidas alcohólicas

CAPÍTULO VI — VACACIONES Y PERMISOS
- Vacaciones: 15 días hábiles remunerados por año de servicio (Art. 186 CST)
- Trabajadores menores de 18 años: 15 días hábiles de vacaciones continuas (Art. 187 CST)
- Acumulación de vacaciones: hasta por 4 años con acuerdo escrito (Art. 190 CST)
- Compensación en dinero de vacaciones (Art. 189 CST)
- Permisos remunerados y no remunerados: calamidad, diligencias personales, sufragio

CAPÍTULO VII — LICENCIAS ESPECIALES
- Licencia de maternidad: 18 semanas (Ley 2114/2021)
- Licencia de paternidad: 2 semanas (Ley 2114/2021)
- Licencia de luto: 5 días hábiles por fallecimiento de familiar hasta segundo grado (Ley 1280/2009)
- Licencia por calamidad doméstica
- Licencias no remuneradas

CAPÍTULO VIII — RÉGIMEN DISCIPLINARIO: CLASIFICACIÓN DE FALTAS
- Faltas leves, graves y muy graves con ejemplos concretos de la empresa
- Procedimiento garantista: citación escrita, audiencia de descargos, fallo motivado (Art. 115 CST)

CAPÍTULO IX — ESCALA DE SANCIONES
- Amonestación verbal y escrita
- Suspensión sin sueldo hasta 8 días para la primera vez; hasta 2 meses para reincidencia (Art. 112 CST)
- Multas: hasta el 1% del salario diario por faltas leves (Art. 113 CST)
- Terminación del contrato con justa causa
- Garantía del debido proceso en toda sanción

CAPÍTULO X — RECLAMOS Y PROCEDIMIENTOS
Procedimiento interno de quejas, instancias, plazos de respuesta y autoridades competentes.

CAPÍTULO XI — NORMAS DE CONDUCTA Y COMPORTAMIENTO
- Obligaciones del trabajador (Art. 58 CST) y del empleador (Art. 57 CST)
- Política de uso de dispositivos móviles, uniformes, confidencialidad
- Prohibiciones específicas de la empresa

CAPÍTULO XII — SEGURIDAD Y SALUD EN EL TRABAJO (SG-SST)
- Política de SST y compromiso de la alta dirección (Decreto 1072/2015, Art. 2.2.4.6.5)
- Obligaciones del empleador en SST (Art. 56 CST; Art. 8 Decreto 1295/1994)
- Obligaciones del trabajador en SST (Art. 58 CST núm. 7)
- COPASST (empresas ≥10 trabajadores) o Vigía de SST (empresas <10) con funciones y periodicidad
- Programas de prevención según riesgos principales de la empresa
- Reporte e investigación de accidentes de trabajo (Art. 62 Decreto 1295/1994); notificación a ARL y Mintrabajo
- Uso obligatorio de EPP y consecuencias de incumplimiento
- Prohibición de trabajar bajo efectos de alcohol o sustancias psicoactivas

CAPÍTULO XIII — USO DE EQUIPOS, UNIFORMES Y BIENES DE LA EMPRESA
Responsabilidad del trabajador sobre activos, política de daños, uso del uniforme y devolución a la terminación.

CAPÍTULO XIV — COMITÉ DE CONVIVENCIA LABORAL Y PREVENCIÓN DE ACOSO
- Definición y modalidades de acoso laboral (Art. 2 Ley 1010/2006)
- Comité de Convivencia Laboral: conformación paritaria, elección, período, funciones y periodicidad de reuniones (Resolución 652/2012 y 1356/2012)
- Mecanismos de prevención: capacitación, canales de denuncia, seguimiento
- Procedimiento interno de queja por acoso laboral
- Política de prevención del acoso sexual (Art. 12 Ley 2365/2024): definición, conductas prohibidas, responsabilidades del empleador, protocolo de atención

CAPÍTULO XV — PROTECCIÓN DE SUJETOS DE ESPECIAL PROTECCIÓN
- Protección a la mujer embarazada: prohibición de despido sin autorización del Inspector del Trabajo (Art. 239 CST)
- Prohibición de solicitar prueba de embarazo para acceso al trabajo o continuidad (Art. 241A CST)
- Protección a personas en situación de discapacidad (Ley 361/1997, Art. 26)
- Protección a personas con fuero sindical (Art. 405 CST)
- No discriminación por orientación sexual, raza, religión o ideología (Art. 10 CST)

CAPÍTULO XVI — DISPOSICIONES FINALES
Vigencia, modificaciones, publicación, depósito ante el Inspector del Trabajo.
{$seccionBiblioteca}
INFORMACIÓN DE LA EMPRESA PROPORCIONADA POR EL ADMINISTRADOR:
{$infoEmpresa}

Redacta el Reglamento Interno de Trabajo completo:
PROMPT;
    }
}
