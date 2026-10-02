<?php

namespace App\Mail;

use App\Models\Empresa;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Fase 1 (Publicación) - deliberadamente MÁS LIGERO que RitAceptado (Fase
 * 2): confirma que el trabajador quedó informado de la publicación del
 * RIT, nunca que "comprendió" o "aceptó" - esa declaración fuerte es
 * exclusiva de la Fase 2. Ver docs/superpowers/specs/2026-09-30-socializacion-rit-dos-fases-design.md.
 */
class RitPublicacionInformada extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $nombreTrabajador,
        public readonly string $nombreEmpresa,
        public readonly ?Empresa $empresa = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Publicación del Reglamento Interno de Trabajo - {$this->nombreEmpresa}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.rit-publicacion-informada',
        );
    }
}
