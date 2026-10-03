<?php

namespace App\Mail;

use App\Models\Empresa;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Escalamiento a RRHH (pedido de Andrés Sarmiento, reunión 2026-10-03): se
 * envía cuando un trabajador insiste por segunda vez en que no entendió el
 * Reglamento Interno de Trabajo, pese a habérsele mostrado el video, el
 * resumen y el RIT completo. De ahí en adelante la decisión es de RRHH, no
 * del sistema.
 */
class RitNoComprendidoAlertaRrhh extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $nombreTrabajador,
        public readonly string $documentoTrabajador,
        public readonly ?Empresa $empresa = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Alerta: trabajador no comprende el Reglamento Interno de Trabajo",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.rit-no-comprendido-alerta-rrhh',
        );
    }
}
