<?php

namespace App\Mail;

use App\Models\ProcesoDisciplinario;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Reenvío del enlace personal de impugnación al trabajador (desde el panel). */
class ImpugnacionEnlace extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly ProcesoDisciplinario $proceso) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Enlace para presentar su impugnación - Proceso {$this->proceso->codigo}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.impugnacion-enlace', with: [
            'proceso' => $this->proceso,
            'trabajador' => $this->proceso->trabajador,
            'empresa' => $this->proceso->empresa,
            'url' => $this->proceso->urlImpugnacion(),
            'limite' => $this->proceso->limiteImpugnacion(),
        ]);
    }
}
