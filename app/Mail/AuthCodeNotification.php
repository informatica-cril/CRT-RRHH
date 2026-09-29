<?php

namespace App\Mail;

use App\Models\AuthorizationCode;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AuthCodeNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public AuthorizationCode $authCode,
        public User $recipient,
        public User $generatedBy,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Codi d\'autorització d\'hores extra — CRT RRHH');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.auth-code');
    }
}
