<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/** Avís de jornades d'altres dies sense sortida fitxada (worklogs:avis-sense-sortida). */
class SenseSortidaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Collection $logs,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '🚪 Tens jornades sense sortida fitxada');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.sense_sortida');
    }
}
