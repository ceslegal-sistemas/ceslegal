<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TranscribeController extends Controller
{
    public function __invoke(Request $request)
    {
        $validated = $request->validate([
            'audio' => 'required|string',
            'tipo'  => 'sometimes|string',
        ]);

        $apiKey = config('services.ia.gemini.api_key');
        $url    = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$apiKey}";

        // Gemini solo acepta el tipo base, sin parámetros de codec (e.g. 'audio/webm;codecs=opus' → 'audio/webm')
        $mime = explode(';', $validated['tipo'] ?? 'audio/webm')[0];

        try {
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(25)
                ->post($url, [
                    'contents' => [[
                        'parts' => [
                            ['text' => 'Transcribe el siguiente audio de voz en español colombiano. Devuelve ÚNICAMENTE el texto transcrito, sin comentarios ni puntuación extra.'],
                            ['inline_data' => [
                                'mime_type' => $mime,
                                'data'      => $validated['audio'],
                            ]],
                        ],
                    ]],
                    'generationConfig' => [
                        'temperature'     => 0.1,
                        'maxOutputTokens' => 500,
                    ],
                ]);

            if (! $response->successful()) {
                Log::error('TranscribeController: Gemini error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return response()->json(['error' => 'Transcripción fallida'], 500);
            }

            $texto = $response->json('candidates.0.content.parts.0.text') ?? '';
            return response()->json(['texto' => trim($texto)]);
        } catch (\Exception $e) {
            Log::error('TranscribeController: excepción', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
