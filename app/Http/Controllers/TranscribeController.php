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

        $apiKey = config('services.elevenlabs.api_key');
        $mime   = explode(';', $validated['tipo'] ?? 'audio/webm')[0];

        // Extensión según MIME
        $ext = match ($mime) {
            'audio/mp4'  => 'mp4',
            'audio/ogg'  => 'ogg',
            'audio/wav'  => 'wav',
            'audio/mpeg' => 'mp3',
            default      => 'webm',
        };

        try {
            $response = Http::withHeaders(['xi-api-key' => $apiKey])
                ->timeout(25)
                ->attach('file', base64_decode($validated['audio']), "audio.{$ext}", ['Content-Type' => $mime])
                ->post('https://api.elevenlabs.io/v1/speech-to-text', [
                    'model_id'      => 'scribe_v1',
                    'language_code' => 'es',
                ]);

            if (! $response->successful()) {
                Log::error('TranscribeController: ElevenLabs error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return response()->json(['error' => 'Transcripción fallida', 'detail' => $response->body()], 500);
            }

            $texto = $response->json('text') ?? '';
            return response()->json(['texto' => trim($texto)]);
        } catch (\Exception $e) {
            Log::error('TranscribeController: excepción', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
