<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TtsController extends Controller
{
    public function __invoke(Request $request)
    {
        $validated = $request->validate(['texto' => 'required|string|max:600']);

        $apiKey  = config('services.elevenlabs.api_key');
        $voiceId = config('services.elevenlabs.voice_id', 'pNInz6obpgDQGcFmaJgB');

        if (empty($apiKey)) {
            Log::warning('TtsController: ELEVENLABS_API_KEY no configurado');
            abort(503, 'TTS not configured');
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'xi-api-key'   => $apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post("https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}", [
                    'text'          => $validated['texto'],
                    'model_id'      => 'eleven_multilingual_v2',
                    'output_format' => 'mp3_44100_128',
                    'voice_settings' => [
                        'stability'         => 0.45,
                        'similarity_boost'  => 0.80,
                        'style'             => 0.15,
                        'use_speaker_boost' => true,
                    ],
                ]);

            if (! $response->successful()) {
                Log::error('TtsController: ElevenLabs error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return response()->json(['error' => 'ElevenLabs: ' . $response->status()], 500);
            }

            return response($response->body(), 200, [
                'Content-Type'  => 'audio/mpeg',
                'Cache-Control' => 'no-store',
            ]);
        } catch (\Exception $e) {
            Log::error('TtsController: excepción', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
