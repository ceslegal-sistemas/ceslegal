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
                $data = $response->json();
                if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                    throw new \RuntimeException('Respuesta de Gemini sin contenido válido');
                }
                return trim($data['candidates'][0]['content']['parts'][0]['text']);
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
        // Buscar fragmentos relevantes de la biblioteca legal
        $biblioteca = app(BibliotecaLegalService::class);
        $queryBiblioteca = implode(' ', array_filter([
            'reglamento interno de trabajo artículos obligatorios Código Sustantivo del Trabajo',
            'jornada laboral horas extras trabajo nocturno dominicales festivos',
            'régimen disciplinario faltas sanciones descargos procedimiento',
            'acoso sexual laboral prevención Ley 2365 2024',
            'Seguridad Salud Trabajo SG-SST obligaciones empleador',
        ]));
        $contextoBiblioteca = $biblioteca->buscarFragmentos($queryBiblioteca, limite: 8, umbral: 0.50);

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
            ? "\nBASE NORMATIVA Y JURISPRUDENCIAL (extraída de la biblioteca legal actualizada):\nUsa estos fragmentos como fundamento jurídico. Cítalos cuando sean aplicables.\n\n{$contextoBiblioteca}\n"
            : '';

        return <<<PROMPT
Eres un abogado laboral colombiano experto en reglamentos internos de trabajo.

Redacta el Reglamento Interno de Trabajo de {$razonSocial} (NIT: {$nit}) con cumplimiento estricto del Artículo 105 y siguientes del Código Sustantivo del Trabajo de Colombia.

INSTRUCCIONES:
- Usa lenguaje formal y técnico-jurídico
- Numera cada artículo de forma consecutiva
- Incluye TODOS los capítulos obligatorios del CST
- Incluye capítulo sobre Política de Prevención de Acoso Sexual según la Ley 2365 de 2024
- Redacta de manera lista para presentar ante el Ministerio del Trabajo
- Basa la redacción en los fragmentos normativos de la base legal que se adjuntan más abajo; NO uses conocimiento genérico de internet
- Si alguna información no fue proporcionada, usa valores razonables y típicos para una empresa colombiana
- NO incluyas comentarios ni aclaraciones fuera del texto del reglamento
- NUNCA uses corchetes ni placeholders como [DÍA], [MES], [AÑO], [NOMBRE], [NÚMERO], [NIT], ni ningún otro; usa siempre los datos reales proporcionados
- La fecha de elaboración es: {$fechaHoy}
- El representante legal firmante es: {$representante}
- NO uses formato Markdown: sin asteriscos (*), sin almohadillas (#), sin guiones de lista al inicio de línea; escribe los títulos de capítulo y artículo completamente en MAYÚSCULAS

CAPÍTULOS OBLIGATORIOS A INCLUIR:
1. Denominación, domicilio, naturaleza y objeto de la empresa
2. Condiciones de admisión y período de prueba
3. Horas de trabajo (jornada ordinaria y nocturna)
4. Trabajo en horas extra, dominicales y festivos
5. Remuneración, modalidades y períodos de pago
6. Vacaciones y permisos
7. Permisos especiales y licencias
8. Régimen disciplinario: faltas leves, graves y muy graves
9. Escalas de sanciones y procedimiento de descargos
10. Reclamos y procedimientos
11. Normas de conducta y comportamiento
12. Seguridad y Salud en el Trabajo (SG-SST)
13. Uso de equipos, uniformes y bienes de la empresa
14. Política de prevención del acoso sexual (Ley 2365 de 2024)
15. Disposiciones finales
{$seccionBiblioteca}
INFORMACIÓN DE LA EMPRESA PROPORCIONADA POR EL ADMINISTRADOR:
{$infoEmpresa}

Redacta el Reglamento Interno de Trabajo completo:
PROMPT;
    }
}
