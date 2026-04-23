<?php

namespace App\Services;

use App\Models\Empresa;
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

        $prompt = $this->construirPrompt($respuestas, $empresa);

        Log::info('RITGeneratorService: generando texto con Gemini', [
            'empresa_id' => $empresa->id,
            'model'      => $model,
        ]);

        $response = Http::withHeaders(['Content-Type' => 'application/json'])
            ->timeout(90)
            ->post($url, [
                'contents' => [
                    ['parts' => [['text' => $prompt]]],
                ],
                'generationConfig' => [
                    'temperature'     => 0.3,
                    'maxOutputTokens' => 16384,
                    'topP'            => 0.95,
                    'topK'            => 40,
                ],
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Error en API Gemini: ' . $response->body());
        }

        $data = $response->json();

        if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            throw new \RuntimeException('Respuesta de Gemini sin contenido válido');
        }

        return trim($data['candidates'][0]['content']['parts'][0]['text']);
    }

    /**
     * Genera el documento Word (.docx) con el texto del RIT.
     * Retorna la ruta relativa dentro de storage/app/private/.
     */
    public function generarDocumentoWord(string $textoRIT, Empresa $empresa): string
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

        // Título
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

        // Parsear el texto por líneas y agregar al documento
        $lineas = explode("\n", $textoRIT);
        foreach ($lineas as $linea) {
            $linea = rtrim($linea);

            if ($linea === '') {
                $section->addTextBreak(1);
                continue;
            }

            // Detectar títulos de capítulo (CAPÍTULO, ARTÍCULO, números romanos al inicio)
            if (preg_match('/^(CAPÍTULO|CAPÍTULO\s+[IVXLC]+|ARTÍCULO\s+\d+|ART\.\s+\d+)/ui', $linea)) {
                $section->addText(
                    $linea,
                    ['bold' => true, 'size' => 12, 'name' => 'Times New Roman'],
                    ['spaceAfter' => 80, 'spaceBefore' => 120]
                );
            } else {
                $section->addText(
                    $linea,
                    ['size' => 12, 'name' => 'Times New Roman'],
                    ['spaceAfter' => 60, 'lineHeight' => 1.5]
                );
            }
        }

        // Guardar archivo
        $directorio = "private/rits/{$empresa->id}";
        Storage::makeDirectory($directorio);

        $rutaRelativa = "{$directorio}/reglamento.docx";
        $rutaAbsoluta = storage_path("app/{$rutaRelativa}");

        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($rutaAbsoluta);

        Log::info('RITGeneratorService: documento Word generado', [
            'empresa_id' => $empresa->id,
            'ruta'       => $rutaRelativa,
        ]);

        return $rutaRelativa;
    }

    private function construirPrompt(array $respuestas, Empresa $empresa): string
    {
        $razonSocial = $empresa->razon_social;
        $nit         = $empresa->nit;

        $infoEmpresa = '';
        foreach ($respuestas as $seccion => $preguntas) {
            if (is_array($preguntas)) {
                $infoEmpresa .= "\n[{$seccion}]\n";
                foreach ($preguntas as $pregunta => $respuesta) {
                    if (!empty($respuesta)) {
                        $infoEmpresa .= "- {$pregunta}: {$respuesta}\n";
                    }
                }
            } elseif (!empty($preguntas)) {
                $infoEmpresa .= "- {$seccion}: {$preguntas}\n";
            }
        }

        return <<<PROMPT
Eres un abogado laboral colombiano experto en reglamentos internos de trabajo.

Redacta el Reglamento Interno de Trabajo de {$razonSocial} (NIT: {$nit}) con cumplimiento estricto del Artículo 105 y siguientes del Código Sustantivo del Trabajo de Colombia.

INSTRUCCIONES:
- Usa lenguaje formal y técnico-jurídico
- Numera cada artículo de forma consecutiva
- Incluye TODOS los capítulos obligatorios del CST
- Incluye capítulo sobre Política de Prevención de Acoso Sexual según la Ley 2365 de 2024
- Redacta de manera lista para presentar ante el Ministerio del Trabajo
- Si alguna información no fue proporcionada, usa valores razonables y típicos para una empresa colombiana
- NO incluyas comentarios ni aclaraciones fuera del texto del reglamento

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

INFORMACIÓN DE LA EMPRESA PROPORCIONADA POR EL ADMINISTRADOR:
{$infoEmpresa}

Redacta el Reglamento Interno de Trabajo completo:
PROMPT;
    }
}
