<?php

namespace App\Jobs;

use App\Mail\CorreoOficial;
use App\Models\CorreoEnviado;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EnviarCorreoOficialJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;
    public int $tries   = 2;

    public function __construct(
        public readonly CorreoEnviado $correo,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $correo = $this->correo->fresh();

        if (!$correo) {
            Log::warning('EnviarCorreoOficialJob: registro no encontrado', [
                'correo_id' => $this->correo->id,
            ]);
            return;
        }

        // Resolve empresa by priority: explicit > trabajador's > proceso's
        $empresaId = $correo->empresa_id
            ?? $correo->trabajador?->empresa_id
            ?? $correo->proceso?->empresa_id;

        $empresa  = $empresaId ? \App\Models\Empresa::find($empresaId) : null;
        $viaGmail = false;

        if ($empresa && $empresa->tieneGmailConectado()) {
            try {
                $accessToken = app(\App\Services\GoogleOAuthService::class)->getValidAccessToken($empresa);
                app(\App\Services\GmailApiService::class)->send($correo, $accessToken);
                $viaGmail = true;
            } catch (\Throwable $e) {
                Log::warning('Gmail API falló, usando SMTP como fallback', [
                    'correo_id' => $correo->id,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        if (!$viaGmail) {
            Mail::to($correo->email_destinatario, $correo->destinatario_nombre)
                ->send(new CorreoOficial($correo));
        }

        $correo->update(['enviado_en' => now('America/Bogota')]);

        Log::info('Correo oficial enviado', [
            'correo_id'    => $correo->id,
            'destinatario' => $correo->email_destinatario,
            'via'          => $viaGmail ? 'gmail_oauth' : 'smtp',
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('EnviarCorreoOficialJob falló', [
            'correo_id' => $this->correo->id,
            'error'     => $e->getMessage(),
        ]);
    }
}
