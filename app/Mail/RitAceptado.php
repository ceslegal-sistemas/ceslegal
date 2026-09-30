<?php

namespace App\Mail;

use App\Models\Empresa;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RitAceptado extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * $empresa es opcional (nullable) para no romper si alguna vez se
     * construye este correo sin el modelo a mano - pero SIEMPRE debería
     * pasarse, es lo que permite mostrar el logo real de la empresa en vez
     * del color rojo genérico de LUPE (bug real reportado por el usuario,
     * 2026-09-30: "sale el color de por defecto de lupe").
     */
    public function __construct(
        public readonly string $nombreTrabajador,
        public readonly string $nombreEmpresa,
        public readonly ?Empresa $empresa = null,
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
