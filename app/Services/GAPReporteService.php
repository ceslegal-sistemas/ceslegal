<?php

namespace App\Services;

use App\Models\AuditoriaRIT;
use App\Models\Empresa;
use App\Models\GapReporte;
use Dompdf\Adapter\CPDF as CpdfAdapter;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;

class GAPReporteService
{
    /**
     * Genera ambos reportes PDF (ejecutivo y técnico) a partir de una auditoría completada.
     * No realiza llamadas a Gemini; trabaja exclusivamente con datos ya almacenados.
     */
    public function generarAmbosReportes(AuditoriaRIT $auditoria): GapReporte
    {
        if ($auditoria->estado !== 'completado') {
            throw new \RuntimeException('La auditoría debe estar completada antes de generar el reporte GAP.');
        }

        $empresa   = $auditoria->empresa;
        $secciones = $auditoria->secciones ?? [];

        if (empty($secciones)) {
            throw new \RuntimeException('La auditoría no tiene secciones registradas.');
        }

        $reporte = GapReporte::updateOrCreate(
            ['auditoria_rit_id' => $auditoria->id],
            [
                'empresa_id'     => $empresa->id,
                'estado'         => 'generando',
                'score_snapshot' => $auditoria->score,
                'mensaje_error'  => null,
            ]
        );

        try {
            $gapsAgrupados = $this->agruparPorRiesgo($secciones);

            $rutaEjecutivo = $this->generarPDF($auditoria, $empresa, $gapsAgrupados, 'ejecutivo');
            $rutaTecnico   = $this->generarPDF($auditoria, $empresa, $gapsAgrupados, 'tecnico');

            $reporte->update([
                'estado'         => 'completado',
                'ruta_ejecutivo' => $rutaEjecutivo,
                'ruta_tecnico'   => $rutaTecnico,
            ]);

            Log::info('GAPReporteService: reportes generados', [
                'auditoria_id' => $auditoria->id,
                'empresa_id'   => $empresa->id,
            ]);

        } catch (\Throwable $e) {
            $reporte->update([
                'estado'        => 'error',
                'mensaje_error' => $e->getMessage(),
            ]);

            Log::error('GAPReporteService: fallo al generar reportes', [
                'auditoria_id' => $auditoria->id,
                'error'        => $e->getMessage(),
            ]);

            throw $e;
        }

        return $reporte;
    }

    /**
     * Agrupa secciones por nivel de riesgo según su score:
     * 0–39 → alto, 40–69 → medio, 70–99 → bajo, 100 → sin_gap
     */
    public function agruparPorRiesgo(array $secciones): array
    {
        $grupos = ['alto' => [], 'medio' => [], 'bajo' => [], 'sin_gap' => []];

        foreach ($secciones as $clave => $seccion) {
            $score = $seccion['score'] ?? 0;
            $nivel = match (true) {
                $score <= 39  => 'alto',
                $score <= 69  => 'medio',
                $score <= 99  => 'bajo',
                default       => 'sin_gap',
            };
            $grupos[$nivel][$clave] = $seccion;
        }

        return $grupos;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Generación PDF: LibreOffice (primario) → DomPDF (fallback)
    // ─────────────────────────────────────────────────────────────────────────

    private function generarPDF(
        AuditoriaRIT $auditoria,
        Empresa $empresa,
        array $gapsAgrupados,
        string $tipo
    ): string {
        $loPath = $this->detectarLibreOffice();

        if ($loPath) {
            try {
                return $this->generarPDFviaLibreOffice($auditoria, $empresa, $gapsAgrupados, $tipo, $loPath);
            } catch (\Exception $e) {
                Log::warning('GAPReporteService: LibreOffice falló, fallback a DomPDF', [
                    'tipo'  => $tipo,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->generarPDFviaDomPDF($auditoria, $empresa, $gapsAgrupados, $tipo);
    }

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

    private function generarPDFviaLibreOffice(
        AuditoriaRIT $auditoria,
        Empresa $empresa,
        array $gapsAgrupados,
        string $tipo,
        string $loPath
    ): string {
        $uid     = uniqid('gap_', true);
        $tmpDir  = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $uid;
        mkdir($tmpDir, 0755, true);

        $docxPath = $tmpDir . DIRECTORY_SEPARATOR . 'gap.docx';
        $pdfPath  = $tmpDir . DIRECTORY_SEPARATOR . 'gap.pdf';

        $this->escribirDocx($auditoria, $empresa, $gapsAgrupados, $tipo, $docxPath);

        // Mismo formato que RITGeneratorService: array para proc_open
        // evita problemas de escaping en Windows con cmd.exe
        $profileDir = str_replace('\\', '/', $tmpDir . '/lo_profile');
        $loProfileUrl = 'file:///' . ltrim($profileDir, '/');

        $cmd = [
            $loPath,
            '--headless',
            '--nofirststartwizard',
            '-env:UserInstallation=' . $loProfileUrl,
            '--convert-to', 'pdf',
            '--outdir', $tmpDir,
            $docxPath,
        ];

        $process = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        if (!is_resource($process)) {
            $this->limpiarDir($tmpDir);
            throw new \RuntimeException('No se pudo iniciar LibreOffice para GAP');
        }

        fclose($pipes[0]);
        $timeout  = 60;
        $deadline = microtime(true) + $timeout;
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $code = null;

        while (microtime(true) < $deadline) {
            $status = proc_get_status($process);
            if (!$status['running']) { $code = $status['exitcode']; break; }
            usleep(200_000);
        }

        fclose($pipes[1]);
        fclose($pipes[2]);

        if ($code === null) {
            proc_terminate($process);
            proc_close($process);
            $this->limpiarDir($tmpDir);
            throw new \RuntimeException('LibreOffice superó el tiempo límite al generar GAP');
        }

        proc_close($process);

        if ($code !== 0 || !file_exists($pdfPath)) {
            $this->limpiarDir($tmpDir);
            throw new \RuntimeException('LibreOffice no convirtió el DOCX GAP (código ' . $code . ')');
        }

        $directorio   = "private/gap-reportes/{$empresa->id}";
        $rutaRelativa = "{$directorio}/gap_{$tipo}_{$auditoria->id}.pdf";

        Storage::makeDirectory($directorio);
        Storage::put($rutaRelativa, file_get_contents($pdfPath));

        $this->limpiarDir($tmpDir);

        Log::info('GAPReporteService: PDF generado con LibreOffice', [
            'auditoria_id' => $auditoria->id,
            'tipo'         => $tipo,
        ]);

        return $rutaRelativa;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Generación DOCX con PhpWord (mismos márgenes A4 que el RIT)
    // ─────────────────────────────────────────────────────────────────────────

    private function escribirDocx(
        AuditoriaRIT $auditoria,
        Empresa $empresa,
        array $gapsAgrupados,
        string $tipo,
        string $rutaAbsoluta
    ): void {
        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(9);

        // Fuentes
        $fNorm = ['name' => 'Arial', 'size' => 9];
        $fBold = ['name' => 'Arial', 'size' => 9, 'bold' => true];
        $fSmal = ['name' => 'Arial', 'size' => 8];
        $fItal = ['name' => 'Arial', 'size' => 8, 'italic' => true];

        // Párrafos
        $pCenter = ['alignment' => Jc::CENTER, 'spaceAfter' => 40,  'spaceBefore' => 0,   'lineRule' => 'auto', 'line' => 240];
        $pRight  = ['alignment' => Jc::RIGHT,  'spaceAfter' => 0,   'spaceBefore' => 0,   'lineRule' => 'auto', 'line' => 240];
        $pLeft   = ['alignment' => Jc::START,  'spaceAfter' => 60,  'spaceBefore' => 0,   'lineRule' => 'auto', 'line' => 240];
        $pBoth   = ['alignment' => Jc::BOTH,   'spaceAfter' => 120, 'spaceBefore' => 0,   'lineRule' => 'auto', 'line' => 240];
        $pHdr    = ['alignment' => Jc::CENTER, 'spaceAfter' => 80,  'spaceBefore' => 180, 'lineRule' => 'auto', 'line' => 240];
        $pSubHdr = ['alignment' => Jc::START,  'spaceAfter' => 60,  'spaceBefore' => 140, 'lineRule' => 'auto', 'line' => 240];
        $pLbl    = ['alignment' => Jc::START,  'spaceAfter' => 40,  'spaceBefore' => 60,  'lineRule' => 'auto', 'line' => 240];
        $pItem   = ['alignment' => Jc::BOTH,   'spaceAfter' => 30,  'spaceBefore' => 0,   'lineRule' => 'auto', 'line' => 240,
                    'indentation' => ['left' => Converter::cmToTwip(0.5)]];
        $pNota   = ['alignment' => Jc::BOTH,   'spaceAfter' => 0,   'spaceBefore' => 200, 'lineRule' => 'auto', 'line' => 240];

        // Sección A4 con los mismos márgenes que el RIT
        $section = $phpWord->addSection([
            'paperSize'    => 'A4',
            'marginTop'    => Converter::cmToTwip(2.5),
            'marginBottom' => Converter::cmToTwip(2.5),
            'marginLeft'   => Converter::cmToTwip(3.0),
            'marginRight'  => Converter::cmToTwip(3.0),
        ]);

        // Footer con datos de contacto de la empresa
        $footer = $section->addFooter();
        $eLugar = trim(
            ($empresa->ciudad ?? '') .
            (($empresa->departamento ?? '') ? ', ' . $empresa->departamento : '')
        );
        $fLine1 = implode('. ', array_filter([$empresa->direccion ?? '', $eLugar]));
        $fLine2 = implode('   ', array_filter([
            ($empresa->telefono       ?? '') ? 'Tel. '   . $empresa->telefono       : '',
            ($empresa->email_contacto ?? '') ? 'Email. ' . $empresa->email_contacto : '',
        ]));
        if ($fLine1) $footer->addText($fLine1, $fSmal, $pRight);
        if ($fLine2) $footer->addText($fLine2, $fSmal, $pRight);

        // Ancho usable: A4 (11906 twips) - 2 * 3cm (3402 twips) = 8504 twips
        $W = 8504;

        // Estilo de tabla con bordes finos
        $tblStyle = [
            'borderSize'       => 4,
            'borderColor'      => '000000',
            'cellMarginTop'    => 60,
            'cellMarginBottom' => 60,
            'cellMarginLeft'   => 100,
            'cellMarginRight'  => 100,
        ];
        $hdrBg = ['bgColor' => 'DDDDDD'];

        // ── Encabezado ───────────────────────────────────────────────────────
        $version = ($tipo === 'ejecutivo') ? 'VERSIÓN EJECUTIVA' : 'VERSIÓN TÉCNICA';
        $section->addText('REPORTE DE ANÁLISIS GAP DE CUMPLIMIENTO NORMATIVO', $fBold, $pCenter);
        $section->addText($version, $fBold, $pCenter);
        $section->addText(
            ($empresa->nombre_completo ?? $empresa->razon_social ?? '') . ' — NIT: ' . ($empresa->nit ?? ''),
            $fNorm, $pCenter
        );
        $section->addText(
            'Fecha de auditoría: ' . $auditoria->created_at->format('d/m/Y') .
            ' — Puntaje global: ' . $auditoria->score . '/100',
            $fNorm, $pCenter
        );

        // ── Resumen de brechas ───────────────────────────────────────────────
        $nAlto   = count($gapsAgrupados['alto']);
        $nMedio  = count($gapsAgrupados['medio']);
        $nBajo   = count($gapsAgrupados['bajo']);
        $nSinGap = count($gapsAgrupados['sin_gap']);
        $total   = $nAlto + $nMedio + $nBajo + $nSinGap;

        $section->addText('RESUMEN DE BRECHAS', $fBold, $pHdr);

        $c5 = (int)($W / 5);
        $tbl = $section->addTable($tblStyle);

        $row = $tbl->addRow();
        $row->addCell($c5, $hdrBg)->addText('RIESGO ALTO (0–39)',   $fBold, $pCenter);
        $row->addCell($c5, $hdrBg)->addText('RIESGO MEDIO (40–69)', $fBold, $pCenter);
        $row->addCell($c5, $hdrBg)->addText('RIESGO BAJO (70–99)',  $fBold, $pCenter);
        $row->addCell($c5, $hdrBg)->addText('SIN BRECHA (100)',     $fBold, $pCenter);
        $row->addCell($c5, $hdrBg)->addText('TOTAL SECCIONES',      $fBold, $pCenter);

        $row = $tbl->addRow();
        $row->addCell($c5)->addText((string)$nAlto,   $fBold, $pCenter);
        $row->addCell($c5)->addText((string)$nMedio,  $fBold, $pCenter);
        $row->addCell($c5)->addText((string)$nBajo,   $fBold, $pCenter);
        $row->addCell($c5)->addText((string)$nSinGap, $fBold, $pCenter);
        $row->addCell($c5)->addText((string)$total,   $fBold, $pCenter);

        // ── Resumen ejecutivo (texto IA) ─────────────────────────────────────
        if ($auditoria->resumen_general) {
            $section->addText('RESUMEN EJECUTIVO', $fBold, $pHdr);
            $section->addText($auditoria->resumen_general, $fNorm, $pBoth);
        }

        // ── Análisis de brechas por sección ─────────────────────────────────
        $section->addText('ANÁLISIS DE BRECHAS POR SECCIÓN', $fBold, $pHdr);

        $c4 = [
            (int)($W * 0.35),
            (int)($W * 0.08),
            (int)($W * 0.12),
            $W - (int)($W * 0.35) - (int)($W * 0.08) - (int)($W * 0.12),
        ];

        $nivelLabels = ['alto' => 'Alto', 'medio' => 'Medio', 'bajo' => 'Bajo'];

        $tbl = $section->addTable($tblStyle);
        $row = $tbl->addRow();
        $row->addCell($c4[0], $hdrBg)->addText('SECCIÓN',                  $fBold, $pCenter);
        $row->addCell($c4[1], $hdrBg)->addText('SCORE',                    $fBold, $pCenter);
        $row->addCell($c4[2], $hdrBg)->addText('NIVEL DE RIESGO',          $fBold, $pCenter);
        $row->addCell($c4[3], $hdrBg)->addText('RECOMENDACIÓN PRIORITARIA', $fBold, $pCenter);

        $hayBrechas = false;
        foreach (['alto', 'medio', 'bajo'] as $nivel) {
            foreach ($gapsAgrupados[$nivel] as $clave => $sec) {
                $hayBrechas = true;
                $row = $tbl->addRow();
                $row->addCell($c4[0])->addText($sec['titulo'] ?? $clave,    $fNorm, $pLeft);
                $row->addCell($c4[1])->addText((string)($sec['score'] ?? 0), $fNorm, $pCenter);
                $row->addCell($c4[2])->addText($nivelLabels[$nivel],         $fBold, $pCenter);
                $row->addCell($c4[3])->addText(($sec['recomendaciones'] ?? [])[0] ?? '—', $fNorm, $pLeft);
            }
        }
        if (!$hayBrechas) {
            $row = $tbl->addRow();
            $row->addCell($W, ['gridSpan' => 4])->addText('No se detectaron brechas de cumplimiento.', $fNorm, $pCenter);
        }

        // ── Plan de acciones prioritarias ────────────────────────────────────
        $todasLasBrechas = [];
        foreach (['alto', 'medio', 'bajo'] as $nivel) {
            foreach ($gapsAgrupados[$nivel] as $clave => $sec) {
                foreach ($sec['recomendaciones'] ?? [] as $rec) {
                    $todasLasBrechas[] = [
                        'seccion' => $sec['titulo'] ?? $clave,
                        'nivel'   => $nivel,
                        'rec'     => $rec,
                    ];
                }
            }
        }
        $top10 = array_slice($todasLasBrechas, 0, 10);

        if (!empty($top10)) {
            $section->addText('PLAN DE ACCIONES PRIORITARIAS', $fBold, $pHdr);

            $c4b = [
                (int)($W * 0.05),
                (int)($W * 0.25),
                (int)($W * 0.10),
                $W - (int)($W * 0.05) - (int)($W * 0.25) - (int)($W * 0.10),
            ];

            $tbl = $section->addTable($tblStyle);
            $row = $tbl->addRow();
            $row->addCell($c4b[0], $hdrBg)->addText('#',                   $fBold, $pCenter);
            $row->addCell($c4b[1], $hdrBg)->addText('SECCIÓN',             $fBold, $pCenter);
            $row->addCell($c4b[2], $hdrBg)->addText('RIESGO',              $fBold, $pCenter);
            $row->addCell($c4b[3], $hdrBg)->addText('ACCIÓN RECOMENDADA',  $fBold, $pCenter);

            foreach ($top10 as $i => $item) {
                $row = $tbl->addRow();
                $row->addCell($c4b[0])->addText((string)($i + 1), $fNorm, $pCenter);
                $row->addCell($c4b[1])->addText($item['seccion'],  $fNorm, $pLeft);
                $row->addCell($c4b[2])->addText(ucfirst($item['nivel']), $fBold, $pCenter);
                $row->addCell($c4b[3])->addText($item['rec'],     $fNorm, $pLeft);
            }
        }

        // ── Solo versión técnica: hallazgos detallados ───────────────────────
        if ($tipo === 'tecnico') {
            $section->addText('HALLAZGOS DETALLADOS POR SECCIÓN', $fBold, $pHdr);

            foreach (['alto', 'medio', 'bajo'] as $nivel) {
                foreach ($gapsAgrupados[$nivel] as $clave => $sec) {
                    $titulo = ($sec['titulo'] ?? $clave)
                        . ' — Score: ' . ($sec['score'] ?? 0) . '/100'
                        . ' — Riesgo: ' . $nivelLabels[$nivel];
                    $section->addText($titulo, $fBold, $pSubHdr);

                    if (!empty($sec['hallazgos'])) {
                        $section->addText('Hallazgos:', $fBold, $pLbl);
                        foreach ($sec['hallazgos'] as $h) {
                            $section->addText('• ' . $h, $fNorm, $pItem);
                        }
                    }

                    if (!empty($sec['recomendaciones'])) {
                        $section->addText('Recomendaciones:', $fBold, $pLbl);
                        foreach ($sec['recomendaciones'] as $r) {
                            $section->addText('• ' . $r, $fNorm, $pItem);
                        }
                    }

                    if (!empty($sec['articulos_referencia'])) {
                        $arts = implode(', ', $sec['articulos_referencia']);
                        $run  = $section->addTextRun($pLbl);
                        $run->addText('Referencias normativas: ', $fBold);
                        $run->addText($arts, $fNorm);
                    }
                }
            }

            $section->addText(
                'Documento confidencial. Esta versión técnica está dirigida exclusivamente a los profesionales ' .
                'de CES Legal y contiene trazabilidad normativa para uso jurídico interno.',
                $fItal,
                $pNota
            );
        }

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($rutaAbsoluta);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Fallback DomPDF (si LibreOffice no está disponible)
    // ─────────────────────────────────────────────────────────────────────────

    private function generarPDFviaDomPDF(
        AuditoriaRIT $auditoria,
        Empresa $empresa,
        array $gapsAgrupados,
        string $tipo
    ): string {
        $html = view("gap-reportes.{$tipo}", compact('empresa', 'auditoria', 'gapsAgrupados'))->render();

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultPaperSize', 'a4');
        $options->set('isFontSubsettingEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->render();

        $canvas = $dompdf->getCanvas();
        if ($canvas instanceof CpdfAdapter) {
            $ownerPass = substr(hash('sha256', config('app.key') . $empresa->id . 'gap_' . $tipo), 0, 32);
            $canvas->get_cpdf()->setEncryption('', $ownerPass, ['print']);
        }

        $directorio   = "private/gap-reportes/{$empresa->id}";
        $rutaRelativa = "{$directorio}/gap_{$tipo}_{$auditoria->id}.pdf";

        Storage::makeDirectory($directorio);
        Storage::put($rutaRelativa, $dompdf->output());

        return $rutaRelativa;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Utilidades
    // ─────────────────────────────────────────────────────────────────────────

    private function limpiarDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $f) {
            $f->isDir() ? rmdir($f->getRealPath()) : unlink($f->getRealPath());
        }
        rmdir($dir);
    }
}
