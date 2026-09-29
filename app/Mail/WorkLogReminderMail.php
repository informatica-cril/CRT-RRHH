<?php

namespace App\Mail;

use App\Models\WorkLog;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WorkLogReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public WorkLog $log,
        public int $reminderNumber,
    ) {}

    public function envelope(): Envelope
    {
        $subject = match ($this->reminderNumber) {
            0       => '🔔 La teva jornada finalitza en 10 minuts — CRT RRHH',
            1       => '⏰ Recorda tancar la jornada — CRT RRHH',
            default => '⚠️ Jornada oberta a les 20:00h — CRT RRHH',
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.worklog_reminder');
    }
}
