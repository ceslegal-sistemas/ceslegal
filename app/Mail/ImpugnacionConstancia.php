<?php

namespace App\Mail;

use App\Models\Impugnacion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Constancia que recibe el trabajador al presentar su impugnación. */
class ImpugnacionConstancia extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Impugnacion $impugnacion) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Recibimos su impugnación {$this->impugnacion->radicado()} - Proceso {$this->impugnacion->proceso->codigo}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.impugnacion-constancia', with: [
            'impugnacion' => $this->impugnacion,
            'proceso' => $this->impugnacion->proceso,
            'trabajador' => $this->impugnacion->proceso->trabajador,
            'empresa' => $this->impugnacion->proceso->empresa,
        ]);
    }
}
