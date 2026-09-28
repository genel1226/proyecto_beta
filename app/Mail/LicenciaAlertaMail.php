<?php

namespace App\Mail;

use App\Models\Licencias\Licencias;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Un solo correo para los dos casos, con una sola plantilla:
 *   - tipo 'por_vencer': faltan $dias días.
 *   - tipo 'vencida':    la licencia acaba de vencer y se suspendió el acceso.
 */
class LicenciaAlertaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Licencias $licencia,
        public string $tipo,
        public int $dias = 0,
    ) {}

    public function envelope(): Envelope
    {
        $codigo = $this->licencia->codigo_licencia;
        $empresa = $this->licencia->empresa?->nombre_comercial;

        $asunto = match (true) {
            $this->tipo === 'vencida' => "Licencia {$codigo} de {$empresa}: venció",
            $this->dias === 1 => "Licencia {$codigo} de {$empresa}: vence mañana",
            default => "Licencia {$codigo} de {$empresa}: vence en {$this->dias} días",
        };

        return new Envelope(subject: $asunto);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.licencia-alerta');
    }
}
