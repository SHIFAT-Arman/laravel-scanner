<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class MemberOrientationInvitation extends Mailable
{
    use Queueable;

    /**
     * Create a new message instance.
     */
    public function __construct(public string $uuid, public string $qrPng) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Invitation to the event titled "Members Orientation - 2026" organized by IEEE AIUB Student Branch.',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.member-orientation-invitation',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => $this->qrPng, "{$this->uuid}.png")
                ->withMime('image/png'),
        ];
    }
}
