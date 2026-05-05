<?php

namespace App\Services;

use App\Models\ReglamentoInterno;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\IOFactory;
use Smalot\PdfParser\Parser as PdfParser;

class ReglamentoInternoService
{
    /**
     * Procesa un archivo (.docx o .pdf), extrae el texto y lo guarda en BD.
     *
     * La extracción de texto es opcional — si falla, el registro se crea de
     * todas formas con activo=true para que la empresa quede con RIT activo.
     */
    public function procesarDocumento(string $rutaArchivo, int $empresaId, string $nombreOriginal): ReglamentoInterno
    {
        $texto = '';

        try {
            $extension = strtolower(pathinfo($rutaArchivo, PATHINFO_EXTENSION));

            $texto = match ($extension) {
                'pdf'  => $this->extraerTextoPdf($rutaArchivo),
                default => $this->extraerTextoDocx($rutaArchivo),
            };
        } catch (\Exception $e) {
            // La extracción de texto falla con gracia — el RIT aún se registra
            Log::warning('ReglamentoInternoService: no se pudo extraer texto del documento', [
                'empresa_id' => $empresaId,
                'archivo'    => basename($rutaArchivo),
                'error'      => $e->getMessage(),
            ]);
        }

        $reglamento = ReglamentoInterno::updateOrCreate(
            ['empresa_id' => $empresaId],
            [
                'nombre'         => $nombreOriginal,
                'texto_completo' => $texto ?: null,
                'activo'         => true,
                'fuente'         => 'subido',
            ]
        );

        Log::info('ReglamentoInternoService: documento registrado', [
            'empresa_id' => $empresaId,
            'nombre'     => $nombreOriginal,
            'chars'      => strlen($texto),
        ]);

        return $reglamento;
    }

    /**
     * Devuelve el texto completo del reglamento activo para una empresa, o null si no existe.
     */
    public function getTextoReglamento(int $empresaId): ?string
    {
        $reglamento = ReglamentoInterno::where('empresa_id', $empresaId)
            ->where('activo', true)
            ->latest()
            ->first();

        return $reglamento?->texto_completo;
    }

    /**
     * Extrae texto plano de un .docx usando PhpWord.
     */
    private function extraerTextoDocx(string $rutaArchivo): string
    {
        $phpWord = IOFactory::load($rutaArchivo);
        $lineas  = [];

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                $lineas[] = $this->elementoATexto($element);
            }
        }

        return trim(implode("\n", array_filter($lineas)));
    }

    /**
     * Extrae texto plano de un .pdf usando smalot/pdfparser.
     */
    private function extraerTextoPdf(string $rutaArchivo): string
    {
        $parser = new PdfParser();
        $pdf    = $parser->parseFile($rutaArchivo);

        return trim($pdf->getText());
    }

    /**
     * Convierte un elemento PhpWord a texto plano recursivamente.
     */
    private function elementoATexto(mixed $element): string
    {
        if ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
            $partes = [];
            foreach ($element->getElements() as $child) {
                $partes[] = $this->elementoATexto($child);
            }
            return implode('', $partes);
        }

        if ($element instanceof \PhpOffice\PhpWord\Element\Text) {
            return $element->getText();
        }

        if ($element instanceof \PhpOffice\PhpWord\Element\Paragraph) {
            $partes = [];
            foreach ($element->getElements() as $child) {
                $partes[] = $this->elementoATexto($child);
            }
            return implode('', $partes);
        }

        if ($element instanceof \PhpOffice\PhpWord\Element\Table) {
            $filas = [];
            foreach ($element->getRows() as $row) {
                $celdas = [];
                foreach ($row->getCells() as $cell) {
                    $contenido = [];
                    foreach ($cell->getElements() as $child) {
                        $contenido[] = $this->elementoATexto($child);
                    }
                    $celdas[] = implode(' ', $contenido);
                }
                $filas[] = implode(' | ', $celdas);
            }
            return implode("\n", $filas);
        }

        if ($element instanceof \PhpOffice\PhpWord\Element\ListItem) {
            return '- ' . $this->elementoATexto($element->getTextObject());
        }

        return '';
    }
}
