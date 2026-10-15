<?php

namespace App\Mail;

use App\Models\Incidencia;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Avís a RRHH d'una incidència nova. Respondre el correu va directament a la persona. */
class IncidenciaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Incidencia $incidencia) {}

    public function envelope(): Envelope
    {
        $u = $this->incidencia->user;

        return new Envelope(
            subject: '🛎️ Incidència: ' . $this->incidencia->assumpte,
            replyTo: [new Address($u->email, $u->name)],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.incidencia');
    }
}
