<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RitAceptado extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $nombreTrabajador,
        public readonly string $nombreEmpresa,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Reglamento Interno de Trabajo - {$this->nombreEmpresa}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.rit-aceptado',
        );
    }
}
