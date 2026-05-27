<?php

namespace App\Services;

use App\Models\ArticuloLegal;
use App\Models\Empresa;
use App\Services\BibliotecaLegalService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Dompdf\Dompdf;
use Dompdf\Options;
use Dompdf\Adapter\CPDF as CpdfAdapter;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;

class RITGeneratorService
{
    /** Modelo que terminó generando el texto. Consultable desde el código llamador. */
    public string $modeloUsado = '';

    /** True solo cuando se llegó al último recurso (flash-lite). */
    public bool $esFallbackLite = false;

    /**
     * Genera el texto completo del RIT usando Gemini, capítulo por capítulo.
     */
    public function generarTextoRIT(array $respuestas, Empresa $empresa): string
    {
        return $this->generarCapitulosRIT($respuestas, $empresa);
    }

    /**
     * Genera el RIT capítulo por capítulo con soporte de callback de progreso.
     * El callback $onProgress recibe ($capActual, $total, $tituloCapitulo).
     */
    public function generarCapitulosRIT(
        array     $respuestas,
        Empresa   $empresa,
        ?\Closure $onProgress = null
    ): string {
        $biblioteca     = app(BibliotecaLegalService::class);
        $capitulos      = self::getCapitulos();
        $total          = count($capitulos);
        $partes         = [];
        $articuloInicio = 1;

        foreach (array_values($capitulos) as $idx => $cap) {
            if ($onProgress) {
                $onProgress($idx + 1, $total, $cap['titulo']);
            }

            Log::info('RITGeneratorService: generando capítulo', [
                'empresa_id'      => $empresa->id,
                'capitulo'        => $cap['numero'],
                'titulo'          => $cap['titulo'],
                'articulo_inicio' => $articuloInicio,
            ]);

            $rag                   = $biblioteca->buscarFragmentos($cap['query_rag'], limite: 8, umbral: 0.30);
            $articulosObligatorios = $this->obtenerArticulosObligatorios($cap['codigos_obligatorios'] ?? []);
            $contextoEmpresa       = $this->construirContextoEmpresa($cap, $respuestas, $empresa);

            $prompt = $this->construirPrompt(
                cap:                   $cap,
                contextoEmpresa:       $contextoEmpresa,
                articulosObligatorios: $articulosObligatorios,
                rag:                   $rag,
                articuloInicio:        $articuloInicio,
                empresa:               $empresa,
            );

            $prompt = preg_replace(
                '/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u',
                '', $prompt
            ) ?? iconv('UTF-8', 'UTF-8//IGNORE', $prompt);

            $textoCapitulo = $this->llamarGemini($prompt, $empresa->id);

            if (!$this->validarCapitulo($textoCapitulo, $cap['titulo'])) {
                Log::warning('RITGeneratorService: capítulo inválido, reintentando', [
                    'empresa_id' => $empresa->id,
                    'capitulo'   => $cap['numero'],
                ]);
                $textoCapitulo = $this->llamarGemini($prompt, $empresa->id);
            }

            $partes[] = trim($textoCapitulo);

            preg_match_all('/^ARTÍCULO\s+\d+/imu', $textoCapitulo, $matches);
            $articuloInicio += max(1, count($matches[0]));
        }

        return implode("\n\n", $partes);
    }

    // ── Métodos internos de generación ────────────────────────────────────────

    private function llamarGemini(string $prompt, int $empresaId = 0): string
    {
        $config = config('services.ia.gemini', []);
        $apiKey = $config['api_key'] ?? '';

        $modelPrincipal = 'gemini-2.5-flash';
        $modelosCascada = ['gemini-2.5-flash', 'gemini-2.5-flash-lite'];

        // $prompt = $this->construirPrompt($respuestas, $empresa);

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
            ],
        ];

        $lastError    = null;
        $totalModelos = count($modelosCascada);

        foreach (array_values($modelosCascada) as $idx => $model) {
            $url         = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
            $esUltimo    = ($idx === $totalModelos - 1);
            $maxIntentos = 2;
            $esperas     = [10, 30];

            Log::info('RITGeneratorService: llamada Gemini', [
                'empresa_id'     => $empresaId,
                'model'          => $model,
                'intento_modelo' => $idx + 1,
            ]);

            $sobrecarga = false;

            for ($intento = 1; $intento <= $maxIntentos; $intento++) {
                $response = Http::withHeaders(['Content-Type' => 'application/json'])
                    ->timeout(90)
                    ->post($url, $payload);

                if ($response->successful()) {
                    $data  = $response->json();
                    $parts = $data['candidates'][0]['content']['parts'] ?? [];

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

                    $this->modeloUsado    = $model;
                    $this->esFallbackLite = ($model === 'gemini-2.5-flash-lite');

                    if ($idx > 0) {
                        Log::info('RITGeneratorService: usando modelo de respaldo', [
                            'empresa_id'    => $empresaId,
                            'model_usado'   => $model,
                            'model_primario' => $modelPrincipal,
                        ]);
                    }

                    return trim($texto);
                }

                $status    = $response->status();
                $lastError = $response->body();

                $esSobrecarga  = in_array($status, [429, 503]);
                $esTransitorio = in_array($status, [500, 502, 504]);

                Log::warning('RITGeneratorService: fallo en intento', [
                    'empresa_id' => $empresaId,
                    'model'      => $model,
                    'intento'    => $intento,
                    'status'     => $status,
                ]);

                if ($esSobrecarga) {
                    if ($intento < $maxIntentos) {
                        sleep($esperas[$intento - 1]);
                    } else {
                        $sobrecarga = true;
                        break;
                    }
                } elseif ($esTransitorio && $intento < $maxIntentos) {
                    sleep($esperas[$intento - 1]);
                } else {
                    throw new \RuntimeException('Error en API Gemini: ' . $lastError);
                }
            }

            if ($sobrecarga && !$esUltimo) {
                Log::warning('RITGeneratorService: modelo saturado, cascadeando', [
                    'empresa_id'    => $empresaId,
                    'model_fallido' => $model,
                    'model_next'    => $modelosCascada[$idx + 1] ?? 'ninguno',
                ]);
                continue;
            }

            break;
        }

        throw new \RuntimeException('Error en API Gemini (todos los modelos intentados): ' . $lastError);
    }

    private function validarCapitulo(string $texto, string $titulo): bool
    {
        if (strlen(trim($texto)) < 200) {
            Log::warning("RITGeneratorService: capítulo '{$titulo}' demasiado corto");
            return false;
        }
        if (!preg_match('/ARTÍCULO\s+\d+/iu', $texto)) {
            Log::warning("RITGeneratorService: capítulo '{$titulo}' sin artículos detectados");
            return false;
        }
        return true;
    }

    private function obtenerArticulosObligatorios(array $codigos): string
    {
        if (empty($codigos)) return '';

        try {
            $articulos = ArticuloLegal::whereIn('codigo', $codigos)
                ->whereNull('empresa_id')
                ->where('activo', true)
                ->get();

            if ($articulos->isEmpty()) return '';

            return $articulos->map(fn($a) => "--- {$a->codigo}: {$a->titulo} ---\n{$a->texto_completo}")
                ->implode("\n\n");
        } catch (\Throwable $e) {
            Log::warning('RITGeneratorService: no se pudieron obtener artículos obligatorios', [
                'codigos' => $codigos,
                'error'   => $e->getMessage(),
            ]);
            return '';
        }
    }

    private function construirContextoEmpresa(array $cap, array $respuestas, Empresa $empresa): string
    {
        $lista = fn($arr) => is_array($arr) ? implode(', ', array_filter($arr)) : ($arr ?? '');

        $lineas = [
            'DATOS DE LA EMPRESA:',
            '- Razón social: ' . $empresa->nombre_completo,
            '- NIT: ' . $empresa->nit,
            '- Tipo societario: ' . ($empresa->tipo_societario ?? 'No especificado'),
            '- Representante Legal: ' . ($empresa->representante_legal ?? ''),
            '- Fecha de elaboración: ' . now()->locale('es')->translatedFormat('j \d\e F \d\e Y'),
        ];

        foreach ($cap['datos_empresa_keys'] as $key) {
            $val = $respuestas[$key] ?? null;
            if ($val === null || $val === '') continue;

            if ($key === 'cargos') {
                $txt = '';
                foreach ((array) $val as $c) {
                    $nombre   = $c['nombre_cargo'] ?? '';
                    $sanciona = ($c['puede_sancionar'] ?? false) ? 'puede sancionar' : 'no sanciona';
                    if ($nombre) $txt .= "  - {$nombre} ({$sanciona})\n";
                }
                $lineas[] = "- Cargos:\n{$txt}";
            } elseif ($key === 'sucursales') {
                $txt = '';
                foreach ((array) $val as $s) {
                    $ciudad = $s['ciudad'] ?? '';
                    $dir    = $s['direccion'] ?? '';
                    $trab   = $s['num_trabajadores'] ?? '';
                    if ($ciudad) $txt .= "  - {$ciudad}: {$dir}, {$trab} trabajadores\n";
                }
                $lineas[] = "- Sucursales:\n{$txt}";
            } elseif ($key === 'beneficios_extralegales') {
                $txt = '';
                foreach ((array) $val as $b) {
                    $nb = $b['nombre_beneficio'] ?? '';
                    $db = $b['descripcion'] ?? '';
                    if ($nb) $txt .= "  - {$nb}: {$db}\n";
                }
                $lineas[] = "- Beneficios extralegales:\n" . ($txt ?: "  - Ninguno\n");
            } elseif ($key === 'sanciones_configuradas') {
                $leves  = array_filter((array) $val, fn($s) => ($s['tipo_falta'] ?? '') === 'leve');
                $graves = array_filter((array) $val, fn($s) => ($s['tipo_falta'] ?? '') === 'grave');
                $fmtS   = fn(array $s): string => match ($s['tipo_sancion'] ?? '') {
                    'llamado_atencion' => 'llamado de atención',
                    'suspension'       => 'suspensión' . (!empty($s['dias_suspension']) ? ' de ' . $s['dias_suspension'] . ' días' : ''),
                    'terminacion'      => 'terminación del contrato',
                    default            => $s['tipo_sancion'] ?? '',
                };
                $txt = '';
                foreach ($leves  as $s) $txt .= "  - [leve] {$s['nombre']}: {$fmtS($s)}\n";
                foreach ($graves as $s) $txt .= "  - [grave] {$s['nombre']}: {$fmtS($s)}\n";
                if ($txt) $lineas[] = "- Sanciones configuradas:\n{$txt}";
            } elseif (is_array($val)) {
                $lineas[] = "- {$key}: " . $lista($val);
            } else {
                $lineas[] = "- {$key}: {$val}";
            }
        }

        return implode("\n", $lineas);
    }

    private function construirPrompt(
        array   $cap,
        string  $contextoEmpresa,
        string  $articulosObligatorios,
        string  $rag,
        int     $articuloInicio,
        Empresa $empresa,
    ): string {
        $numero  = $cap['numero'];
        $titulo  = $cap['titulo'];
        $instr   = $cap['instrucciones'];

        $seccionArticulos = $articulosObligatorios
            ? "═══════════════════════════════════════════════════\n"
              . "TEXTO OFICIAL DE ARTÍCULOS DEL CST (fuente: base de datos interna)\n"
              . "Reproduce el contenido de estos artículos con fidelidad. Puedes citar su número.\n"
              . "═══════════════════════════════════════════════════\n"
              . $articulosObligatorios . "\n"
            : '';

        $seccionBiblioteca = $rag
            ? "═══════════════════════════════════════════════════\n"
              . "FRAGMENTOS DE LA BIBLIOTECA JURÍDICA INTERNA\n"
              . "Puedes citar artículos y leyes que aparezcan textualmente en estos fragmentos.\n"
              . "═══════════════════════════════════════════════════\n"
              . $rag . "\n"
            : '';

        $razonSocial = $empresa->nombre_completo;

        $advertenciaLegal = (!$articulosObligatorios && !$rag)
            ? "ADVERTENCIA: No hay contexto jurídico disponible para este capítulo. "
              . "Redacta el contenido temático completo SIN citar números de artículo ni nombres de ley.\n"
            : '';

        return <<<PROMPT
Eres un abogado laboral colombiano experto en Reglamentos Internos de Trabajo.

Redacta ÚNICAMENTE el CAPÍTULO {$numero} ({$titulo}) del RIT de "{$razonSocial}".
Los artículos comienzan desde ARTÍCULO {$articuloInicio}.

REGLA FUNDAMENTAL — CITAS LEGALES:
- Números de artículo, nombres de ley, porcentajes y plazos legales: SOLO los que aparezcan
  textualmente en el contexto jurídico inyectado más abajo (artículos CST o biblioteca).
- PROHIBIDO inventar o recordar artículos, leyes, porcentajes o plazos de tu entrenamiento.
- Si el contexto no trae una cifra o referencia, redacta el concepto sin citar la fuente legal.

INSTRUCCIONES TEMÁTICAS DE ESTE CAPÍTULO:
{$instr}

REGLAS DE FORMATO — OBLIGATORIAS:
1. Inicia con CAPÍTULO {$numero} (primera línea) y {$titulo} (segunda línea), ambas en MAYÚSCULAS.
2. Cada artículo en su propia línea: ARTÍCULO N. NOMBRE. Texto completo (mínimo 60 palabras).
3. Párrafos adicionales de un artículo: líneas a continuación sin prefijo ARTÍCULO.
4. PARÁGRAFO en línea propia: PARÁGRAFO PRIMERO. Texto.
5. Listas dentro de artículo: 1) texto  2) texto  (en líneas separadas).
6. TABLAS cuando corresponda (horarios, escalas de sanciones, etapas disciplinarias):
   TABLA:
   ENCABEZADO: Col1 | Col2
   FILA: dato1 | dato2
   FIN_TABLA
7. Sin Markdown: sin *, sin #, sin **.
8. NUNCA uses corchetes ni placeholders. Usa los datos reales de la empresa.
9. Devuelve SOLO el texto del capítulo.

{$advertenciaLegal}
{$seccionArticulos}
{$seccionBiblioteca}
{$contextoEmpresa}

Comienza ahora con "CAPÍTULO {$numero}":
PROMPT;
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
     * Genera un PDF del RIT en un archivo temporal.
     * Intenta primero con LibreOffice (DOCX → PDF, calidad Word real).
     * Fallback: DomPDF si LibreOffice no está disponible.
     */
    public function generarPDFTemp(string $textoRIT, Empresa $empresa): string
    {
        $loPath = $this->detectarLibreOffice();

        if ($loPath) {
            try {
                return $this->generarPDFviaLibreOffice($textoRIT, $empresa, $loPath);
            } catch (\Exception $e) {
                Log::warning('RITGeneratorService: LibreOffice falló, fallback a DomPDF', [
                    'empresa_id' => $empresa->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        return $this->generarPDFviaDomPDF($textoRIT, $empresa);
    }

    /** Detecta la ruta de LibreOffice según el SO. */
    private function detectarLibreOffice(): ?string
    {
        if (PHP_OS_FAMILY === 'Linux') {
            foreach (['/usr/bin/soffice', '/usr/local/bin/soffice', '/snap/bin/soffice'] as $p) {
                if (file_exists($p)) return $p;
            }
            return null;
        }
        foreach ([
            'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
            'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
        ] as $p) {
            if (file_exists($p)) return $p;
        }
        return null;
    }

    /** Genera PDF usando LibreOffice: escribe DOCX y lo convierte. */
    private function generarPDFviaLibreOffice(string $textoRIT, Empresa $empresa, string $loPath): string
    {
        $uid    = uniqid('rit_', true);
        $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $uid;
        mkdir($tmpDir, 0755, true);

        $docxPath = $tmpDir . DIRECTORY_SEPARATOR . 'reglamento.docx';
        $pdfPath  = $tmpDir . DIRECTORY_SEPARATOR . 'reglamento.pdf';

        $this->escribirDocx($textoRIT, $empresa, $docxPath);

        // Perfil de usuario único para evitar conflictos de instancia concurrente
        // file:/// (3 slashes) es necesario para rutas absolutas en Windows y Linux
        $profileDir = str_replace('\\', '/', $tmpDir . '/lo_profile');
        $loProfile  = 'file:///' . ltrim($profileDir, '/');

        $cmd = [
            $loPath,
            '--headless',
            '--nofirststartwizard',
            '-env:UserInstallation=' . $loProfile,
            '--convert-to', 'pdf',
            '--outdir', $tmpDir,
            $docxPath,
        ];

        // Usar proc_open con timeout de 60s para evitar bloqueos indefinidos en Windows
        $process = proc_open(
            $cmd,
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );

        if (!is_resource($process)) {
            $this->limpiarDir($tmpDir);
            throw new \RuntimeException('No se pudo iniciar el proceso LibreOffice');
        }

        fclose($pipes[0]);

        $timeout  = 60;  // segundos
        $deadline = microtime(true) + $timeout;
        $output   = '';

        // Lectura no bloqueante con timeout
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $code = null;
        while (microtime(true) < $deadline) {
            $status = proc_get_status($process);
            if (!$status['running']) {
                $code = $status['exitcode'];
                break;
            }
            $output .= stream_get_contents($pipes[1]);
            $output .= stream_get_contents($pipes[2]);
            usleep(200_000); // 200ms
        }

        fclose($pipes[1]);
        fclose($pipes[2]);

        if ($code === null) {
            // Timeout: matar el proceso
            proc_terminate($process);
            proc_close($process);
            $this->limpiarDir($tmpDir);
            throw new \RuntimeException('LibreOffice superó el tiempo límite de ' . $timeout . 's');
        }

        proc_close($process);

        if ($code !== 0 || !file_exists($pdfPath)) {
            $this->limpiarDir($tmpDir);
            throw new \RuntimeException(
                'LibreOffice no convirtió el DOCX (código ' . $code . '): ' . trim($output)
            );
        }

        $finalPath = tempnam(sys_get_temp_dir(), 'rit_') . '.pdf';
        copy($pdfPath, $finalPath);
        $this->limpiarDir($tmpDir);

        Log::info('RITGeneratorService: PDF generado con LibreOffice', [
            'empresa_id' => $empresa->id,
        ]);

        return $finalPath;
    }

    /** Fallback: genera PDF con DomPDF desde HTML. */
    private function generarPDFviaDomPDF(string $textoRIT, Empresa $empresa): string
    {
        $html = $this->textoAHtml($textoRIT, $empresa);

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'Arial');
        $options->set('isFontSubsettingEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->render();

        $canvas = $dompdf->getCanvas();
        if ($canvas instanceof CpdfAdapter) {
            $ownerPass = substr(hash('sha256', config('app.key') . $empresa->id . 'rit'), 0, 32);
            $canvas->get_cpdf()->setEncryption('', $ownerPass, ['print']);
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'rit_') . '.pdf';
        file_put_contents($tmpPath, $dompdf->output());

        return $tmpPath;
    }

    /** Elimina todos los archivos y el directorio temporal. */
    private function limpiarDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*') as $f) {
            is_dir($f) ? $this->limpiarDir($f) : @unlink($f);
        }
        @rmdir($dir);
    }

    /**
     * Convierte el texto plano del RIT a HTML profesional para DOMPDF.
     * Genera portada, encabezados de capítulo, artículos, parágrafos y listas con diseño formal.
     */
    private function textoAHtml(string $textoRIT, Empresa $empresa): string
    {
        $eNombre        = htmlspecialchars($empresa->nombre_completo ?? $empresa->razon_social ?? '', ENT_QUOTES, 'UTF-8');
        $eNit           = htmlspecialchars($empresa->nit ?? '', ENT_QUOTES, 'UTF-8');
        $eRepresentante = htmlspecialchars($empresa->representante_legal ?? '', ENT_QUOTES, 'UTF-8');
        $eCiudad        = htmlspecialchars($empresa->ciudad ?? '', ENT_QUOTES, 'UTF-8');
        $eDpto          = htmlspecialchars($empresa->departamento ?? '', ENT_QUOTES, 'UTF-8');
        $eDireccion     = htmlspecialchars($empresa->direccion ?? '', ENT_QUOTES, 'UTF-8');
        $eTelefono      = htmlspecialchars($empresa->telefono ?? '', ENT_QUOTES, 'UTF-8');
        $eEmail         = htmlspecialchars($empresa->email_contacto ?? '', ENT_QUOTES, 'UTF-8');
        $eLugar         = trim($eCiudad . ($eDpto ? ', ' . $eDpto : ''));

        $fLine1 = implode('. ', array_filter([$eDireccion, $eLugar]));
        $fLine2 = implode('   ', array_filter([
            $eTelefono ? 'Tel. ' . $eTelefono : '',
            $eEmail    ? 'Email. ' . $eEmail   : '',
        ]));

        // Eliminar la primera línea si Gemini repite el título (evita duplicado con el header HTML)
        $textoRIT = ltrim($textoRIT);
        if (preg_match('/^REGLAMENTO\s+INTERNO\s+DE\s+TRABAJO/iu', $textoRIT)) {
            $textoRIT = ltrim(substr($textoRIT, strpos($textoRIT, "\n")), "\r\n");
        }

        $cuerpo     = '';
        $enLista    = false;
        $enTabla    = false;
        $tablaHdr   = null;
        $tablaRows  = [];
        $lastCapNum = false;

        foreach (explode("\n", $textoRIT) as $linea) {
            $linea = rtrim($linea);

            // ── INICIO DE TABLA ───────────────────────────────────────────────
            if (preg_match('/^TABLA:/iu', $linea)) {
                if ($enLista) { $cuerpo .= '</div>'; $enLista = false; }
                $enTabla   = true;
                $tablaHdr  = null;
                $tablaRows = [];
                $lastCapNum = false;
                continue;
            }

            // ── DENTRO DE TABLA ───────────────────────────────────────────────
            if ($enTabla) {
                if (preg_match('/^ENCABEZADO:\s*(.+)$/iu', $linea, $m)) {
                    $tablaHdr = array_map('trim', explode('|', $m[1]));
                } elseif (preg_match('/^FILA:\s*(.+)$/iu', $linea, $m)) {
                    $tablaRows[] = array_map('trim', explode('|', $m[1]));
                } elseif (preg_match('/^FIN_TABLA/iu', $linea)) {
                    $enTabla = false;
                    $cuerpo .= '<table class="rit-tbl">';
                    if ($tablaHdr) {
                        $cuerpo .= '<tr>';
                        foreach ($tablaHdr as $th) {
                            $cuerpo .= '<th>' . htmlspecialchars($th, ENT_QUOTES, 'UTF-8') . '</th>';
                        }
                        $cuerpo .= '</tr>';
                    }
                    foreach ($tablaRows as $fila) {
                        $cuerpo .= '<tr>';
                        foreach ($fila as $td) {
                            $cuerpo .= '<td>' . htmlspecialchars($td, ENT_QUOTES, 'UTF-8') . '</td>';
                        }
                        $cuerpo .= '</tr>';
                    }
                    $cuerpo .= '</table>';
                }
                // ignorar cualquier otra línea dentro de la tabla
                continue;
            }

            if (trim($linea) === '') {
                if ($enLista) { $cuerpo .= '</div>'; $enLista = false; }
                $lastCapNum = false;
                continue;
            }

            $linea = preg_replace('/\*{1,2}([^*]+)\*{1,2}/', '$1', $linea);
            $linea = ltrim($linea, '-*# ');
            $linea = rtrim($linea);

            // ── CAPÍTULO con separador: CAPÍTULO I — TÍTULO ───────────────────
            if (preg_match('/^(CAPÍTULO\s+[IVXLCDM]+)\s*[—–\-]+\s*(.+)$/iu', $linea, $m)) {
                if ($enLista) { $cuerpo .= '</div>'; $enLista = false; }
                $cuerpo .= '<p class="cap-num">' . htmlspecialchars(strtoupper($m[1]), ENT_QUOTES, 'UTF-8') . '</p>'
                         . '<p class="cap-tit">' . htmlspecialchars(strtoupper(trim($m[2])), ENT_QUOTES, 'UTF-8') . '</p>';
                $lastCapNum = false;
                continue;
            }

            // ── CAPÍTULO solo (la siguiente línea será el título) ─────────────
            if (preg_match('/^CAPÍTULO\s+[IVXLCDM]+\.?\s*$/iu', $linea)) {
                if ($enLista) { $cuerpo .= '</div>'; $enLista = false; }
                $cuerpo    .= '<p class="cap-num">' . htmlspecialchars(strtoupper(trim($linea)), ENT_QUOTES, 'UTF-8') . '</p>';
                $lastCapNum = true;
                continue;
            }

            // ── Título del capítulo (línea inmediatamente después de CAPÍTULO) ─
            if ($lastCapNum) {
                if ($enLista) { $cuerpo .= '</div>'; $enLista = false; }
                $cuerpo    .= '<p class="cap-tit">' . htmlspecialchars(strtoupper(trim($linea)), ENT_QUOTES, 'UTF-8') . '</p>';
                $lastCapNum = false;
                continue;
            }
            $lastCapNum = false;

            // ── ARTÍCULO: título en negrita, cuerpo en normal ─────────────────
            if (preg_match('/^(ARTÍCULO\s+\d+\.[^.]+\.)\s*(.*)$/iu', $linea, $mArt)) {
                if ($enLista) { $cuerpo .= '</div>'; $enLista = false; }
                $tArt = htmlspecialchars(trim($mArt[1]), ENT_QUOTES, 'UTF-8');
                $bArt = htmlspecialchars(trim($mArt[2] ?? ''), ENT_QUOTES, 'UTF-8');
                $cuerpo .= '<p class="art"><strong>' . $tArt . '</strong>'
                         . ($bArt !== '' ? ' ' . $bArt : '') . '</p>';
                continue;
            }

            // ── PARÁGRAFO: etiqueta en negrita, cuerpo en normal ──────────────
            if (preg_match('/^(PARÁGRAFO(?:\s+(?:ÚNICO|PRIMERO|SEGUNDO|TERCERO|CUARTO|\d+))?\s*[:.])(.*)$/iu', $linea, $mPar)) {
                if ($enLista) { $cuerpo .= '</div>'; $enLista = false; }
                $tPar = htmlspecialchars(trim($mPar[1]), ENT_QUOTES, 'UTF-8');
                $bPar = htmlspecialchars(trim($mPar[2] ?? ''), ENT_QUOTES, 'UTF-8');
                $cuerpo .= '<p class="paragrafo"><strong>' . $tPar . '</strong>'
                         . ($bPar !== '' ? ' ' . $bPar : '') . '</p>';
                continue;
            }

            // ── Sub-ítems numerados: 1) o a) ─────────────────────────────────
            if (preg_match('/^\s*(\d+|[a-zA-Z])\)\s+(.+)$/', $linea, $m)) {
                if (!$enLista) { $cuerpo .= '<div class="lista">'; $enLista = true; }
                $cuerpo .= '<div class="lista-item">'
                         . '<span class="lista-marc">' . htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8') . ')</span>'
                         . '<span class="lista-txt">'  . htmlspecialchars(trim($m[2]), ENT_QUOTES, 'UTF-8') . '</span>'
                         . '</div>';
                continue;
            }

            // ── Viñetas: • ────────────────────────────────────────────────────
            if (preg_match('/^\s*[•·▪▸]\s+(.+)$/', $linea, $m)) {
                if (!$enLista) { $cuerpo .= '<div class="lista">'; $enLista = true; }
                $cuerpo .= '<div class="lista-item">'
                         . '<span class="lista-marc">•</span>'
                         . '<span class="lista-txt">' . htmlspecialchars(trim($m[1]), ENT_QUOTES, 'UTF-8') . '</span>'
                         . '</div>';
                continue;
            }

            // ── Cuerpo genérico ───────────────────────────────────────────────
            if ($enLista) { $cuerpo .= '</div>'; $enLista = false; }
            $cuerpo .= '<p class="body">' . htmlspecialchars($linea, ENT_QUOTES, 'UTF-8') . '</p>';
        }

        if ($enLista) { $cuerpo .= '</div>'; }

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
@page { margin: 2.5cm 3cm; }
* { box-sizing: border-box; }
body {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 9pt;
    line-height: 1.08;
    color: #000000;
}
.titulo {
    text-align: center;
    font-weight: bold;
    font-size: 9pt;
    margin-top: 0;
    margin-bottom: 8pt;
}
.cap-num {
    text-align: center;
    font-weight: bold;
    font-size: 9pt;
    margin-top: 16pt;
    margin-bottom: 0;
    page-break-after: avoid;
}
.cap-tit {
    text-align: center;
    font-weight: bold;
    font-size: 9pt;
    margin-top: 0;
    margin-bottom: 8pt;
    page-break-before: avoid;
}
.art {
    text-align: justify;
    font-weight: normal;
    font-size: 9pt;
    margin-top: 0;
    margin-bottom: 8pt;
}
.paragrafo {
    text-align: justify;
    font-weight: normal;
    font-size: 9pt;
    margin-top: 0;
    margin-bottom: 8pt;
    margin-left: 14pt;
}
.body {
    text-align: justify;
    font-weight: normal;
    font-size: 9pt;
    margin-top: 0;
    margin-bottom: 8pt;
}
.lista { margin-left: 14pt; margin-bottom: 0; }
.lista-item { display: table; width: 100%; font-size: 9pt; margin-bottom: 4pt; }
.lista-marc { display: table-cell; width: 14pt; vertical-align: top; }
.lista-txt  { display: table-cell; text-align: justify; vertical-align: top; }
.rit-tbl {
    width: 100%;
    border-collapse: collapse;
    margin-top: 6pt;
    margin-bottom: 8pt;
    font-size: 9pt;
}
.rit-tbl th {
    border: 0.5pt solid #000000;
    padding: 3pt 5pt;
    font-weight: bold;
    text-align: center;
    vertical-align: middle;
}
.rit-tbl td {
    border: 0.5pt solid #000000;
    padding: 3pt 5pt;
    text-align: left;
    vertical-align: top;
}
</style>
</head>
<body>

<p class="titulo">REGLAMENTO INTERNO DE TRABAJO DE {$eNombre}</p>

{$cuerpo}

</body>
</html>
HTML;
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
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(9);

        // Estilos comunes — igual que SERVISOM: interlineado sencillo (240 twips), 8pt after
        $fNorm = ['name' => 'Arial', 'size' => 9];
        $fBold = ['name' => 'Arial', 'size' => 9, 'bold' => true];
        $fSmal = ['name' => 'Arial', 'size' => 8];

        $pBody = ['alignment' => Jc::BOTH,   'spaceAfter' => 160, 'spaceBefore' => 0,   'lineRule' => 'auto', 'line' => 240];
        $pCap  = ['alignment' => Jc::CENTER,  'spaceAfter' => 0,   'spaceBefore' => 280, 'lineRule' => 'auto', 'line' => 240];
        $pCapT = ['alignment' => Jc::CENTER,  'spaceAfter' => 160, 'spaceBefore' => 0,   'lineRule' => 'auto', 'line' => 240];
        $pArt  = ['alignment' => Jc::BOTH,   'spaceAfter' => 0,   'spaceBefore' => 160, 'lineRule' => 'auto', 'line' => 240];
        $pPar  = ['alignment' => Jc::BOTH,   'spaceAfter' => 0,   'spaceBefore' => 0,   'lineRule' => 'auto', 'line' => 240, 'indentation' => ['left' => Converter::cmToTwip(0.7)]];
        $pR    = ['alignment' => Jc::RIGHT,  'spaceAfter' => 0,   'spaceBefore' => 0,   'lineRule' => 'auto', 'line' => 240];

        $section = $phpWord->addSection([
            'paperSize'    => 'A4',
            'marginTop'    => Converter::cmToTwip(2.5),
            'marginBottom' => Converter::cmToTwip(2.5),
            'marginLeft'   => Converter::cmToTwip(3.0),
            'marginRight'  => Converter::cmToTwip(3.0),
        ]);

        // Footer con datos de la empresa (alineado a la derecha, igual que el PDF)
        $footer  = $section->addFooter();
        $eLugar  = trim(($empresa->ciudad ?? '') . (($empresa->departamento ?? '') ? ', ' . $empresa->departamento : ''));
        $fLine1  = implode('. ', array_filter([$empresa->direccion ?? '', $eLugar]));
        $fLine2  = implode('   ', array_filter([
            ($empresa->telefono       ?? '') ? 'Tel. '   . $empresa->telefono       : '',
            ($empresa->email_contacto ?? '') ? 'Email. ' . $empresa->email_contacto : '',
        ]));
        if ($fLine1) $footer->addText(htmlspecialchars($fLine1), $fSmal, $pR);
        if ($fLine2) $footer->addText(htmlspecialchars($fLine2), $fSmal, $pR);

        // Título principal
        $eNombre = strtoupper($empresa->nombre_completo ?? $empresa->razon_social ?? '');
        $section->addText(
            "REGLAMENTO INTERNO DE TRABAJO DE {$eNombre}",
            $fBold,
            ['alignment' => Jc::CENTER, 'spaceAfter' => 160, 'spaceBefore' => 0, 'lineRule' => 'auto', 'line' => 240]
        );

        // Eliminar la primera línea si Gemini repite el título (evita duplicado con el addText del título)
        $textoRIT = ltrim($textoRIT);
        if (preg_match('/^REGLAMENTO\s+INTERNO\s+DE\s+TRABAJO/iu', $textoRIT)) {
            $textoRIT = ltrim(substr($textoRIT, strpos($textoRIT, "\n")), "\r\n");
        }

        // Parser — idéntica lógica a textoAHtml()
        $lastCapNum = false;

        foreach (explode("\n", $textoRIT) as $linea) {
            $linea = rtrim($linea);

            if (trim($linea) === '') {
                $lastCapNum = false;
                continue; // no añadir saltos de línea extras; el spaceAfter ya separa
            }

            // Limpiar markdown
            $linea = preg_replace('/\*{1,2}([^*]+)\*{1,2}/', '$1', $linea);
            $linea = ltrim($linea, '-*# ');
            $linea = rtrim($linea);
            if ($linea === '') continue;

            // ── TABLA ──────────────────────────────────────────────────────────
            // (las tablas en DOCX se omiten por complejidad; se dejan como texto)
            if (preg_match('/^(TABLA:|ENCABEZADO:|FILA:|FIN_TABLA)/iu', $linea)) {
                continue;
            }

            // ── CAPÍTULO X — TÍTULO (una sola línea) ──────────────────────────
            if (preg_match('/^(CAPÍTULO\s+[IVXLCDM]+)\s*[—–\-]+\s*(.+)$/iu', $linea, $m)) {
                $section->addText(strtoupper($m[1]), $fBold, $pCap);
                $section->addText(strtoupper(trim($m[2])), $fBold, $pCapT);
                $lastCapNum = false;
                continue;
            }

            // ── CAPÍTULO X solo (siguiente línea = título) ────────────────────
            if (preg_match('/^CAPÍTULO\s+[IVXLCDM]+\.?\s*$/iu', $linea)) {
                $section->addText(strtoupper(trim($linea)), $fBold, $pCap);
                $lastCapNum = true;
                continue;
            }

            // ── Título del capítulo ───────────────────────────────────────────
            if ($lastCapNum) {
                $section->addText(strtoupper(trim($linea)), $fBold, $pCapT);
                $lastCapNum = false;
                continue;
            }
            $lastCapNum = false;

            // ── ARTÍCULO: título en negrita, cuerpo en normal ─────────────────
            if (preg_match('/^(ARTÍCULO\s+\d+\.[^.]+\.)\s*(.*)$/iu', $linea, $mArt)) {
                $tArt = trim($mArt[1]);
                $bArt = trim($mArt[2] ?? '');
                $run  = $section->addTextRun($pArt);
                $run->addText($tArt . ($bArt !== '' ? ' ' : ''), $fBold);
                if ($bArt !== '') {
                    $run->addText($bArt, $fNorm);
                }
                continue;
            }

            // ── PARÁGRAFO: etiqueta en negrita, cuerpo en normal ──────────────
            if (preg_match('/^(PARÁGRAFO(?:\s+(?:ÚNICO|PRIMERO|SEGUNDO|TERCERO|CUARTO|\d+))?\s*[:.])(.*)$/iu', $linea, $mPar)) {
                $tPar = trim($mPar[1]);
                $bPar = trim($mPar[2] ?? '');
                $run  = $section->addTextRun($pPar);
                $run->addText($tPar . ($bPar !== '' ? ' ' : ''), $fBold);
                if ($bPar !== '') {
                    $run->addText($bPar, $fNorm);
                }
                continue;
            }

            // ── Cuerpo genérico ───────────────────────────────────────────────
            $section->addText($linea, $fNorm, $pBody);
        }

        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($rutaAbsoluta);
    }

    public static function getCapitulos(): array
    {
        return [
            [
                'numero' => 'I', 'titulo' => 'DENOMINACIÓN, DOMICILIO Y OBJETO',
                'query_rag' => 'reglamento interno trabajo denominación domicilio objeto ámbito aplicación',
                'codigos_obligatorios' => ['Art. 104 CST', 'Art. 105 CST', 'Art. 106 CST'],
                'datos_empresa_keys'   => ['domicilio', 'tiene_sucursales', 'sucursales', 'actividad_economica', 'actividades_secundarias', 'num_trabajadores'],
                'instrucciones' => implode("\n", [
                    'Redacta estos artículos, cada uno como párrafo completo:',
                    '1. Ámbito de aplicación del reglamento: a quiénes aplica, desde cuándo rige.',
                    '2. Denominación completa, NIT y tipo societario de la empresa.',
                    '3. Domicilio principal; si tiene sucursales, listarlas con ciudad y dirección.',
                    '4. Actividad económica principal y secundarias.',
                    '5. Representante legal y su facultad de dirección y sanción disciplinaria.',
                    'IMPORTANTE: los artículos del CST que aplican están en el contexto jurídico inyectado.',
                ]),
            ],
            [
                'numero' => 'II', 'titulo' => 'ADMISIÓN Y PERÍODO DE PRUEBA',
                'query_rag' => 'admisión trabajadores período de prueba requisitos ingreso contrato trabajo',
                'codigos_obligatorios' => ['Art. 76 CST', 'Art. 77 CST', 'Art. 78 CST', 'Art. 80 CST'],
                'datos_empresa_keys'   => ['tipos_contrato', 'tiene_trabajadores_mision', 'cargos'],
                'instrucciones' => implode("\n", [
                    '1. REQUISITOS DE INGRESO: lista de documentos que la empresa exige (hoja de vida, documento de identidad, certificados de estudio y experiencia). Los antecedentes judiciales solo son exigibles cuando el cargo lo requiera por razones de seguridad, no como criterio automático de exclusión. Lista en formato 1) 2) 3).',
                    '2. PERÍODO DE PRUEBA: reglas de duración, forma de pactarlo (siempre por escrito), terminación durante el período. Los plazos exactos y condiciones provienen del contexto jurídico inyectado.',
                    '3. PROHIBICIONES DE INGRESO: documentos o pruebas que NO pueden exigirse al trabajador como condición de ingreso (libreta militar, pruebas de embarazo, pruebas de salud discriminatorias, etc.). Los artículos y normas aplicables están en el contexto jurídico inyectado.',
                    '4. TIPOS DE CONTRATO que usa la empresa (según datos empresa). Si usa trabajadores en misión (empresas temporales), mencionar el marco aplicable.',
                    'IMPORTANTE: los plazos, porcentajes y referencias legales exactas provienen únicamente del contexto jurídico inyectado.',
                ]),
            ],
            [
                'numero' => 'III', 'titulo' => 'JORNADA ORDINARIA DE TRABAJO',
                'query_rag' => 'jornada laboral ordinaria horas trabajo diurno nocturno descanso dominical compensatorio',
                'codigos_obligatorios' => ['Art. 158 CST', 'Art. 159 CST', 'Art. 160 CST', 'Art. 161 CST', 'Art. 162 CST', 'Art. 181 CST', 'Art. 182 CST'],
                'datos_empresa_keys'   => ['horario_entrada', 'horario_salida', 'opera_en_turnos', 'numero_turnos', 'definicion_turnos', 'rotacion_turnos', 'trabaja_sabados', 'trabaja_dominicales', 'cargos_exentos_jornada', 'modalidades_jornada', 'cargos_nocturnos', 'control_asistencia'],
                'instrucciones' => implode("\n", [
                    'A. JORNADA MÁXIMA LEGAL: definición, horas máximas semanales y tendencia de reducción según normativa vigente (ver contexto jurídico). Definir trabajo diurno y nocturno con los horarios que establezca la ley.',
                    'B. HORARIO DE LA EMPRESA — usar TABLA con los datos reales de la empresa:',
                    '   TABLA:',
                    '   ENCABEZADO: Días | Horario entrada | Descanso | Horario salida',
                    '   FILA: [días según datos] | [hora entrada] | [pausa almuerzo] | [hora salida]',
                    '   FIN_TABLA  (si trabaja sábados, agregar fila; si opera en turnos, una tabla por turno)',
                    'C. DESCANSO DOMINICAL: artículo independiente sobre el derecho al descanso dominical remunerado. El contenido exacto proviene del contexto jurídico inyectado.',
                    'D. DESCANSO COMPENSATORIO: artículo independiente sobre qué ocurre cuando se trabaja el día de descanso obligatorio. Los derechos económicos exactos provienen del contexto jurídico inyectado.',
                    'E. Si opera en turnos: artículo por turno con horario exacto y cargos asignados, usando TABLA.',
                    'F. Si hay cargos de dirección, manejo o confianza excluidos de jornada máxima: artículo expreso indicando qué cargos y sus condiciones. Ver contexto jurídico para la norma aplicable.',
                    'G. CONTROL DE ASISTENCIA: sistema que usa la empresa (según datos empresa).',
                ]),
            ],
            [
                'numero' => 'IV', 'titulo' => 'TRABAJO SUPLEMENTARIO, DOMINICALES Y FESTIVOS',
                'query_rag' => 'horas extras trabajo suplementario dominicales festivos recargo nocturno límite autorización',
                'codigos_obligatorios' => ['Art. 167 CST', 'Art. 168 CST', 'Art. 169 CST', 'Art. 179 CST', 'Art. 180 CST'],
                'datos_empresa_keys'   => ['politica_horas_extras', 'trabaja_dominicales', 'cargos_nocturnos'],
                'instrucciones' => implode("\n", [
                    'A. LÍMITE DE HORAS EXTRAS: artículo sobre el máximo de horas extras permitidas diaria y semanalmente, la obligación de autorización previa y escrita del empleador, y que las no autorizadas no generan pago. Los límites exactos provienen del contexto jurídico inyectado.',
                    'B. RECARGOS POR TRABAJO SUPLEMENTARIO: artículo que enumere los diferentes recargos aplicables (hora extra diurna, hora extra nocturna, trabajo dominical o festivo, recargo nocturno ordinario). Los porcentajes exactos de cada recargo provienen del contexto jurídico inyectado.',
                    'C. Si opera con turnos nocturnos regulares: artículo sobre el recargo nocturno aplicable.',
                    'D. REGISTRO: registro individual de trabajo suplementario firmado por ambas partes.',
                    'E. Política interna de autorización de horas extras de la empresa (según datos empresa).',
                    'IMPORTANTE: los porcentajes de recargo y los límites en horas provienen únicamente del contexto jurídico inyectado.',
                ]),
            ],
            [
                'numero' => 'V', 'titulo' => 'REMUNERACIÓN Y FORMA DE PAGO',
                'query_rag' => 'salario remuneración forma pago periodicidad salario en especie propinas prohibición fichas',
                'codigos_obligatorios' => ['Art. 127 CST', 'Art. 128 CST', 'Art. 129 CST', 'Art. 131 CST', 'Art. 132 CST', 'Art. 134 CST', 'Art. 136 CST'],
                'datos_empresa_keys'   => ['forma_pago', 'periodicidad_pago', 'periodicidad_detalle', 'maneja_comisiones', 'tipo_comisiones', 'beneficios_extralegales'],
                'instrucciones' => implode("\n", [
                    'A. MODALIDADES DE SALARIO: por unidad de tiempo, por obra o tarea, variable. Salario integral si aplica a algún cargo (ver condiciones en contexto jurídico).',
                    'B. PERÍODO Y FORMA DE PAGO: periodicidad según datos de la empresa. Forma de pago (transferencia, efectivo, etc.). Comprobante de pago discriminado con devengados y descuentos.',
                    'C. PROHIBICIÓN DE PAGO EN ESPECIE NO AUTORIZADA: artículo sobre la prohibición de pagar con fichas, vales, mercancías u otros sustitutos. Texto y referencias exactas provienen del contexto jurídico inyectado.',
                    'D. SALARIO EN ESPECIE: reglas sobre cuándo es válido, porcentajes máximos permitidos y obligación de pactarlo por escrito. Los límites exactos provienen del contexto jurídico inyectado.',
                    'E. PROPINAS: artículo sobre la naturaleza jurídica de las propinas. El texto exacto aplicable proviene del contexto jurídico inyectado.',
                    'F. Si maneja comisiones: artículo sobre su naturaleza y liquidación. Beneficios extralegales de la empresa (según datos empresa), indicando si son o no factor salarial.',
                    'IMPORTANTE: los porcentajes, condiciones y referencias legales exactas provienen únicamente del contexto jurídico inyectado.',
                ]),
            ],
            [
                'numero' => 'VI', 'titulo' => 'VACACIONES Y PERMISOS',
                'query_rag' => 'vacaciones remuneradas días hábiles registro acumulación compensación dinero permisos remunerados',
                'codigos_obligatorios' => ['Art. 186 CST', 'Art. 187 CST', 'Art. 188 CST', 'Art. 189 CST', 'Art. 190 CST'],
                'datos_empresa_keys'   => ['politica_permisos'],
                'instrucciones' => implode("\n", [
                    'A. VACACIONES ANUALES: artículo sobre el derecho a vacaciones remuneradas por año de servicio. El número exacto de días y las condiciones provienen del contexto jurídico inyectado.',
                    'B. DISFRUTE Y REGISTRO: acuerdo entre partes para fijar la época de vacaciones; registro especial de vacaciones con datos del trabajador, fecha de salida y retorno, y saldo acumulado.',
                    'C. ACUMULACIÓN: posibilidad de acumular vacaciones por acuerdo escrito entre las partes; condiciones y límites según contexto jurídico inyectado.',
                    'D. INTERRUPCIÓN: qué ocurre cuando durante el disfrute de vacaciones sobreviene incapacidad médica o calamidad doméstica.',
                    'E. COMPENSACIÓN EN DINERO: posibilidad de compensar parte de las vacaciones en dinero, condiciones y límites según contexto jurídico inyectado.',
                    'F. PERMISOS REMUNERADOS: calamidad doméstica, sufragio, diligencias personales con aviso previo. Política interna de la empresa (según datos empresa).',
                    'IMPORTANTE: el número exacto de días y los límites de acumulación provienen únicamente del contexto jurídico inyectado.',
                ]),
            ],
            [
                'numero' => 'VII', 'titulo' => 'LICENCIAS ESPECIALES',
                'query_rag' => 'licencia maternidad paternidad luto calamidad doméstica enfermedad no remunerada',
                'codigos_obligatorios' => ['Art. 236 CST', 'Art. 237 CST', 'Art. 238 CST', 'Art. 239 CST'],
                'datos_empresa_keys'   => ['tiene_licencias_especiales', 'descripcion_licencias'],
                'instrucciones' => implode("\n", [
                    'A. LICENCIA DE MATERNIDAD: derecho de la trabajadora, duración, quién la paga, prohibición de laborar durante la licencia. La duración exacta en semanas proviene del contexto jurídico inyectado.',
                    'B. LICENCIA DE PATERNIDAD: derecho del padre trabajador, duración, condiciones. La duración exacta proviene del contexto jurídico inyectado.',
                    'C. LICENCIA DE LUTO: duración en días hábiles, parentesco que la activa. Los días exactos y el grado de parentesco cubierto provienen del contexto jurídico inyectado.',
                    'D. LICENCIA POR CALAMIDAD DOMÉSTICA GRAVE: eventos que la activan, duración razonable definida por la empresa.',
                    'E. LICENCIAS NO REMUNERADAS: posibilidad de acuerdo escrito para estudios, trámites u otras causas justificadas.',
                    'F. Licencias especiales propias de la empresa (según datos empresa), si las hay.',
                    'IMPORTANTE: las duraciones exactas (semanas, días) provienen únicamente del contexto jurídico inyectado.',
                ]),
            ],
            [
                'numero' => 'VIII', 'titulo' => 'RÉGIMEN DISCIPLINARIO: CLASIFICACIÓN DE FALTAS',
                'query_rag' => 'régimen disciplinario faltas leves graves procedimiento descargos garantía debido proceso',
                'codigos_obligatorios' => ['Art. 108 CST', 'Art. 111 CST', 'Art. 113 CST', 'Art. 115 CST'],
                'datos_empresa_keys'   => ['sanciones_configuradas', 'faltas_leves', 'faltas_graves', 'cargos'],
                'instrucciones' => implode("\n", [
                    'A. DEFINICIÓN DE FALTA DISCIPLINARIA: incumplimiento de obligaciones o transgresión de prohibiciones del reglamento.',
                    'B. CATÁLOGO DE FALTAS LEVES (mínimo 8, lista 1) 2) 3)): impuntualidad reiterada, ausentarse sin permiso, descuido en el puesto, uso personal de equipos de la empresa, incumplimiento de entregas, presentación personal inadecuada, descuido en el uso de elementos de seguridad, etc.',
                    'C. CATÁLOGO DE FALTAS GRAVES (mínimo 8): reincidencia en faltas leves, abandono injustificado del puesto, daño doloso a bienes, engaño o fraude, falta grave de respeto, divulgación de información confidencial, inasistencia injustificada, incumplimiento reiterado de instrucciones.',
                    'D. CATÁLOGO DE FALTAS MUY GRAVES (mínimo 5): hurto o apropiación indebida, agresión física, acoso laboral o sexual, presentarse bajo efectos de alcohol o sustancias psicoactivas, sabotaje.',
                    'E. PROCEDIMIENTO DISCIPLINARIO: garantía del debido proceso. Usar TABLA con las etapas:',
                    '   TABLA:',
                    '   ENCABEZADO: Etapa | Descripción',
                    '   FILA: Citación a descargos | El empleador comunica por escrito los cargos y traslada las pruebas al trabajador.',
                    '   FILA: Preparación de descargos | El trabajador cuenta con el plazo legal para preparar y presentar sus descargos por escrito.',
                    '   FILA: Audiencia de descargos | El trabajador expone sus argumentos y puede estar acompañado de representante sindical o persona de su confianza.',
                    '   FILA: Decisión motivada | El empleador emite fallo escrito debidamente fundamentado en hechos y derecho.',
                    '   FILA: Notificación e impugnación | Se notifica al trabajador, quien puede apelar ante el superior jerárquico en el plazo legal.',
                    '   FILA: Segunda instancia | El superior jerárquico resuelve la apelación en el plazo legal.',
                    '   FIN_TABLA',
                    'IMPORTANTE: los plazos exactos (días hábiles) del procedimiento provienen únicamente del contexto jurídico inyectado.',
                ]),
            ],
            [
                'numero' => 'IX', 'titulo' => 'ESCALA DE SANCIONES',
                'query_rag' => 'escala sanciones disciplinarias multa suspensión terminación justa causa proporcionalidad',
                'codigos_obligatorios' => ['Art. 112 CST', 'Art. 113 CST', 'Art. 114 CST'],
                'datos_empresa_keys'   => ['sanciones_configuradas', 'faltas_leves', 'faltas_graves'],
                'instrucciones' => implode("\n", [
                    'Artículo introductorio sobre proporcionalidad de las sanciones. Luego TABLA obligatoria:',
                    'TABLA:',
                    'ENCABEZADO: Sanción | Concepto | Faltas que la generan',
                    'FILA: Llamado de atención verbal | Amonestación oral privada con registro en hoja de vida. | Faltas leves por primera vez.',
                    'FILA: Llamado de atención escrito | Notificación formal con copia a hoja de vida. | Faltas leves reiteradas.',
                    'FILA: Multa | Descuento salarial según límite legal, destinado al fondo de premios de los trabajadores. | Según gravedad de la falta.',
                    'FILA: Suspensión sin remuneración | Interrupción temporal del contrato sin pago, dentro del límite legal de días. | Faltas graves.',
                    'FILA: Terminación con justa causa | Desvinculación por falta muy grave o reincidencia. | Faltas muy graves o reincidencia en graves.',
                    'FIN_TABLA',
                    'Artículo adicional sobre: proporcionalidad; derecho de impugnación ante el Inspector del Trabajo.',
                    'Si la empresa tiene sanciones configuradas específicas (según datos empresa): incluirlas o ajustar la tabla.',
                    'IMPORTANTE: el límite máximo de la multa y de la suspensión (en días) provienen únicamente del contexto jurídico inyectado.',
                ]),
            ],
            [
                'numero' => 'X', 'titulo' => 'RECLAMOS Y PROCEDIMIENTOS',
                'query_rag' => 'reclamos peticiones trabajadores procedimiento queja instancias respuesta empleador',
                'codigos_obligatorios' => [],
                'datos_empresa_keys'   => [],
                'instrucciones' => implode("\n", [
                    'A. INSTANCIAS INTERNAS: jefe directo → área de RRHH → Gerencia. Cómo presenta el trabajador su reclamo.',
                    'B. PLAZO DE RESPUESTA: la empresa debe responder por escrito en un plazo razonable desde la recepción del reclamo. El plazo exacto se indica si aparece en el contexto jurídico inyectado.',
                    'C. RECLAMOS CONTRA EL SUPERIOR JERÁRQUICO: procedimiento especial cuando el reclamo involucra directamente al superior.',
                    'D. ACCESO EXTERNO: si no hay acuerdo interno, el trabajador puede acudir al Inspector del Trabajo o a la jurisdicción laboral ordinaria.',
                    'E. PROHIBICIÓN DE REPRESALIAS: el empleador no puede tomar represalias contra el trabajador que presente reclamos de buena fe.',
                ]),
            ],
            [
                'numero' => 'XI', 'titulo' => 'NORMAS DE CONDUCTA Y COMPORTAMIENTO',
                'query_rag' => 'obligaciones especiales trabajador empleador prohibiciones conducta confidencialidad',
                'codigos_obligatorios' => ['Art. 57 CST', 'Art. 58 CST', 'Art. 59 CST', 'Art. 60 CST'],
                'datos_empresa_keys'   => ['politica_celular', 'usa_uniforme', 'tiene_codigo_etica', 'politica_confidencialidad', 'que_quiere_prevenir'],
                'instrucciones' => implode("\n", [
                    'A. OBLIGACIONES DEL TRABAJADOR: puntualidad, cuidado de bienes, respeto hacia compañeros/superiores/clientes, confidencialidad, obediencia a instrucciones razonables, reporte oportuno de novedades. Lista 1) 2) 3).',
                    'B. OBLIGACIONES DEL EMPLEADOR: suministrar instrumentos y condiciones de trabajo, garantizar seguridad, pagar oportunamente, respetar la dignidad del trabajador.',
                    'C. PROHIBICIONES DEL TRABAJADOR: sustracción de bienes, actividades personales durante la jornada, consumo de alcohol o sustancias psicoactivas, proselitismo político o religioso, uso ilícito de recursos de la empresa, divulgación de información confidencial.',
                    'D. POLÍTICA DE USO DE CELULARES Y DISPOSITIVOS PERSONALES: según datos empresa; uso personal restringido o prohibido durante la jornada laboral.',
                    'E. Si usa uniforme (según datos empresa): artículo sobre entrega, uso obligatorio, mantenimiento y devolución.',
                    'F. POLÍTICA DE CONFIDENCIALIDAD: información empresarial reservada; obligación vigente durante y después de la relación laboral. Según política de la empresa.',
                    'G. Mencionar específicamente qué quiere prevenir la empresa (según datos empresa).',
                ]),
            ],
            [
                'numero' => 'XII', 'titulo' => 'SEGURIDAD Y SALUD EN EL TRABAJO',
                'query_rag' => 'seguridad salud trabajo SG-SST obligaciones empleador trabajador EPP exámenes médicos accidentes laborales COPASST',
                'codigos_obligatorios' => [],
                'datos_empresa_keys'   => ['tiene_sg_sst', 'riesgos_principales', 'tiene_epp', 'epp_descripcion', 'num_trabajadores'],
                'instrucciones' => implode("\n", [
                    'Cada artículo mínimo 60 palabras:',
                    'A. POLÍTICA DE SST: compromiso de la alta dirección, recursos asignados, objetivos del sistema de gestión.',
                    'B. OBLIGACIONES DEL EMPLEADOR EN SST: afiliar a ARL, proveer EPP, garantizar condiciones seguras, realizar exámenes médicos de ingreso/periódicos/egreso, investigar accidentes y enfermedades laborales.',
                    'C. OBLIGACIONES DEL TRABAJADOR EN SST: usar correctamente el EPP, reportar condiciones inseguras, asistir a capacitaciones, no manipular equipos de seguridad sin autorización.',
                    'D. VIGÍA DE SST O COPASST: según número de trabajadores (ver datos empresa); período de gestión, reuniones y funciones de vigilancia. Los umbrales exactos de trabajadores para cada figura provienen del contexto jurídico inyectado.',
                    'E. EXÁMENES MÉDICOS OCUPACIONALES: ingreso, periódicos y de egreso; incluir exámenes de alcoholemia y sustancias psicoactivas para cargos con riesgo para terceros (conductores, maquinaria, alturas); reserva absoluta de información médica.',
                    'F. REPORTE DE ACCIDENTES: el trabajador notifica al empleador el mismo día del accidente; la empresa notifica a la ARL dentro del plazo legal; investigación interna obligatoria.',
                    'G. USO OBLIGATORIO DE EPP: según matriz de riesgos del cargo; incumplimiento constituye falta disciplinaria. EPP de la empresa según datos empresa.',
                    'H. PROHIBICIÓN PARA CARGOS DE RIESGO: artículo expreso prohibiendo a trabajadores en cargos de riesgo para terceros (conductores, operadores de maquinaria, trabajo en alturas) presentarse o permanecer en el trabajo bajo efectos de alcohol, sustancias psicoactivas o medicamentos que alteren el estado de alerta. Calificarlo como falta muy grave. Referencias normativas exactas provienen del contexto jurídico inyectado.',
                    'I. Riesgos principales identificados en la empresa (según datos empresa).',
                ]),
            ],
            [
                'numero' => 'XIII', 'titulo' => 'USO DE EQUIPOS, UNIFORMES Y BIENES DE LA EMPRESA',
                'query_rag' => 'equipos bienes empresa responsabilidad trabajador daños uniformes devolución activos',
                'codigos_obligatorios' => [],
                'datos_empresa_keys'   => ['usa_uniforme'],
                'instrucciones' => implode("\n", [
                    'A. ASIGNACIÓN DE EQUIPOS: procedimiento de entrega formal mediante acta con inventario detallado.',
                    'B. RESPONSABILIDAD POR DAÑOS: el trabajador responde por daños causados por negligencia, descuido o mal uso intencional; no responde por el deterioro derivado del uso normal.',
                    'C. Si usa uniforme (según datos empresa): entrega, uso obligatorio durante la jornada, mantenimiento adecuado, prohibición de uso en actividades que dañen la imagen corporativa, devolución al terminar.',
                    'D. DEVOLUCIÓN DE BIENES: obligación de devolver todos los bienes asignados al terminar el contrato, mediante acta de devolución.',
                    'E. USO DE RECURSOS TECNOLÓGICOS: los equipos de cómputo, acceso a internet y correo corporativo son para uso laboral; la empresa puede monitorear su uso para fines de seguridad.',
                ]),
            ],
            [
                'numero' => 'XIV', 'titulo' => 'COMITÉ DE CONVIVENCIA LABORAL Y PREVENCIÓN DE ACOSO',
                'query_rag' => 'acoso laboral sexual comité convivencia modalidades procedimiento queja denuncia prevención',
                'codigos_obligatorios' => [],
                'datos_empresa_keys'   => ['num_trabajadores'],
                'instrucciones' => implode("\n", [
                    'Cada artículo mínimo 60 palabras:',
                    'A. ACOSO LABORAL — DEFINICIÓN Y MODALIDADES: persecución, discriminación, entorpecimiento, inequidad y desprotección. Definir cada modalidad con ejemplo concreto. Las referencias normativas exactas provienen del contexto jurídico inyectado.',
                    'B. COMITÉ DE CONVIVENCIA LABORAL: conformación bipartita (representantes del empleador y de los trabajadores); elección democrática de los representantes de los trabajadores; período de gestión; reuniones ordinarias y extraordinarias. El número exacto de representantes y las resoluciones aplicables provienen del contexto jurídico inyectado.',
                    'C. FUNCIONES DEL COMITÉ: recibir quejas de acoso, examinar las conductas denunciadas, facilitar el diálogo entre las partes, formular recomendaciones, hacer seguimiento a las medidas adoptadas.',
                    'D. PROCEDIMIENTO INTERNO DE QUEJA POR ACOSO LABORAL — pasos numerados: 1) Presentación escrita al Comité; 2) Notificación al presunto acosador; 3) Investigación confidencial; 4) Audiencia de conciliación; 5) Informe final con medidas correctivas y plazos; 6) Seguimiento periódico.',
                    'E. PREVENCIÓN DEL ACOSO SEXUAL — ARTÍCULO AUTÓNOMO: definición de acoso sexual en el contexto laboral; tipos de conductas constitutivas (verbales, físicas, digitales); canal confidencial exclusivo para denuncias; protocolo de investigación y respuesta con plazos; garantía de confidencialidad de la víctima; prohibición expresa de represalias. Las referencias normativas exactas provienen del contexto jurídico inyectado.',
                    'F. SANCIONES POR ACOSO: calificación como falta muy grave; consecuencias disciplinarias, administrativas y penales. Los artículos exactos del Código Penal aplicables provienen del contexto jurídico inyectado.',
                ]),
            ],
            [
                'numero' => 'XV', 'titulo' => 'PROTECCIÓN DE SUJETOS DE ESPECIAL PROTECCIÓN',
                'query_rag' => 'mujer embarazada maternidad paternidad discapacidad estabilidad laboral reforzada fuero sindical no discriminación',
                'codigos_obligatorios' => ['Art. 239 CST', 'Art. 240 CST', 'Art. 241 CST', 'Art. 241A CST'],
                'datos_empresa_keys'   => [],
                'instrucciones' => implode("\n", [
                    'A. MUJER EMBARAZADA Y EN PERÍODO DE LACTANCIA: protección especial contra el despido; prohibición de exigir pruebas de embarazo o exámenes de salud discriminatorios; duración de la licencia de maternidad. Los artículos y plazos exactos provienen del contexto jurídico inyectado.',
                    'B. LICENCIA DE PATERNIDAD: derecho del padre, duración, condiciones de pago. La duración exacta proviene del contexto jurídico inyectado.',
                    'C. PERSONAS EN SITUACIÓN DE DISCAPACIDAD: estabilidad laboral reforzada; prohibición de despido sin autorización previa de la autoridad competente; obligación de realizar ajustes razonables en el puesto de trabajo.',
                    'D. TRABAJADORES CON FUERO SINDICAL: prohibición de despido, traslado o desmejora sin autorización judicial previa; consecuencias del desconocimiento del fuero. Los artículos exactos del CST provienen del contexto jurídico inyectado.',
                    'E. NO DISCRIMINACIÓN: prohibición absoluta de discriminación por raza, sexo, edad, religión, orientación sexual, identidad de género, origen nacional o social, posición económica u otra condición. Referencias normativas exactas provienen del contexto jurídico inyectado.',
                ]),
            ],
            [
                'numero' => 'XVI', 'titulo' => 'DISPOSICIONES FINALES',
                'query_rag' => 'disposiciones finales vigencia reglamento interno trabajo depósito Ministerio Trabajo publicación modificaciones',
                'codigos_obligatorios' => [],
                'datos_empresa_keys'   => ['domicilio'],
                'instrucciones' => implode("\n", [
                    'A. VIGENCIA: el reglamento rige desde la fecha de su publicación a los trabajadores y permanece vigente mientras no sea derogado o modificado.',
                    'B. MODIFICACIONES: procedimiento para modificar el reglamento; obligación de comunicar a los trabajadores con anticipación mínima y de depositar ante el Ministerio del Trabajo.',
                    'C. PUBLICACIÓN Y ACCESO: publicar en lugar visible de cada establecimiento y entregar copia a cada trabajador al momento de su vinculación.',
                    'D. DEPÓSITO ANTE EL MINISTERIO: plazo para depositar ante la Dirección Territorial del Ministerio del Trabajo competente según el domicilio de la empresa. El plazo exacto proviene del contexto jurídico inyectado.',
                    'E. INCORPORACIÓN A CONTRATOS: el presente reglamento queda incorporado como parte integrante de todos los contratos individuales de trabajo.',
                    'F. ARTÍCULO FINAL DE FIRMA: ciudad, fecha de elaboración (usar fecha actual), nombre completo y cargo del representante legal.',
                ]),
            ],
        ];
    }

    private function _placeholder(): string
    {
        // Este método no se usa — aquí termina la clase
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
        if (strlen($contextoBiblioteca) > 10000) {
            $contextoBiblioteca = substr($contextoBiblioteca, 0, 10000) . "\n[...fragmentos adicionales omitidos por límite de longitud]";
        }

        $razonSocial  = $empresa->nombre_completo; // razón social + tipo societario
        $nit          = $empresa->nit;

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

        // Sanciones configuradas (nuevo formato por conducta) o fallback al formato antiguo
        $sancionesTexto = '';
        if (!empty($r['sanciones_configuradas']) && is_array($r['sanciones_configuradas'])) {
            $leves  = array_filter($r['sanciones_configuradas'], fn($s) => ($s['tipo_falta'] ?? '') === 'leve');
            $graves = array_filter($r['sanciones_configuradas'], fn($s) => ($s['tipo_falta'] ?? '') === 'grave');
            $fmtSancion = fn(array $s): string => match ($s['tipo_sancion'] ?? '') {
                'llamado_atencion' => 'llamado de atención',
                'suspension'       => 'suspensión' . (!empty($s['dias_suspension']) ? ' de ' . $s['dias_suspension'] . ' días' : ''),
                'terminacion'      => 'terminación del contrato',
                default            => $s['tipo_sancion'] ?? '',
            };
            if ($leves) {
                $sancionesTexto .= "- Faltas leves y sus sanciones:\n";
                foreach ($leves as $s) {
                    $sancionesTexto .= "  - {$s['nombre']}: {$fmtSancion($s)}\n";
                }
            }
            if ($graves) {
                $sancionesTexto .= "- Faltas graves y sus sanciones:\n";
                foreach ($graves as $s) {
                    $sancionesTexto .= "  - {$s['nombre']}: {$fmtSancion($s)}\n";
                }
            }
        } else {
            $sancionesTexto .= "- Faltas leves: " . $lista($r['faltas_leves'] ?? []) . "\n";
            $sancionesTexto .= "- Faltas graves: " . $lista($r['faltas_graves'] ?? []) . "\n";
            if (!empty($r['sanciones_contempladas'])) {
                $sancionesTexto .= "- Sanciones contempladas: " . $lista($r['sanciones_contempladas'] ?? []) . "\n";
            }
        }

        $representante = $empresa->representante_legal ?? '';
        $fechaHoy      = now()->locale('es')->translatedFormat('j \d\e F \d\e Y');

        $infoEmpresa = "
EMPRESA Y ACTIVIDAD
- Razón social: {$razonSocial}
- Tipo societario: " . ($empresa->tipo_societario ?? 'No especificado') . "
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
- Modalidades de jornada: " . $lista($r['modalidades_jornada'] ?? []) . "
- Horario principal/administrativo: " . ($r['horario_entrada'] ?? '') . " a " . ($r['horario_salida'] ?? '') . "
- Opera en múltiples turnos: " . ($r['opera_en_turnos'] ?? 'No') . "
- Número de turnos: " . ($r['numero_turnos'] ?? 'N/A') . "
- Definición de turnos: " . ($r['definicion_turnos'] ?? 'N/A') . "
- Sistema de rotación: " . ($r['rotacion_turnos'] ?? 'N/A') . "
- Cargos con turno nocturno regular (21:00-06:00): " . ($r['cargos_nocturnos'] ?? 'N/A') . "
- Trabaja sábados: " . ($r['trabaja_sabados'] ?? 'no') . "
- Trabaja dominicales/festivos: " . ($r['trabaja_dominicales'] ?? 'no') . "
- Cargos exentos jornada máxima (Art. 162 CST): " . ($r['cargos_exentos_jornada'] ?? 'N/A') . "
- Control de asistencia: " . ($r['control_asistencia'] ?? '') . "
- Política horas extras: " . ($r['politica_horas_extras'] ?? '') . "

SALARIO Y BENEFICIOS
- Forma de pago: " . ($r['forma_pago'] ?? '') . "
- Periodicidad de pago: " . $lista($r['periodicidad_pago'] ?? []) . "
- Detalle periodicidad por cargo: " . ($r['periodicidad_detalle'] ?? 'N/A') . "
- Maneja comisiones/bonificaciones: " . ($r['maneja_comisiones'] ?? 'no') . "
- Tipo de comisiones: " . ($r['tipo_comisiones'] ?? 'N/A') . "
- Beneficios extralegales:\n" . ($beneficiosTexto ?: "  - Ninguno\n") . "
- Política de permisos personales: " . ($r['politica_permisos'] ?? '') . "
- Licencias especiales: " . ($r['tiene_licencias_especiales'] ?? 'no') . "
- Descripción licencias: " . ($r['descripcion_licencias'] ?? 'N/A') . "

RÉGIMEN DISCIPLINARIO
" . $sancionesTexto . "

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
4. El título del capítulo va en DOS líneas propias en MAYÚSCULAS:
   Primera línea: CAPÍTULO I
   Segunda línea: DENOMINACIÓN, DOMICILIO Y OBJETO
5. Cada artículo ocupa su propia línea con el texto COMPLETO en esa misma línea: ARTÍCULO 1. NOMBRE. Texto completo aquí sin cortar. Si el desarrollo es extenso, los párrafos adicionales de desarrollo van en líneas separadas a continuación (sin prefijo ARTÍCULO).
6. PARÁGRAFO va en su propia línea: PARÁGRAFO PRIMERO. Texto del parágrafo.
7. Para listas dentro de un artículo usa numeración interna en líneas separadas: "1) texto" "2) texto" etc.
8. Sin Markdown: sin asteriscos, sin # ni **.
9. TABLAS — usa este formato exacto para datos estructurados (horarios, escalas, etapas):
   TABLA:
   ENCABEZADO: Columna 1 | Columna 2 | Columna 3
   FILA: dato1 | dato2 | dato3
   FIN_TABLA
   Las tablas van inmediatamente después del párrafo de ARTÍCULO al que pertenecen.
   USA TABLAS OBLIGATORIAMENTE en: horario de trabajo, escala de sanciones disciplinarias, etapas del proceso disciplinario.

EJEMPLO DE FORMATO CORRECTO (sigue este modelo exactamente):

CAPÍTULO II
ADMISIÓN Y PERÍODO DE PRUEBA

ARTÍCULO 4. REQUISITOS DE INGRESO. Para ingresar como trabajador de {$razonSocial} se requerirá la presentación de hoja de vida con soportes, fotocopia del documento de identidad, certificados de estudios y experiencia laboral, certificado de antecedentes judiciales y disciplinarios, y los demás documentos que la empresa estime pertinentes conforme a la naturaleza del cargo. Queda expresamente prohibido solicitar prueba de embarazo o estado de gravidez como requisito de ingreso, así como cualquier otra condición que configure discriminación en el proceso de selección.

ARTÍCULO 5. PERÍODO DE PRUEBA. El período de prueba deberá pactarse siempre por escrito como cláusula expresa del contrato de trabajo. En contratos a término indefinido, el período de prueba no podrá exceder de dos (2) meses. En contratos a término fijo, el período de prueba no podrá exceder de la quinta parte del término pactado, sin que pueda exceder de dos (2) meses. Durante el período de prueba cualquiera de las partes podrá dar por terminado el contrato en cualquier momento, sin previo aviso y sin indemnización, pero la terminación debe ser fundamentada y comunicada por escrito.

EJEMPLO DE TABLA — HORARIO (úsalo exactamente así):
ARTÍCULO 12. HORARIO DE TRABAJO. La jornada ordinaria de trabajo se distribuye de la siguiente manera:
TABLA:
ENCABEZADO: Días | Horario mañana | Descanso almuerzo | Horario tarde
FILA: Lunes a jueves | 07:00 am a 12:00 pm | 12:00 pm a 01:00 pm | 01:00 pm a 05:00 pm
FILA: Viernes | 07:00 am a 12:00 pm | 12:00 pm a 01:00 pm | 01:00 pm a 04:00 pm
FIN_TABLA

EJEMPLO DE TABLA — SANCIONES (úsalo exactamente así):
ARTÍCULO X. ESCALA DE SANCIONES. Las sanciones disciplinarias aplicables son las siguientes:
TABLA:
ENCABEZADO: Sanción | Descripción | Aplica para
FILA: Llamado de atención verbal | Amonestación oral privada con registro en hoja de vida. | Faltas leves (primera vez)
FILA: Llamado de atención escrito | Notificación formal al trabajador con copia a hoja de vida. | Faltas leves (reincidencia)
FILA: Suspensión sin remuneración | Interrupción temporal del contrato de 1 a 8 días calendario. | Faltas graves
FILA: Terminación con justa causa | Desvinculación por comisión de falta muy grave o reincidencia. | Faltas muy graves
FIN_TABLA

EJEMPLO DE TABLA — PROCESO DISCIPLINARIO (úsalo exactamente así):
ARTÍCULO X. PROCEDIMIENTO DISCIPLINARIO. El proceso disciplinario se desarrollará en las siguientes etapas:
TABLA:
ENCABEZADO: Etapa | Descripción
FILA: Citación a descargos | El empleador comunica por escrito los cargos al trabajador con traslado de pruebas.
FILA: Término de preparación | El trabajador dispone de cinco (5) días hábiles para preparar su defensa.
FILA: Audiencia de descargos | El trabajador presenta sus descargos; puede estar acompañado de un representante sindical o persona de confianza.
FILA: Decisión motivada | El empleador emite fallo escrito fundamentado.
FILA: Notificación e impugnación | Se notifica al trabajador quien tiene 5 días hábiles para apelar ante el superior jerárquico.
FIN_TABLA

CAPÍTULOS OBLIGATORIOS — redacta CADA artículo como párrafo completo, no como resumen.
RECUERDA: cada capítulo va en DOS líneas (CAPÍTULO X en la primera, TÍTULO en la segunda):

CAPÍTULO I
DENOMINACIÓN, DOMICILIO Y OBJETO
Artículos requeridos: ámbito de aplicación del reglamento, denominación y NIT de la empresa, domicilio principal y sucursales, actividad económica, representante legal y su facultad para sancionar.

CAPÍTULO II
ADMISIÓN Y PERÍODO DE PRUEBA
Artículos requeridos: documentos exigidos para ingreso (hoja de vida, fotocopia del documento de identidad, certificados de estudio y experiencia; el certificado de antecedentes judiciales solo es exigible cuando el cargo lo requiera por razones de seguridad, y NO podrá usarse como criterio automático de exclusión); período de prueba estipulado siempre por escrito — máximo 2 meses en indefinidos y proporcional al plazo en fijos; prórroga del período de prueba solo por acuerdo escrito dentro del plazo original; prohibición expresa de discriminación en selección.
ARTÍCULO OBLIGATORIO VERBATIM — incluir esta regla exacta: "El período de prueba deberá pactarse siempre por escrito como cláusula expresa del contrato de trabajo. La terminación durante el período de prueba debe comunicarse con fundamentación y por escrito."
ARTÍCULO OBLIGATORIO VERBATIM sobre prohibiciones de ingreso — incluir con esta redacción exacta: "Queda expresamente prohibido exigir como requisito de ingreso la presentación de la libreta militar, certificados o pruebas de gravidez o estado de embarazo, prueba de VIH/SIDA, ni ningún otro examen o documento que pueda constituir discriminación en el proceso de selección, de conformidad con el artículo 77 del Decreto 2663 de 1950, la Ley 972 de 2005 y la Ley 1010 de 2006."

CAPÍTULO III
JORNADA ORDINARIA DE TRABAJO
Artículos requeridos:
A) Jornada máxima semanal: 47h con reducción progresiva a 42h (Ley 2101/2021); definición de trabajo diurno (06:00-21:00) y nocturno (21:00-06:00); distribución de la jornada diaria con el descanso para almuerzo.
B) Horario específico de la empresa — OBLIGATORIO usar tabla con el horario exacto del cuestionario:
   Texto del artículo en una línea, seguido INMEDIATAMENTE de:
   TABLA:
   ENCABEZADO: Días | Horario mañana | Descanso almuerzo | Horario tarde
   FILA: [días] | [hora entrada] | [hora inicio almuerzo] a [hora fin almuerzo] | [hora regreso] a [hora salida]
   FIN_TABLA
   Si la empresa trabaja sábado, agregar FILA: Sábado | [horario] | [almuerzo] | [salida]
C) DESCANSO DOMINICAL OBLIGATORIO — artículo independiente: "Todo trabajador tiene derecho a un descanso remunerado que comprende el domingo de cada semana, de conformidad con el artículo 181 del Código Sustantivo del Trabajo. Este descanso será remunerado con el salario ordinario de un día de trabajo." (Art. 181 CST)
D) DESCANSO COMPENSATORIO — artículo independiente: cuando por razón del trabajo se labore el día de descanso obligatorio, el trabajador tendrá derecho a un descanso compensatorio remunerado en la semana siguiente, sin perjuicio del recargo del 75% sobre el valor del trabajo en domingo o festivo (Art. 182 CST).
Si la empresa opera en múltiples turnos: artículo específico para cada turno con nombre, horario exacto y cargos, usando tabla.
Si existen cargos de dirección, manejo o confianza (Art. 162 CST): artículo expreso indicando que dichos cargos quedan excluidos del límite de jornada máxima, sin que esto les prive del descanso dominical remunerado.

CAPÍTULO IV
TRABAJO SUPLEMENTARIO, DOMINICALES Y FESTIVOS
Artículos requeridos:
A) Límite horas extras — VERBATIM OBLIGATORIO: "El trabajo suplementario o de horas extras no podrá exceder de dos (2) horas diarias ni de doce (12) horas semanales, de conformidad con el artículo 167A del Decreto 2663 de 1950 (Código Sustantivo del Trabajo)." Autorización previa y escrita del empleador; horas extras no autorizadas no generan pago.
B) Recargos exactos: hora extra diurna 25% sobre el ordinario; hora extra nocturna 75%; trabajo en dominical o festivo 75%; recargo nocturno ordinario 35% (trabajo entre 21:00-06:00 no en jornada ordinaria).
C) Si la empresa opera en turnos nocturnos regulares: artículo expreso sobre recargo nocturno del 35% para quienes tienen jornada ordinaria nocturna.
D) Registro individual del trabajo suplementario por trabajador, firmado por ambas partes.

CAPÍTULO V
REMUNERACIÓN Y FORMA DE PAGO
Artículos requeridos:
A) Modalidades de salario: por unidad de tiempo, por obra o tarea, variable; el salario integral (cuando supere 10 SMMLV incluye prestaciones) si aplica a algún cargo de la empresa.
B) Período de pago: jornales (trabajo diario u obra) se pagan semanal o quincenalmente; sueldos (contrato a tiempo) se pagan mensualmente; periodicidad específica de la empresa según los datos del cuestionario.
C) Forma de pago: modalidad indicada en el cuestionario (transferencia, efectivo, cheque o mixto).
D) Prohibición de trueque — VERBATIM OBLIGATORIO: "Queda absolutamente prohibido pagar el salario con fichas, vales, mercancías, bonos o cualquier otro signo representativo, así como con bebidas alcohólicas, estupefacientes o sustancias alucinógenas." (Art. 134 y 136 CST)
E) Salario en especie: máximo el 50% del salario total (Art. 129 CST); para trabajadores que devenguen el salario mínimo mensual legal vigente, el pago en especie no podrá exceder el 30% de ese salario; debe pactarse por escrito; los alimentos, habitación y vestido de trabajo no se consideran salario en especie cuando son ocasionales o para el desempeño del cargo.
F) Propinas — VERBATIM OBLIGATORIO: "Las propinas que reciban los trabajadores no constituyen salario y no se pueden pactar como tal. En consecuencia, no se computarán en el salario para ningún efecto legal." (Art. 131 CST)
G) Comprobante de pago discriminado que detalle devengados y descuentos.

CAPÍTULO VI
VACACIONES Y PERMISOS
Artículos requeridos:
ARTÍCULO OBLIGATORIO VERBATIM — incluir esta frase exacta: "Todo trabajador tiene derecho a quince (15) días hábiles consecutivos de vacaciones remuneradas por cada año de servicio, de conformidad con el artículo 186 del Código Sustantivo del Trabajo."
Adicionalmente: período de disfrute acordado entre partes con aviso previo de 15 días; la empresa llevará un registro especial de vacaciones con nombre del trabajador, fecha de salida, fecha de retorno y saldo acumulado (Art. 187 CST); acumulación hasta 4 años por acuerdo escrito entre las partes; interrupción justificada — cuando durante el disfrute de las vacaciones sobrevenga una causa justificada (incapacidad médica, calamidad doméstica), el trabajador tendrá derecho a reanudarlas tan pronto desaparezca la causa de interrupción; compensación en dinero — la empresa podrá, por acuerdo escrito con el trabajador, compensar en dinero hasta la mitad de las vacaciones, siempre que el trabajador devenga más de un (1) salario mínimo mensual legal vigente (Art. 189 CST); permisos remunerados (calamidad doméstica, sufragio, diligencias personales con aviso previo).

CAPÍTULO VII
LICENCIAS ESPECIALES
Artículos requeridos: licencia de maternidad 18 semanas remuneradas (Ley 2114/2021); licencia de paternidad 2 semanas remuneradas (Ley 2114/2021); licencia de luto 5 días hábiles por cónyuge, compañero permanente o familiar hasta segundo grado de consanguinidad (Ley 1280/2009); licencia por calamidad doméstica grave; licencias no remuneradas.

CAPÍTULO VIII
RÉGIMEN DISCIPLINARIO: CLASIFICACIÓN DE FALTAS
Artículos requeridos: definición de falta disciplinaria; catálogo completo de faltas LEVES con ejemplos concretos de la empresa (mínimo 8 ejemplos como lista 1) 2) 3)...); catálogo completo de faltas GRAVES con ejemplos concretos (mínimo 8); catálogo de faltas MUY GRAVES (mínimo 5).
Procedimiento disciplinario — OBLIGATORIO usar tabla con las etapas exactas:
Párrafo del artículo describiendo el proceso garantista (Art. 115 CST), seguido INMEDIATAMENTE de:
TABLA:
ENCABEZADO: Etapa | Descripción
FILA: Citación a descargos | El empleador comunica por escrito los cargos al trabajador y le traslada las pruebas que obran en su contra.
FILA: Término de preparación | El trabajador dispone de un plazo mínimo de cinco (5) días hábiles para preparar y presentar sus descargos por escrito.
FILA: Audiencia de descargos | Diligencia en la que el trabajador expone sus argumentos; puede estar acompañado de un representante sindical o la persona de su confianza.
FILA: Decisión motivada | El empleador emite fallo escrito debidamente fundamentado con las razones de hecho y de derecho que sustentan la decisión.
FILA: Notificación e impugnación | La decisión se notifica por escrito al trabajador, quien cuenta con cinco (5) días hábiles para apelar ante el superior jerárquico.
FILA: Segunda instancia | El superior jerárquico o cargo designado estudia la apelación y emite decisión definitiva dentro de los diez (10) días hábiles siguientes.
FIN_TABLA

CAPÍTULO IX
ESCALA DE SANCIONES
Artículos requeridos — OBLIGATORIO usar tabla para la escala de sanciones:
Párrafo del artículo introduciendo la escala proporcional (Art. 112-115 CST), seguido INMEDIATAMENTE de:
TABLA:
ENCABEZADO: Sanción disciplinaria | Concepto | Faltas que la generan
FILA: Llamado de atención verbal | Amonestación oral de carácter privado con registro en la hoja de vida del trabajador. | Faltas leves cometidas por primera vez.
FILA: Llamado de atención escrito | Notificación formal al trabajador mediante documento escrito con copia para la hoja de vida. | Faltas leves reiteradas.
FILA: Multa | Descuento sobre el salario equivalente a máximo 1/5 del salario diario, destinado al fondo de premios de los trabajadores, nunca a la empresa. | Faltas leves graves según gravedad.
FILA: Suspensión sin remuneración | Interrupción temporal de la prestación del servicio de 1 a 8 días calendario sin derecho a salario. | Faltas graves.
FILA: Terminación del contrato con justa causa | Desvinculación del trabajador como consecuencia de la comisión de una falta muy grave o de reincidencia en falta grave. | Faltas muy graves o reincidencia en graves.
FIN_TABLA
Garantía del debido proceso y proporcionalidad en toda sanción; derecho del trabajador a impugnar la sanción impuesta ante el Ministerio del Trabajo.

CAPÍTULO X
RECLAMOS Y PROCEDIMIENTOS
Artículos requeridos: instancias internas para presentar reclamos; plazos de respuesta máximo 15 días hábiles; procedimiento cuando el reclamo involucra al superior jerárquico; acceso a Ministerio del Trabajo o jurisdicción laboral cuando no hay acuerdo.

CAPÍTULO XI
NORMAS DE CONDUCTA Y COMPORTAMIENTO
Artículos requeridos: obligaciones especiales del trabajador (puntualidad, cuidado de bienes, respeto, confidencialidad, obediencia razonable); obligaciones del empleador (instrumentos, seguridad, pago oportuno, respeto a la dignidad); prohibiciones del trabajador (sustracción de bienes, actividades personales en jornada, consumo de alcohol/sustancias, proselitismo, uso ilícito de recursos); política de uso de celulares/dispositivos personales en jornada; política de confidencialidad de información empresarial.

CAPÍTULO XII
SEGURIDAD Y SALUD EN EL TRABAJO (SG-SST)
ARTÍCULOS OBLIGATORIOS — cada uno como párrafo completo de mínimo 60 palabras:
A) Política de SST: compromiso de la alta dirección, recursos asignados, ámbito de aplicación
B) Obligaciones del empleador en SST: afiliar a ARL, proveer EPP, garantizar condiciones seguras, realizar exámenes médicos ocupacionales de ingreso/periódicos/egreso, investigar accidentes y enfermedades laborales
C) Obligaciones del trabajador en SST: usar correctamente el EPP, reportar condiciones inseguras, asistir a capacitaciones, no manipular equipos de seguridad sin autorización
D) Vigía de SST (empresas con menos de 10 trabajadores) o COPASST (10 o más): designación, período de 2 años, reunión mensual, funciones de vigilancia
E) Exámenes médicos ocupacionales: ingreso, periódicos y egreso; obligatorios; incluir exámenes complementarios de alcoholemia y detección de sustancias psicoactivas para trabajadores en cargos que impliquen manejo de maquinaria, conducción de vehículos, trabajo en alturas o cualquier riesgo para terceros; reserva absoluta de la información médica.
F) Reporte de accidentes: el trabajador notifica al empleador el mismo día; la empresa notifica a la ARL dentro de los 2 días hábiles siguientes; investigación interna obligatoria.
G) EPP: uso obligatorio según matriz de riesgos del cargo; incumplimiento = falta disciplinaria grave.
H) Prohibición para trabajadores en cargos de riesgo: artículo expreso que prohíbe a los trabajadores que ocupen cargos que impliquen riesgo para terceros (conductores, operadores de maquinaria, trabajo en alturas, vigilantes) presentarse al trabajo o permanecer en él bajo efectos de alcohol, sustancias psicoactivas, estupefacientes o medicamentos que alteren el estado de alerta; violación = falta muy grave con terminación justificada. (Decreto 1069/2015 Art. 2.2.2.2.8.1)

CAPÍTULO XIII
USO DE EQUIPOS, UNIFORMES Y BIENES DE LA EMPRESA
Artículos requeridos: asignación formal de equipos con acta; responsabilidad del trabajador por daño causado por negligencia o mal uso; política de uniformes (si aplica) o presentación personal; devolución formal de todos los bienes al terminar el contrato.

CAPÍTULO XIV
COMITÉ DE CONVIVENCIA LABORAL Y PREVENCIÓN DE ACOSO
ARTÍCULOS OBLIGATORIOS — cada uno como párrafo completo de mínimo 60 palabras:
A) Definición y modalidades de acoso laboral: persecución, discriminación, entorpecimiento, inequidad y desprotección (Ley 1010/2006, Art. 2). Definir cada modalidad con ejemplo.
B) Comité de Convivencia Laboral — VERBATIM OBLIGATORIO incluir estas dos frases exactas:
   FRASE 1: "El comité estará conformado de manera bipartita por dos (2) representantes del empleador y dos (2) representantes de los trabajadores, para la adopción de medidas de prevención y corrección del acoso laboral, de conformidad con la Resolución 734 de 2006 y la Resolución 652 de 2012."
   FRASE 2 (PARÁGRAFO OBLIGATORIO): "Las personas que hayan sido víctimas o victimarios de conductas de acoso laboral no podrán integrar el Comité de Convivencia Laboral. Para empresas con menos de veinte (20) trabajadores, el Comité se conformará con un (1) representante del empleador y un (1) representante de los trabajadores."
   Elección democrática de representantes de los trabajadores, período de 2 años, reunión mensual ordinaria y extraordinaria cuando se presente un caso.
C) Funciones del Comité: recibir quejas, examinar conductas, facilitar diálogo entre las partes, formular recomendaciones, hacer seguimiento, informar a la dirección.
D) Procedimiento interno de queja por acoso laboral — artículo con pasos numerados: 1) presentación escrita al Comité; 2) aviso al presunto acosador en máx 5 días; 3) investigación confidencial en máx 30 días; 4) audiencia de conciliación; 5) informe final con medidas correctivas concretas y plazos; 6) seguimiento trimestral.
E) POLÍTICA DE PREVENCIÓN DEL ACOSO SEXUAL — LEY 2365 DE 2024 — ARTÍCULO AUTÓNOMO CON ESTE TÍTULO EXACTO. Contenido mínimo: definición legal de acoso sexual en el trabajo; conductas que lo constituyen (solicitudes de favores sexuales, comentarios, contacto físico no deseado, exhibicionismo, acoso digital); canal confidencial exclusivo para denuncias de acoso sexual; protocolo de atención con plazos máximos; garantía de confidencialidad de la víctima; prohibición expresa de represalias contra quien denuncie; obligación del empleador de investigar dentro de los 5 días hábiles siguientes a la denuncia.
F) Sanciones por acoso laboral o sexual: falta muy grave con terminación con justa causa, denuncia ante Inspector del Trabajo, acciones penales según gravedad (Art. 210A Código Penal para acoso sexual).

CAPÍTULO XV
PROTECCIÓN DE SUJETOS DE ESPECIAL PROTECCIÓN
Artículos requeridos:
A) Mujer embarazada y en período de lactancia: prohibición de despido sin autorización previa del Inspector del Trabajo (Art. 241A CST); licencia de maternidad de 18 semanas remuneradas (Ley 2114/2021); prohibición expresa de solicitar prueba de embarazo, examen de VIH/SIDA o hacer preguntas sobre estado de gravidez en entrevistas de trabajo o durante la relación laboral (Ley 972/2005, Art. 236 CST).
B) Licencia de paternidad: dos (2) semanas remuneradas pagadas por el empleador al padre trabajador, de conformidad con la Ley 2114 de 2021, prorrogables según el número de hijos.
C) Personas en situación de discapacidad: estabilidad laboral reforzada — prohibición de despido sin autorización del Ministerio del Trabajo (Sentencia T-306/2024 y T-427/1992 CSJ); deber de realizar ajustes razonables en el puesto de trabajo.
D) Trabajadores con fuero sindical (fundadores, adherentes, directivos): prohibición de despido, traslado o desmejora sin autorización judicial previa (Art. 405-411 CST); el desconocimiento del fuero genera la obligación de reintegro y pago de salarios dejados de percibir.
E) No discriminación: prohibición absoluta de discriminación por raza, color, sexo, edad, idioma, religión, opinión política, orientación sexual o identidad de género, origen nacional o social, posición económica o cualquier otra condición. (Art. 143 CST, Ley 1482/2011)

CAPÍTULO XVI
DISPOSICIONES FINALES
Artículos requeridos: vigencia desde la publicación a los trabajadores; procedimiento para modificaciones (comunicación a trabajadores y depósito ante Ministerio del Trabajo); obligación de publicar en lugar visible y entregar copia a cada trabajador; depósito ante la Dirección Territorial del Ministerio del Trabajo competente; incorporación del RIT a todos los contratos individuales de trabajo.
{$seccionBiblioteca}
INFORMACIÓN DE LA EMPRESA PROPORCIONADA POR EL ADMINISTRADOR:
{$infoEmpresa}

VERIFICACIÓN OBLIGATORIA ANTES DE TERMINAR — revisa CADA punto:
1. CAP. II: ¿incluye prohibición de exigir libreta militar y prueba de embarazo con referencia al Art. 77 del Decreto 2663 de 1950? ¿incluye prórroga del período de prueba?
2. CAP. III: ¿tiene ARTÍCULO INDEPENDIENTE de descanso dominical remunerado (Art. 181 CST)? ¿tiene ARTÍCULO INDEPENDIENTE de descanso compensatorio (Art. 182 CST)? ¿describe la distribución de la jornada diaria?
3. CAP. IV: ¿contiene la frase literal "no podrá exceder de dos (2) horas diarias ni de doce (12) horas semanales" con referencia al "artículo 167A del Decreto 2663 de 1950"? ¿especifica los recargos exactos (25%, 75%)?
4. CAP. V: ¿contiene la prohibición de pago con fichas, mercancías o víveres? ¿menciona el salario integral?
5. CAP. VI: ¿contiene la frase "quince (15) días hábiles consecutivos de vacaciones remuneradas"? ¿menciona el registro especial de vacaciones?
6. CAP. VIII: ¿el procedimiento disciplinario incluye el derecho del trabajador a estar acompañado de un representante sindical o persona de su confianza en la audiencia de descargos?
7. CAP. IX: ¿la escala diferencia claramente entre faltas leves (multa, no suspensión), graves (suspensión 1-8 días) y muy graves (hasta 2 meses o terminación)?
8. CAP. XII: ¿incluye exámenes de alcoholemia/sustancias para cargos de riesgo? ¿tiene artículo de prohibición expresa para trabajadores en cargos de riesgo para terceros?
9. CAP. XIV B: ¿contiene la frase "comité conformado de manera bipartita por dos (2) representantes del empleador y dos (2) representantes de los trabajadores" y referencia a la "Resolución 734 de 2006"?
10. CAP. XIV E: ¿tiene un ARTÍCULO AUTÓNOMO titulado "POLÍTICA DE PREVENCIÓN DEL ACOSO SEXUAL — LEY 2365 DE 2024" con canal de denuncia y protocolo de atención separado del artículo del Comité?
Si cualquiera de estos puntos está ausente, REDÁCTALO AHORA antes de finalizar.

Redacta el Reglamento Interno de Trabajo completo:
PROMPT;
    }
}
