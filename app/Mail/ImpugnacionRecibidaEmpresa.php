<?php

namespace App\Mail;

use App\Models\Impugnacion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Aviso a la empresa (y al abogado) de que el trabajador presentó una impugnación. */
class ImpugnacionRecibidaEmpresa extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Impugnacion $impugnacion, public readonly string $urlPanel) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "El trabajador presentó una impugnación - Proceso {$this->impugnacion->proceso->codigo}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.impugnacion-recibida-empresa', with: [
            'impugnacion' => $this->impugnacion,
            'proceso' => $this->impugnacion->proceso,
            'trabajador' => $this->impugnacion->proceso->trabajador,
            'empresa' => $this->impugnacion->proceso->empresa,
            'urlPanel' => $this->urlPanel,
        ]);
    }
}
