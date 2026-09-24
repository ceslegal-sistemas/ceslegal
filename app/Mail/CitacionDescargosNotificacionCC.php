<?php

namespace App\Mail;

use App\Models\ProcesoDisciplinario;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Correo informativo (NO un CC tecnico real) para un jefe/RRHH que debe
 * enterarse de que se citó a un trabajador a descargos - pedido explícito
 * del usuario, 2026-09-24: mensaje distinto al que recibe el trabajador,
 * dirigido a un tercero, no una copia literal de la citación formal.
 */
class CitacionDescargosNotificacionCC extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ProcesoDisciplinario $proceso,
        public readonly string $pdfPath,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Citación a descargos - ' . $this->proceso->trabajador->nombre_completo,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.citacion-descargos-notificacion-cc',
            with: [
                'proceso' => $this->proceso,
                'trabajador' => $this->proceso->trabajador,
                'empresa' => $this->proceso->empresa,
            ],
        );
    }

    public function attachments(): array
    {
        $extension = pathinfo($this->pdfPath, PATHINFO_EXTENSION);
        $mimeType = $extension === 'pdf'
            ? 'application/pdf'
            : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

        return [
            Attachment::fromPath($this->pdfPath)
                ->as('Citacion_Descargos_' . $this->proceso->codigo . '.' . $extension)
                ->withMime($mimeType),
        ];
    }
}
