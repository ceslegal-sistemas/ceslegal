<?php

namespace App\Mail;

use App\Models\CorreoEnviado;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class CorreoOficial extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly CorreoEnviado $correo,
    ) {}

    public function envelope(): Envelope
    {
        $cc = [];
        foreach (($this->correo->email_cc ?? []) as $emailCc) {
            if (filter_var($emailCc, FILTER_VALIDATE_EMAIL)) {
                $cc[] = $emailCc;
            }
        }

        $prefijo = match ($this->correo->prioridad) {
            'urgente' => '[URGENTE] ',
            'alta'    => '[IMPORTANTE] ',
            default   => '',
        };

        // El nombre del remitente es la razón social del cliente, no "LUPE Legal".
        // (El fallback SMTP conserva la dirección configurada; solo cambia el nombre visible.)
        $empresa = $this->correo->empresa
            ?? $this->correo->trabajador?->empresa
            ?? $this->correo->proceso?->empresa;
        $nombreRemitente = $empresa?->razon_social ?? $empresa?->nombre_completo;

        return new Envelope(
            from: $nombreRemitente
                ? new Address(config('mail.from.address'), $nombreRemitente)
                : null,
            subject: $prefijo . $this->correo->asunto,
            cc: $cc,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.correo-oficial',
            with: [
                'correo'      => $this->correo,
                'trackingUrl' => route('correo.tracking.pixel', $this->correo->token),
            ],
        );
    }

    public function attachments(): array
    {
        $attachments = [];

        foreach (($this->correo->adjuntos ?? []) as $ruta) {
            if (Storage::disk('local')->exists($ruta)) {
                $attachments[] = Attachment::fromStorageDisk('local', $ruta)
                    ->as(basename($ruta));
            }
        }

        return $attachments;
    }
}
