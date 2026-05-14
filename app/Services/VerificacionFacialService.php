<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VerificacionFacialService
{
    /**
     * Capa 2: Gemini Vision — Verifica calidad de la foto y ausencia de obstrucciones.
     *
     * Devuelve ['ok' => true] si la foto es válida.
     * Devuelve ['ok' => false, 'motivo' => '...'] con la razón de rechazo.
     * En caso de error de red/API, devuelve ['ok' => true] para no bloquear al trabajador (fail-open).
     */
    public function validarCalidadFoto(string $base64): array
    {
        $apiKey    = config('services.ia.gemini.api_key');
        $imageData = preg_replace('/^data:image\/\w+;base64,/', '', $base64);

        $prompt = 'Analiza esta foto de verificación de identidad para un proceso legal. '
            . 'Responde SOLO con JSON válido (sin markdown, sin texto adicional) en este formato exacto: '
            . '{"ok": true/false, "motivo": "string o null"}. '
            . 'Criterios de RECHAZO (ok=false): '
            . '(1) Cara no visible, fuera de encuadre o de espaldas. '
            . '(2) Obstrucciones: gafas oscuras de sol, tapabocas/mascarilla, gorra o sombrero que tape la frente, bufanda en el rostro. '
            . '(3) Imagen borrosa, muy oscura o sobreexpuesta. '
            . '(4) Más de una persona visible en la foto. '
            . '(5) Es una foto de pantalla, foto de foto, o imagen impresa. '
            . 'Si la foto es válida (rostro visible, sin obstrucciones, buena iluminación), responde {"ok": true, "motivo": null}. '
            . 'El campo motivo debe estar en español, máx 120 caracteres, dirigido al trabajador (segunda persona).';

        try {
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(30)
                ->post(
                    'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $apiKey,
                    [
                        'contents' => [[
                            'parts' => [
                                ['text' => $prompt],
                                ['inline_data' => ['mime_type' => 'image/jpeg', 'data' => $imageData]],
                            ],
                        ]],
                        'generationConfig' => [
                            'temperature'    => 0.1,
                            'maxOutputTokens' => 150,
                            'thinkingConfig' => ['thinkingBudget' => 0],
                        ],
                    ]
                );

            if (!$response->successful()) {
                Log::warning('VerificacionFacial: Gemini no disponible', [
                    'status' => $response->status(),
                ]);
                return ['ok' => true, 'motivo' => null]; // fail-open
            }

            $text = '';
            foreach (array_reverse($response->json('candidates.0.content.parts', [])) as $part) {
                if (empty($part['thought']) && isset($part['text']) && $part['text'] !== '') {
                    $text = trim($part['text']);
                    break;
                }
            }

            // Quitar markdown fences si Gemini los incluyó
            $text = preg_replace('/^```json?\s*|\s*```$/', '', $text);
            $json = json_decode($text, true);

            if (!is_array($json) || !isset($json['ok'])) {
                Log::warning('VerificacionFacial: JSON inválido de Gemini', ['raw' => $text]);
                return ['ok' => true, 'motivo' => null]; // fail-open
            }

            return $json;
        } catch (\Exception $e) {
            Log::error('VerificacionFacial: excepción Gemini', ['error' => $e->getMessage()]);
            return ['ok' => true, 'motivo' => null]; // fail-open
        }
    }

    /**
     * Pre-captura: detecta gafas, tapabocas, gorras u obstrucciones usando Gemini Vision.
     * Prompt ultra-específico solo para accesorios — diferente al de calidad de foto (Capa 2).
     * Fail-open en errores para no bloquear al trabajador.
     */
    public function detectarAccesorios(string $base64): array
    {
        $apiKey    = config('services.ia.gemini.api_key');
        $imageData = preg_replace('/^data:image\/\w+;base64,/', '', $base64);

        $prompt = 'Observa esta imagen de cámara web en tiempo real. '
            . 'Tu única tarea: detectar si la persona lleva puestos accesorios que dificulten la identificación facial. '
            . 'Responde SOLO con JSON válido (sin markdown): {"accesorio": true/false, "motivo": "string o null"}. '
            . 'Reglas estrictas — responde accesorio=true si: '
            . '(1) Lleva CUALQUIER tipo de gafas: de sol, transparentes, de lectura, de cualquier color o forma. '
            . '(2) Lleva tapabocas, mascarilla o cualquier cosa cubriendo boca/nariz. '
            . '(3) Lleva gorra, sombrero, capucha u otro objeto que tape la frente o parte del rostro. '
            . '(4) Una mano u objeto cubre parte del rostro. '
            . 'Si detectas GAFAS (cualquier tipo), motivo="Por favor retírese las gafas para verificar su identidad correctamente." '
            . 'Si detectas TAPABOCAS, motivo="Por favor retírese el tapabocas para verificar su identidad correctamente." '
            . 'Si detectas GORRA u otro objeto, motivo="Por favor retire el accesorio que cubre su rostro." '
            . 'Si el rostro está completamente libre de accesorios, responde {"accesorio": false, "motivo": null}. '
            . 'Sé muy estricto: ante la mínima duda sobre gafas, responde accesorio=true.';

        try {
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(15)
                ->post(
                    'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $apiKey,
                    [
                        'contents' => [[
                            'parts' => [
                                ['text' => $prompt],
                                ['inline_data' => ['mime_type' => 'image/jpeg', 'data' => $imageData]],
                            ],
                        ]],
                        'generationConfig' => [
                            'temperature'     => 0.1,
                            'maxOutputTokens' => 80,
                            'thinkingConfig'  => ['thinkingBudget' => 0],
                        ],
                    ]
                );

            if (!$response->successful()) {
                Log::warning('VerificacionFacial: detectarAccesorios Gemini no disponible', [
                    'status' => $response->status(),
                ]);
                return ['ok' => true, 'motivo' => null]; // fail-open
            }

            $text = '';
            foreach (array_reverse($response->json('candidates.0.content.parts', [])) as $part) {
                if (empty($part['thought']) && isset($part['text']) && $part['text'] !== '') {
                    $text = trim($part['text']);
                    break;
                }
            }

            $text = preg_replace('/^```json?\s*|\s*```$/', '', $text);
            $json = json_decode($text, true);

            Log::info('VerificacionFacial: detectarAccesorios Gemini', ['raw' => $text, 'parsed' => $json]);

            if (!is_array($json) || !isset($json['accesorio'])) {
                return ['ok' => true, 'motivo' => null]; // fail-open
            }

            if ($json['accesorio']) {
                return [
                    'ok'     => false,
                    'motivo' => $json['motivo'] ?? 'Por favor retire el accesorio que cubre su rostro.',
                ];
            }

            return ['ok' => true, 'motivo' => null];

        } catch (\Exception $e) {
            Log::warning('VerificacionFacial: detectarAccesorios excepción', ['error' => $e->getMessage()]);
            return ['ok' => true, 'motivo' => null]; // fail-open
        }
    }

    /**
     * Capa 3: AWS Rekognition — Compara la selfie del trabajador contra la foto de referencia.
     *
     * @param  string $referenciaBytes  Contenido binario de la foto de referencia (Storage::get()).
     * @param  string $selfieBase64     Selfie en base64 (con o sin prefijo data:image/...).
     * @return array ['ok' => bool, 'motivo' => string|null, 'similitud' => float|null]
     */
    public function compararRostros(string $referenciaBytes, string $selfieBase64): array
    {
        $selfieBytes = base64_decode(
            preg_replace('/^data:image\/\w+;base64,/', '', $selfieBase64)
        );

        $client = new \Aws\Rekognition\RekognitionClient([
            'version'     => 'latest',
            'region'      => config('services.rekognition.region', 'us-east-1'),
            'credentials' => [
                'key'    => config('services.rekognition.key'),
                'secret' => config('services.rekognition.secret'),
            ],
        ]);

        try {
            $result = $client->compareFaces([
                'SourceImage'         => ['Bytes' => $referenciaBytes],
                'TargetImage'         => ['Bytes' => $selfieBytes],
                'SimilarityThreshold' => 80.0,
            ]);

            $matches    = $result['FaceMatches'] ?? [];
            $similarity = !empty($matches) ? (float) $matches[0]['Similarity'] : 0.0;

            Log::info('VerificacionFacial: Rekognition comparación', [
                'similarity' => $similarity,
            ]);

            if ($similarity >= 90.0) {
                return ['ok' => true, 'similitud' => round($similarity, 1), 'motivo' => null];
            }

            return [
                'ok'        => false,
                'similitud' => round($similarity, 1),
                'motivo'    => 'No fue posible confirmar que usted es el trabajador registrado. '
                    . 'Asegúrese de tener buena iluminación y que su rostro esté bien visible.',
            ];
        } catch (\Aws\Rekognition\Exception\RekognitionException $e) {
            Log::error('VerificacionFacial: error Rekognition', [
                'code'    => $e->getAwsErrorCode(),
                'message' => $e->getMessage(),
            ]);
            // fail-open: si AWS no está disponible, no bloquear al trabajador
            return ['ok' => true, 'similitud' => null, 'motivo' => null];
        } catch (\Exception $e) {
            Log::error('VerificacionFacial: excepción genérica Rekognition', [
                'error' => $e->getMessage(),
            ]);
            return ['ok' => true, 'similitud' => null, 'motivo' => null];
        }
    }
}
