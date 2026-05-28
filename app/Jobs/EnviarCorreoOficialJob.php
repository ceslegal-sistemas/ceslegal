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

        Mail::to($correo->email_destinatario, $correo->destinatario_nombre)
            ->send(new CorreoOficial($correo));

        $correo->update(['enviado_en' => now('America/Bogota')]);

        Log::info('Correo oficial enviado', [
            'correo_id'    => $correo->id,
            'destinatario' => $correo->email_destinatario,
            'asunto'       => $correo->asunto,
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
