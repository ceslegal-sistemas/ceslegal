<?php

namespace App\Services;

use App\Models\ReglamentoInterno;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\IOFactory;

class ReglamentoInternoService
{
    /**
     * Procesa un archivo .docx, extrae el texto y lo guarda en BD.
     */
    public function procesarDocumento(string $rutaArchivo, int $empresaId, string $nombreOriginal): ReglamentoInterno
    {
        $texto = $this->extraerTexto($rutaArchivo);

        $reglamento = ReglamentoInterno::updateOrCreate(
            ['empresa_id' => $empresaId],
            [
                'nombre'         => $nombreOriginal,
                'texto_completo' => $texto,
                'activo'         => true,
            ]
        );

        Log::info('Reglamento interno procesado', [
            'empresa_id'  => $empresaId,
            'nombre'      => $nombreOriginal,
            'chars'       => strlen($texto),
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
     * Extrae el texto plano de un archivo .docx usando PhpWord.
     */
    private function extraerTexto(string $rutaArchivo): string
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
