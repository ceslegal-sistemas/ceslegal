<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpDescargos extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $codigo,
        public readonly string $nombreTrabajador,
        public readonly string $procesoCodigo,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Código de verificación — {$this->procesoCodigo}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.otp-descargos',
        );
    }
}
