<?php

namespace App\Services;

use App\Models\ActaInspeccion;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Table as TableStyle;
use PhpOffice\PhpWord\Style\Table;

class ActaInspeccionDocService
{
    public function generarDocx(ActaInspeccion $acta): string
    {
        $acta->loadMissing('empresa');

        $word = new PhpWord();
        $word->setDefaultFontName('Arial');
        $word->setDefaultFontSize(10);

        $section = $word->addSection([
            'marginLeft'   => 720,
            'marginRight'  => 720,
            'marginTop'    => 720,
            'marginBottom' => 720,
        ]);

        // ── Estilos de tabla ──────────────────────────────────────────────
        $tblStyle = [
            'borderColor' => '000000',
            'borderSize'  => 6,
            'cellMargin'  => 80,
            'width'       => 100,
            'unit'        => \PhpOffice\PhpWord\Style\Table::WIDTH_PERCENT,
        ];


        $celdaTh = ['bgColor' => '1F3864', 'valign' => 'center'];
        $fuenteTh = ['name' => 'Arial', 'size' => 10, 'bold' => true, 'color' => 'FFFFFF'];
        $centro   = ['alignment' => Jc::CENTER];
        $negrita  = ['name' => 'Arial', 'size' => 10, 'bold' => true];

        // ─────────────────────────────────────────────────────────────────
        // ENCABEZADO: Logo empresa + Título + N° Acta
        // ─────────────────────────────────────────────────────────────────
        $tbl = $section->addTable($tblStyle);
        $tbl->addRow(600);

        // Columna empresa
        $c1 = $tbl->addCell(4500, ['bgColor' => 'F2F2F2', 'valign' => 'center']);
        $c1->addText(
            strtoupper($acta->empresa->razon_social ?? 'EMPRESA'),
            ['name' => 'Arial', 'size' => 11, 'bold' => true],
            ['alignment' => Jc::CENTER]
        );
        $c1->addText(
            'NIT: ' . ($acta->empresa->nit ?? ''),
            ['name' => 'Arial', 'size' => 9],
            ['alignment' => Jc::CENTER]
        );

        // Columna título
        $c2 = $tbl->addCell(3500, $celdaTh);
        $c2->addText('ACTA DE INSPECCIÓN RUTINARIA', $fuenteTh, $centro);

        // Columna N° Acta
        $c3 = $tbl->addCell(2000, ['bgColor' => 'F2F2F2', 'valign' => 'center']);
        $c3->addText('N° ACTA:', $negrita, $centro);
        $c3->addText($acta->numero_acta, ['name' => 'Arial', 'size' => 11, 'bold' => true, 'color' => '1F3864'], $centro);

        // ─────────────────────────────────────────────────────────────────
        // FILA: Fecha + Hora inicio + Hora cierre
        // ─────────────────────────────────────────────────────────────────
        $tbl2 = $section->addTable($tblStyle);
        $tbl2->addRow(400);

        $this->celdaKV($tbl2, 'FECHA:', $acta->fecha?->format('d/m/Y') ?? '', 3333);
        $this->celdaKV($tbl2, 'HORA INICIO:', $acta->hora_inicio ?? '', 3333);
        $this->celdaKV($tbl2, 'HORA CIERRE:', $acta->hora_cierre ?? '', 3334);

        // ─────────────────────────────────────────────────────────────────
        // OBJETIVO
        // ─────────────────────────────────────────────────────────────────
        $tbl3 = $section->addTable($tblStyle);
        $tbl3->addRow(300);
        $h = $tbl3->addCell(10000, $celdaTh);
        $h->addText('OBJETIVO DE LA INSPECCIÓN', $fuenteTh);

        $tbl3->addRow();
        $b = $tbl3->addCell(10000, ['valign' => 'top']);
        $b->addText($acta->objetivo ?? '', null, ['spaceAfter' => 40]);

        // ─────────────────────────────────────────────────────────────────
        // TEMA
        // ─────────────────────────────────────────────────────────────────
        $tbl4 = $section->addTable($tblStyle);
        $tbl4->addRow(300);
        $h4 = $tbl4->addCell(10000, $celdaTh);
        $h4->addText('TEMA DE LA INSPECCIÓN', $fuenteTh);
        $tbl4->addRow();
        $b4 = $tbl4->addCell(10000);
        $b4->addText($acta->tema ?? '', null, ['spaceAfter' => 40]);

        // ─────────────────────────────────────────────────────────────────
        // ASISTENTES
        // ─────────────────────────────────────────────────────────────────
        $tbl5 = $section->addTable($tblStyle);
        $tbl5->addRow(300);
        $th5 = $tbl5->addCell(10000, $celdaTh);
        $th5->addText('ASISTENTES', $fuenteTh);

        $tbl5->addRow(300);
        foreach (['NOMBRE', 'CARGO', 'FIRMA'] as $w => $col) {
            $widths5 = [3500, 3500, 3000];
            $c = $tbl5->addCell($widths5[$w], ['bgColor' => 'D9E1F2']);
            $c->addText($col, $negrita, $centro);
        }

        $asistentes = $acta->asistentes ?? [];
        // Mínimo 5 filas
        $filas5 = max(5, count($asistentes));
        for ($i = 0; $i < $filas5; $i++) {
            $tbl5->addRow(450);
            $tbl5->addCell(3500)->addText($asistentes[$i]['nombre'] ?? '');
            $tbl5->addCell(3500)->addText($asistentes[$i]['cargo'] ?? '');
            $tbl5->addCell(3000)->addText('');  // Firma (en blanco — se firma a mano)
        }

        // ─────────────────────────────────────────────────────────────────
        // COMPROMISOS
        // ─────────────────────────────────────────────────────────────────
        $tbl6 = $section->addTable($tblStyle);
        $tbl6->addRow(300);
        $th6 = $tbl6->addCell(10000, $celdaTh);
        $th6->addText('COMPROMISOS', $fuenteTh);

        $tbl6->addRow(300);
        $widths6 = [700, 5300, 2500, 1500];
        foreach (['ITEM', 'COMPROMISO', 'RESPONSABLE', 'FIRMA'] as $wi => $col) {
            $c = $tbl6->addCell($widths6[$wi], ['bgColor' => 'D9E1F2']);
            $c->addText($col, $negrita, $centro);
        }

        $compromisos = $acta->compromisos ?? [];
        $filas6 = max(6, count($compromisos));
        for ($i = 0; $i < $filas6; $i++) {
            $tbl6->addRow(450);
            $tbl6->addCell($widths6[0])->addText(($i + 1) . '.', null, $centro);
            $tbl6->addCell($widths6[1])->addText($compromisos[$i]['compromiso'] ?? '');
            $tbl6->addCell($widths6[2])->addText($compromisos[$i]['responsable'] ?? '');
            $tbl6->addCell($widths6[3])->addText('');
        }

        // ─────────────────────────────────────────────────────────────────
        // HALLAZGOS
        // ─────────────────────────────────────────────────────────────────
        $tbl7 = $section->addTable($tblStyle);
        $tbl7->addRow(300);
        $h7 = $tbl7->addCell(10000, $celdaTh);
        $h7->addText('HALLAZGOS', $fuenteTh);
        $tbl7->addRow();
        $b7 = $tbl7->addCell(10000);
        $b7->addText($acta->hallazgos ?? '', null, ['spaceAfter' => 80]);

        // ─────────────────────────────────────────────────────────────────
        // OBSERVACIONES
        // ─────────────────────────────────────────────────────────────────
        $tbl8 = $section->addTable($tblStyle);
        $tbl8->addRow(300);
        $h8 = $tbl8->addCell(10000, $celdaTh);
        $h8->addText('OBSERVACIONES', $fuenteTh);
        $tbl8->addRow();
        $b8 = $tbl8->addCell(10000);
        $b8->addText($acta->observaciones ?? '', null, ['spaceAfter' => 80]);

        // ─────────────────────────────────────────────────────────────────
        // PIE: Elaboró / Revisó
        // ─────────────────────────────────────────────────────────────────
        $tbl9 = $section->addTable($tblStyle);
        $tbl9->addRow(300);
        $p1 = $tbl9->addCell(5000, ['bgColor' => 'F2F2F2', 'valign' => 'center']);
        $p1->addText('Elaboró: ' . ($acta->user?->name ?? ''), $negrita);
        $p2 = $tbl9->addCell(5000, ['bgColor' => 'F2F2F2', 'valign' => 'center']);
        $p2->addText('Estado: ' . strtoupper($acta->estado), $negrita, ['alignment' => Jc::RIGHT]);

        // ─────────────────────────────────────────────────────────────────
        // Guardar
        // ─────────────────────────────────────────────────────────────────
        $dir  = storage_path('app/private/actas-inspeccion');
        if (!is_dir($dir)) mkdir($dir, 0775, true);

        $ruta = $dir . '/' . str_replace('/', '-', $acta->numero_acta) . '_' . $acta->id . '.docx';

        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($word, 'Word2007');
        $writer->save($ruta);

        return $ruta;
    }

    private function celdaKV($tabla, string $label, string $value, int $width): void
    {
        $cell = $tabla->addCell($width, ['bgColor' => 'F2F2F2']);
        $run  = $cell->addTextRun();
        $run->addText($label . ' ', ['name' => 'Arial', 'size' => 10, 'bold' => true]);
        $run->addText($value,       ['name' => 'Arial', 'size' => 10]);
    }
}
