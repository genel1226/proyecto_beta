<?php

namespace App\Mail;

use App\Models\Licencias\Licencias;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Un solo correo para los dos casos, con una sola plantilla:
 *   - tipo 'por_vencer': faltan $dias días.
 *   - tipo 'vencida':    la licencia acaba de vencer y se suspendió el acceso.
 *
 * Todos los textos se arman aquí y la plantilla (emails/licencia-alerta.blade.php)
 * solo los dibuja: así el diseño se cambia sin tocar la lógica, y al revés.
 * También hay una versión en texto plano (emails/licencia-alerta-texto.blade.php).
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

        // Si el cliente responde el correo, la respuesta llega a Software4tech.
        $respuestas = config('licencias.correo_interno');

        return new Envelope(
            subject: $asunto,
            replyTo: $respuestas ? [new Address($respuestas)] : [],
        );
    }

    public function content(): Content
    {
        $vencida = $this->tipo === 'vencida';

        $empresa = $this->licencia->empresa?->nombre_comercial ?? '—';
        $codigo = $this->licencia->codigo_licencia;
        $fecha = $this->licencia->fecha_vencimiento->format('d/m/Y');
        $cuando = $this->dias === 1 ? 'mañana' : "en {$this->dias} días";
        $correo = config('licencias.correo_interno');

        return new Content(
            view: 'emails.licencia-alerta',
            text: 'emails.licencia-alerta-texto',
            with: [
                'vencida' => $vencida,
                'etiqueta' => $vencida ? 'Licencia vencida' : 'Licencia por vencer',
                'titulo' => $vencida ? "Tu licencia venció el {$fecha}" : "Tu licencia vence {$cuando}",
                'preheader' => $vencida
                    ? "El acceso de {$empresa} al CMMS fue suspendido."
                    : "Realiza el pago antes del {$fecha} para evitar la suspensión del acceso.",
                'saludo' => "equipo de {$empresa}",
                'mensaje' => $vencida
                    ? "La licencia {$codigo} de {$empresa} venció el {$fecha}."
                    : "La licencia {$codigo} de {$empresa} vence {$cuando}, el {$fecha}.",
                'consejo' => $vencida
                    ? 'El acceso de la empresa al CMMS fue suspendido. Para reactivarlo, comunícate con Software4tech.'
                    : 'Para evitar la suspensión del acceso al CMMS, realiza el pago antes de esa fecha.',
                'nota' => $vencida
                    ? 'Si ya realizaste el pago, escríbenos para reactivar tu acceso cuanto antes.'
                    : 'Si ya realizaste el pago, puedes ignorar este mensaje.',

                // Datos de la tabla de detalle
                'empresa' => $empresa,
                'codigo' => $codigo,
                'plan' => $this->licencia->plan?->nombre ?? '—',
                'fecha' => $fecha,
                'estadoTexto' => $vencida ? 'Vencida · acceso suspendido' : "Vence {$cuando}",

                // Colores según el caso (los mismos tonos del sistema: ámbar = por vencer, rosa = vencida)
                'tonoFondo' => $vencida ? '#ffeaf1' : '#fff7de',
                'tonoBorde' => $vencida ? '#c0245f' : '#b36b00',
                'tonoTexto' => $vencida ? '#8f1b48' : '#7a4f00',

                // Botón y pie (el botón solo sale si hay un correo de contacto configurado)
                'textoBoton' => $vencida ? 'Reactivar mi licencia' : 'Contactar a Software4tech',
                'mailto' => $correo ? 'mailto:' . $correo . '?subject=' . rawurlencode("Licencia {$codigo} de {$empresa}") : null,
                'contactoCorreo' => $correo,
                'contactoTelefono' => config('licencias.contacto_telefono'),
                'contactoWeb' => config('licencias.contacto_web'),
                'sistema' => config('app.name'),
                'anio' => now()->year,
            ],
        );
    }
}
