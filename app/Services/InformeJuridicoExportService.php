<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\InformeJuridico;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;

class InformeJuridicoExportService
{
    protected const MESES = [
        'enero' => 'Enero',
        'febrero' => 'Febrero',
        'marzo' => 'Marzo',
        'abril' => 'Abril',
        'mayo' => 'Mayo',
        'junio' => 'Junio',
        'julio' => 'Julio',
        'agosto' => 'Agosto',
        'septiembre' => 'Septiembre',
        'octubre' => 'Octubre',
        'noviembre' => 'Noviembre',
        'diciembre' => 'Diciembre',
    ];

    protected const COLORES_AREA = [
        '#4f46e5', '#0ea5e9', '#10b981', '#f59e0b', '#ef4444',
        '#8b5cf6', '#ec4899', '#06b6d4', '#84cc16', '#f97316'
    ];

    protected const COLORES_ESTADO = [
        'entregado' => '#10b981',
        'pendiente' => '#f59e0b',
        'en_proceso' => '#3b82f6',
    ];

    public function getInformes(int $empresaId, int $anio, ?string $mes = null): Collection
    {
        $query = InformeJuridico::with(['areaPractica', 'tipoGestion', 'subtipo', 'creador'])
            ->where('empresa_id', $empresaId)
            ->where('anio', $anio);

        if ($mes && $mes !== 'todos') {
            $query->where('mes', $mes);
        }

        return $query->orderBy('mes')->orderBy('created_at')->get();
    }

    public function getResumenPorArea(Collection $informes): array
    {
        $colores = self::COLORES_AREA;
        $index = 0;

        return $informes->groupBy('area_practica_texto')
            ->map(function ($items, $area) use (&$index, $colores) {
                $color = $colores[$index % count($colores)];
                $index++;
                return [
                    'area' => $area,
                    'cantidad' => $items->count(),
                    'tiempo_total' => $items->sum('tiempo_minutos'),
                    'tiempo_formateado' => $this->formatearTiempo($items->sum('tiempo_minutos')),
                    'color' => $color,
                ];
            })
            ->values()
            ->toArray();
    }

    public function getResumenPorTipo(Collection $informes): array
    {
        $colores = self::COLORES_AREA;
        $index = 0;

        return $informes->groupBy('tipo_gestion_texto')
            ->map(function ($items, $tipo) use (&$index, $colores) {
                $color = $colores[$index % count($colores)];
                $index++;
                return [
                    'tipo' => $tipo,
                    'cantidad' => $items->count(),
                    'tiempo_total' => $items->sum('tiempo_minutos'),
                    'tiempo_formateado' => $this->formatearTiempo($items->sum('tiempo_minutos')),
                    'color' => $color,
                ];
            })
            ->values()
            ->toArray();
    }

    public function getResumenPorEstado(Collection $informes): array
    {
        return $informes->groupBy('estado')
            ->map(function ($items, $estado) {
                return [
                    'estado' => $items->first()->estado_texto,
                    'estado_key' => $estado,
                    'cantidad' => $items->count(),
                    'color' => self::COLORES_ESTADO[$estado] ?? '#6b7280',
                ];
            })
            ->values()
            ->toArray();
    }

    public function getResumenPorMes(Collection $informes): array
    {
        $mesesOrden = array_keys(self::MESES);

        return $informes->groupBy('mes')
            ->map(function ($items, $mes) {
                return [
                    'mes' => self::MESES[$mes] ?? $mes,
                    'mes_key' => $mes,
                    'cantidad' => $items->count(),
                    'tiempo_total' => $items->sum('tiempo_minutos'),
                    'tiempo_formateado' => $this->formatearTiempo($items->sum('tiempo_minutos')),
                ];
            })
            ->sortBy(function ($item, $key) use ($mesesOrden) {
                return array_search($key, $mesesOrden);
            })
            ->values()
            ->toArray();
    }

    protected function formatearTiempo(int $minutos): string
    {
        $horas = intdiv($minutos, 60);
        $mins = $minutos % 60;

        if ($horas > 0) {
            return "{$horas}h {$mins}m";
        }

        return "{$mins} min";
    }

    public function generarPDF(int $empresaId, int $anio, ?string $mes = null): string
    {
        $empresa = Empresa::findOrFail($empresaId);
        $informes = $this->getInformes($empresaId, $anio, $mes);

        $resumenPorArea = $this->getResumenPorArea($informes);
        $resumenPorTipo = $this->getResumenPorTipo($informes);
        $resumenPorEstado = $this->getResumenPorEstado($informes);
        $resumenPorMes = $mes === 'todos' ? $this->getResumenPorMes($informes) : null;

        // Generar SVG para gráficas
        $chartAreaSvg = $this->generarPieChartSvg($resumenPorArea, 'area', 'cantidad');
        $chartEstadoSvg = $this->generarPieChartSvg($resumenPorEstado, 'estado', 'cantidad');
        $chartMesSvg = $resumenPorMes ? $this->generarBarChartSvg($resumenPorMes, 'mes', 'cantidad') : null;
        $chartTiempoSvg = $resumenPorMes ? $this->generarBarChartSvg($resumenPorMes, 'mes', 'tiempo_total', true) : null;

        $data = [
            'empresa' => $empresa,
            'anio' => $anio,
            'mes' => $mes && $mes !== 'todos' ? (self::MESES[$mes] ?? $mes) : 'Todos los meses',
            'informes' => $informes,
            'resumenPorArea' => $resumenPorArea,
            'resumenPorTipo' => $resumenPorTipo,
            'resumenPorEstado' => $resumenPorEstado,
            'resumenPorMes' => $resumenPorMes,
            'totalGestiones' => $informes->count(),
            'tiempoTotal' => $this->formatearTiempo($informes->sum('tiempo_minutos')),
            'tiempoTotalMinutos' => $informes->sum('tiempo_minutos'),
            'fechaGeneracion' => now()->format('d/m/Y H:i'),
            'chartAreaSvg' => $chartAreaSvg,
            'chartEstadoSvg' => $chartEstadoSvg,
            'chartMesSvg' => $chartMesSvg,
            'chartTiempoSvg' => $chartTiempoSvg,
        ];

        $pdf = Pdf::loadView('exports.informe-juridico-pdf', $data);
        $pdf->setPaper('a4', 'portrait');

        $filename = 'informe-juridico-' . $empresa->id . '-' . $anio . '-' . ($mes ?? 'anual') . '.pdf';
        $path = 'exports/' . $filename;

        Storage::disk('public')->put($path, $pdf->output());

        return $path;
    }

    protected function generarPieChartSvg(array $data, string $labelKey, string $valueKey): string
    {
        if (empty($data)) {
            return '';
        }

        $total = array_sum(array_column($data, $valueKey));
        if ($total === 0) {
            return '';
        }

        $width = 300;
        $height = 200;
        $cx = 100;
        $cy = 100;
        $radius = 80;

        $svg = '<svg width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '" xmlns="http://www.w3.org/2000/svg">';

        $startAngle = -90;
        foreach ($data as $item) {
            $value = $item[$valueKey];
            $percentage = ($value / $total) * 100;
            $angle = ($value / $total) * 360;
            $endAngle = $startAngle + $angle;

            $color = $item['color'] ?? '#6b7280';

            // Calcular puntos del arco
            $x1 = $cx + $radius * cos(deg2rad($startAngle));
            $y1 = $cy + $radius * sin(deg2rad($startAngle));
            $x2 = $cx + $radius * cos(deg2rad($endAngle));
            $y2 = $cy + $radius * sin(deg2rad($endAngle));

            $largeArc = $angle > 180 ? 1 : 0;

            if ($angle < 360) {
                $svg .= '<path d="M ' . $cx . ' ' . $cy . ' L ' . $x1 . ' ' . $y1 . ' A ' . $radius . ' ' . $radius . ' 0 ' . $largeArc . ' 1 ' . $x2 . ' ' . $y2 . ' Z" fill="' . $color . '" stroke="#fff" stroke-width="2"/>';
            } else {
                $svg .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $radius . '" fill="' . $color . '"/>';
            }

            $startAngle = $endAngle;
        }

        // Leyenda
        $legendX = 210;
        $legendY = 20;
        foreach ($data as $item) {
            $label = $item[$labelKey];
            $value = $item[$valueKey];
            $percentage = round(($value / $total) * 100, 1);
            $color = $item['color'] ?? '#6b7280';

            $svg .= '<rect x="' . $legendX . '" y="' . $legendY . '" width="12" height="12" fill="' . $color . '" rx="2"/>';
            $svg .= '<text x="' . ($legendX + 18) . '" y="' . ($legendY + 10) . '" font-size="9" fill="#374151">' . mb_substr($label, 0, 12) . ' (' . $percentage . '%)</text>';
            $legendY += 18;
        }

        $svg .= '</svg>';

        return $svg;
    }

    protected function generarBarChartSvg(array $data, string $labelKey, string $valueKey, bool $isTiempo = false): string
    {
        if (empty($data)) {
            return '';
        }

        $maxValue = max(array_column($data, $valueKey));
        if ($maxValue === 0) {
            return '';
        }

        $width = 500;
        $height = 180;
        $padding = 50;
        $barWidth = ($width - $padding * 2) / count($data) - 10;
        $chartHeight = $height - $padding - 30;

        $svg = '<svg width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '" xmlns="http://www.w3.org/2000/svg">';

        // Eje Y
        $svg .= '<line x1="' . $padding . '" y1="20" x2="' . $padding . '" y2="' . ($height - 30) . '" stroke="#e5e7eb" stroke-width="1"/>';

        // Eje X
        $svg .= '<line x1="' . $padding . '" y1="' . ($height - 30) . '" x2="' . ($width - 20) . '" y2="' . ($height - 30) . '" stroke="#e5e7eb" stroke-width="1"/>';

        // Líneas de referencia
        for ($i = 0; $i <= 4; $i++) {
            $y = 20 + ($chartHeight * $i / 4);
            $value = round($maxValue * (4 - $i) / 4);
            $svg .= '<line x1="' . $padding . '" y1="' . $y . '" x2="' . ($width - 20) . '" y2="' . $y . '" stroke="#f3f4f6" stroke-width="1"/>';
            $displayValue = $isTiempo ? $this->formatearTiempo($value) : $value;
            $svg .= '<text x="' . ($padding - 5) . '" y="' . ($y + 4) . '" font-size="8" fill="#9ca3af" text-anchor="end">' . $displayValue . '</text>';
        }

        // Barras
        $x = $padding + 10;
        $colors = ['#4f46e5', '#0ea5e9', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4', '#84cc16', '#f97316', '#6366f1', '#14b8a6'];
        $colorIndex = 0;

        foreach ($data as $item) {
            $value = $item[$valueKey];
            $barHeight = ($value / $maxValue) * $chartHeight;
            $y = ($height - 30) - $barHeight;
            $color = $colors[$colorIndex % count($colors)];

            // Barra con gradiente
            $svg .= '<rect x="' . $x . '" y="' . $y . '" width="' . $barWidth . '" height="' . $barHeight . '" fill="' . $color . '" rx="3" opacity="0.85"/>';

            // Valor encima de la barra
            $displayValue = $isTiempo ? $this->formatearTiempo($value) : $value;
            $svg .= '<text x="' . ($x + $barWidth / 2) . '" y="' . ($y - 5) . '" font-size="8" fill="#374151" text-anchor="middle" font-weight="600">' . $displayValue . '</text>';

            // Etiqueta
            $label = mb_substr($item[$labelKey], 0, 3);
            $svg .= '<text x="' . ($x + $barWidth / 2) . '" y="' . ($height - 15) . '" font-size="8" fill="#6b7280" text-anchor="middle">' . $label . '</text>';

            $x += $barWidth + 10;
            $colorIndex++;
        }

        $svg .= '</svg>';

        return $svg;
    }

    public function generarExcel(int $empresaId, int $anio, ?string $mes = null): string
    {
        $empresa = Empresa::findOrFail($empresaId);
        $informes = $this->getInformes($empresaId, $anio, $mes);

        $resumenPorArea = $this->getResumenPorArea($informes);
        $resumenPorTipo = $this->getResumenPorTipo($informes);
        $resumenPorEstado = $this->getResumenPorEstado($informes);
        $resumenPorMes = $mes === 'todos' ? $this->getResumenPorMes($informes) : null;

        $spreadsheet = new Spreadsheet();

        // Hoja de Resumen
        $this->crearHojaResumen($spreadsheet, $empresa, $anio, $mes, $informes, $resumenPorArea, $resumenPorTipo, $resumenPorEstado, $resumenPorMes);

        // Hoja de Detalle
        $this->crearHojaDetalle($spreadsheet, $informes);

        // Hoja de Datos para Gráficas
        if ($resumenPorMes) {
            $this->crearHojaDatosGraficas($spreadsheet, $resumenPorArea, $resumenPorMes);
        }

        $filename = 'informe-juridico-' . $empresa->id . '-' . $anio . '-' . ($mes ?? 'anual') . '.xlsx';
        $path = 'exports/' . $filename;
        $fullPath = Storage::disk('public')->path($path);

        // Asegurar que el directorio existe
        $directory = dirname($fullPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->setIncludeCharts(true);
        $writer->save($fullPath);

        return $path;
    }

    protected function crearHojaResumen(Spreadsheet $spreadsheet, Empresa $empresa, int $anio, ?string $mes, Collection $informes, array $resumenPorArea, array $resumenPorTipo, array $resumenPorEstado, ?array $resumenPorMes): void
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Resumen');

        // Estilos
        $headerStyle = [
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ];

        $subHeaderStyle = [
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '6366F1']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];

        $tableHeaderStyle = [
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F2937']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ];

        $cellStyle = [
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ];

        // Encabezado principal
        $sheet->mergeCells('A1:F1');
        $sheet->setCellValue('A1', 'INFORME DE GESTIÓN JURÍDICA');
        $sheet->getStyle('A1:F1')->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(35);

        // Info empresa
        $sheet->mergeCells('A3:F3');
        $sheet->setCellValue('A3', $empresa->razon_social);
        $sheet->getStyle('A3')->applyFromArray(['font' => ['bold' => true, 'size' => 14]]);

        $sheet->setCellValue('A4', 'NIT:');
        $sheet->setCellValue('B4', $empresa->nit);
        $sheet->setCellValue('C4', 'Periodo:');
        $sheet->setCellValue('D4', $anio . ' - ' . ($mes && $mes !== 'todos' ? (self::MESES[$mes] ?? $mes) : 'Anual'));
        $sheet->setCellValue('E4', 'Generado:');
        $sheet->setCellValue('F4', now()->format('d/m/Y H:i'));
        $sheet->getStyle('A4:E4')->applyFromArray(['font' => ['bold' => true]]);

        // Resumen General
        $sheet->mergeCells('A6:F6');
        $sheet->setCellValue('A6', 'RESUMEN GENERAL');
        $sheet->getStyle('A6:F6')->applyFromArray($subHeaderStyle);
        $sheet->getRowDimension(6)->setRowHeight(25);

        $sheet->setCellValue('A7', 'Total Gestiones');
        $sheet->setCellValue('B7', $informes->count());
        $sheet->setCellValue('C7', 'Tiempo Total');
        $sheet->setCellValue('D7', $this->formatearTiempo($informes->sum('tiempo_minutos')));
        $sheet->getStyle('A7:D7')->applyFromArray($cellStyle);
        $sheet->getStyle('A7:A7')->applyFromArray(['font' => ['bold' => true]]);
        $sheet->getStyle('C7:C7')->applyFromArray(['font' => ['bold' => true]]);
        $sheet->getStyle('B7:B7')->applyFromArray(['font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '4F46E5']]]);
        $sheet->getStyle('D7:D7')->applyFromArray(['font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '4F46E5']]]);

        $row = 9;

        // Resumen por Área
        if (!empty($resumenPorArea)) {
            $sheet->mergeCells("A{$row}:C{$row}");
            $sheet->setCellValue("A{$row}", 'GESTIONES POR ÁREA DE PRÁCTICA');
            $sheet->getStyle("A{$row}:C{$row}")->applyFromArray($subHeaderStyle);
            $row++;

            $sheet->setCellValue("A{$row}", 'Área');
            $sheet->setCellValue("B{$row}", 'Cantidad');
            $sheet->setCellValue("C{$row}", 'Tiempo');
            $sheet->getStyle("A{$row}:C{$row}")->applyFromArray($tableHeaderStyle);
            $row++;

            foreach ($resumenPorArea as $item) {
                $sheet->setCellValue("A{$row}", $item['area']);
                $sheet->setCellValue("B{$row}", $item['cantidad']);
                $sheet->setCellValue("C{$row}", $item['tiempo_formateado']);
                $sheet->getStyle("A{$row}:C{$row}")->applyFromArray($cellStyle);
                $sheet->getStyle("B{$row}")->applyFromArray(['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);
                $row++;
            }
            $row++;
        }

        // Resumen por Tipo
        if (!empty($resumenPorTipo)) {
            $sheet->mergeCells("A{$row}:C{$row}");
            $sheet->setCellValue("A{$row}", 'GESTIONES POR TIPO');
            $sheet->getStyle("A{$row}:C{$row}")->applyFromArray($subHeaderStyle);
            $row++;

            $sheet->setCellValue("A{$row}", 'Tipo');
            $sheet->setCellValue("B{$row}", 'Cantidad');
            $sheet->setCellValue("C{$row}", 'Tiempo');
            $sheet->getStyle("A{$row}:C{$row}")->applyFromArray($tableHeaderStyle);
            $row++;

            foreach ($resumenPorTipo as $item) {
                $sheet->setCellValue("A{$row}", $item['tipo']);
                $sheet->setCellValue("B{$row}", $item['cantidad']);
                $sheet->setCellValue("C{$row}", $item['tiempo_formateado']);
                $sheet->getStyle("A{$row}:C{$row}")->applyFromArray($cellStyle);
                $sheet->getStyle("B{$row}")->applyFromArray(['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);
                $row++;
            }
            $row++;
        }

        // Resumen por Estado
        if (!empty($resumenPorEstado)) {
            $sheet->mergeCells("A{$row}:B{$row}");
            $sheet->setCellValue("A{$row}", 'GESTIONES POR ESTADO');
            $sheet->getStyle("A{$row}:B{$row}")->applyFromArray($subHeaderStyle);
            $row++;

            $sheet->setCellValue("A{$row}", 'Estado');
            $sheet->setCellValue("B{$row}", 'Cantidad');
            $sheet->getStyle("A{$row}:B{$row}")->applyFromArray($tableHeaderStyle);
            $row++;

            foreach ($resumenPorEstado as $item) {
                $sheet->setCellValue("A{$row}", $item['estado']);
                $sheet->setCellValue("B{$row}", $item['cantidad']);
                $sheet->getStyle("A{$row}:B{$row}")->applyFromArray($cellStyle);
                $sheet->getStyle("B{$row}")->applyFromArray(['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);
                $row++;
            }
            $row++;
        }

        // Resumen por Mes (si es anual)
        if ($resumenPorMes && !empty($resumenPorMes)) {
            $sheet->mergeCells("A{$row}:C{$row}");
            $sheet->setCellValue("A{$row}", 'GESTIONES POR MES');
            $sheet->getStyle("A{$row}:C{$row}")->applyFromArray($subHeaderStyle);
            $row++;

            $sheet->setCellValue("A{$row}", 'Mes');
            $sheet->setCellValue("B{$row}", 'Cantidad');
            $sheet->setCellValue("C{$row}", 'Tiempo');
            $sheet->getStyle("A{$row}:C{$row}")->applyFromArray($tableHeaderStyle);
            $row++;

            foreach ($resumenPorMes as $item) {
                $sheet->setCellValue("A{$row}", $item['mes']);
                $sheet->setCellValue("B{$row}", $item['cantidad']);
                $sheet->setCellValue("C{$row}", $item['tiempo_formateado']);
                $sheet->getStyle("A{$row}:C{$row}")->applyFromArray($cellStyle);
                $sheet->getStyle("B{$row}")->applyFromArray(['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);
                $row++;
            }
        }

        // Ajustar anchos
        $sheet->getColumnDimension('A')->setWidth(35);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(20);
        $sheet->getColumnDimension('E')->setWidth(15);
        $sheet->getColumnDimension('F')->setWidth(20);
    }

    protected function crearHojaDetalle(Spreadsheet $spreadsheet, Collection $informes): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Detalle');

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F2937']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ];

        $cellStyle = [
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ];

        // Encabezados
        $headers = ['#', 'Mes', 'Área de Práctica', 'Tipo de Gestión', 'Subtipo', 'Descripción', 'Estado', 'Tiempo', 'Observación', 'Fecha'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }
        $sheet->getStyle('A1:J1')->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(25);

        // Datos
        $row = 2;
        $num = 1;
        foreach ($informes as $informe) {
            $sheet->setCellValue("A{$row}", $num);
            $sheet->setCellValue("B{$row}", $informe->mes_texto);
            $sheet->setCellValue("C{$row}", $informe->area_practica_texto);
            $sheet->setCellValue("D{$row}", $informe->tipo_gestion_texto);
            $sheet->setCellValue("E{$row}", $informe->subtipo_texto ?? '-');
            $sheet->setCellValue("F{$row}", $informe->descripcion);
            $sheet->setCellValue("G{$row}", $informe->estado_texto);
            $sheet->setCellValue("H{$row}", $informe->tiempo_formateado);
            $sheet->setCellValue("I{$row}", $informe->observacion ?? '-');
            $sheet->setCellValue("J{$row}", $informe->created_at->format('d/m/Y'));

            $sheet->getStyle("A{$row}:J{$row}")->applyFromArray($cellStyle);

            // Color alternado
            if ($row % 2 === 0) {
                $sheet->getStyle("A{$row}:J{$row}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F9FAFB']],
                ]);
            }

            // Color por estado
            $estadoColor = match($informe->estado) {
                'entregado' => 'D1FAE5',
                'pendiente' => 'FEF3C7',
                'en_proceso' => 'DBEAFE',
                default => 'FFFFFF',
            };
            $sheet->getStyle("G{$row}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $estadoColor]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);

            $row++;
            $num++;
        }

        // Ajustar anchos
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(12);
        $sheet->getColumnDimension('C')->setWidth(20);
        $sheet->getColumnDimension('D')->setWidth(20);
        $sheet->getColumnDimension('E')->setWidth(15);
        $sheet->getColumnDimension('F')->setWidth(40);
        $sheet->getColumnDimension('G')->setWidth(12);
        $sheet->getColumnDimension('H')->setWidth(10);
        $sheet->getColumnDimension('I')->setWidth(25);
        $sheet->getColumnDimension('J')->setWidth(12);

        // Congelar primera fila
        $sheet->freezePane('A2');
    }

    protected function crearHojaDatosGraficas(Spreadsheet $spreadsheet, array $resumenPorArea, array $resumenPorMes): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Gráficas');

        // Datos para gráfica de área
        $sheet->setCellValue('A1', 'Área de Práctica');
        $sheet->setCellValue('B1', 'Cantidad');
        $row = 2;
        foreach ($resumenPorArea as $item) {
            $sheet->setCellValue("A{$row}", $item['area']);
            $sheet->setCellValue("B{$row}", $item['cantidad']);
            $row++;
        }
        $areaEndRow = $row - 1;

        // Crear gráfica de pastel para áreas
        $dataSeriesLabels = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Gráficas!$B$1', null, 1)];
        $xAxisTickValues = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Gráficas!$A$2:$A$' . $areaEndRow, null, count($resumenPorArea))];
        $dataSeriesValues = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Gráficas!$B$2:$B$' . $areaEndRow, null, count($resumenPorArea))];

        $series = new DataSeries(
            DataSeries::TYPE_PIECHART,
            null,
            range(0, count($dataSeriesValues) - 1),
            $dataSeriesLabels,
            $xAxisTickValues,
            $dataSeriesValues
        );

        $plotArea = new PlotArea(null, [$series]);
        $legend = new Legend(Legend::POSITION_RIGHT, null, false);
        $title = new Title('Gestiones por Área');

        $chart = new Chart(
            'chart1',
            $title,
            $legend,
            $plotArea
        );

        $chart->setTopLeftPosition('D1');
        $chart->setBottomRightPosition('L15');
        $sheet->addChart($chart);

        // Datos para gráfica de meses
        $row = $areaEndRow + 3;
        $mesStartRow = $row;
        $sheet->setCellValue("A{$row}", 'Mes');
        $sheet->setCellValue("B{$row}", 'Cantidad');
        $row++;
        foreach ($resumenPorMes as $item) {
            $sheet->setCellValue("A{$row}", $item['mes']);
            $sheet->setCellValue("B{$row}", $item['cantidad']);
            $row++;
        }
        $mesEndRow = $row - 1;

        // Crear gráfica de barras para meses
        $dataSeriesLabels2 = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Gráficas!$B$' . $mesStartRow, null, 1)];
        $xAxisTickValues2 = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Gráficas!$A$' . ($mesStartRow + 1) . ':$A$' . $mesEndRow, null, count($resumenPorMes))];
        $dataSeriesValues2 = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Gráficas!$B$' . ($mesStartRow + 1) . ':$B$' . $mesEndRow, null, count($resumenPorMes))];

        $series2 = new DataSeries(
            DataSeries::TYPE_BARCHART,
            DataSeries::GROUPING_STANDARD,
            range(0, count($dataSeriesValues2) - 1),
            $dataSeriesLabels2,
            $xAxisTickValues2,
            $dataSeriesValues2
        );
        $series2->setPlotDirection(DataSeries::DIRECTION_COL);

        $plotArea2 = new PlotArea(null, [$series2]);
        $legend2 = new Legend(Legend::POSITION_BOTTOM, null, false);
        $title2 = new Title('Gestiones por Mes');

        $chart2 = new Chart(
            'chart2',
            $title2,
            $legend2,
            $plotArea2
        );

        $chart2->setTopLeftPosition('D17');
        $chart2->setBottomRightPosition('L32');
        $sheet->addChart($chart2);

        // Ajustar anchos
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(12);
    }

    public static function getMeses(): array
    {
        return self::MESES;
    }

    public static function getAniosDisponibles(): array
    {
        $anioActual = now()->year;
        $anios = [];

        for ($i = $anioActual; $i >= $anioActual - 5; $i--) {
            $anios[$i] = (string) $i;
        }

        return $anios;
    }
}
