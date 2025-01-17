<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class CrmMail extends Mailable implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(
        public string $content = '',
        public ?string $fromName = null,
        public ?string $headerImage = null,
        public ?string $footerImage = null,
        public ?string $signature = null,
        public ?array $attach = null,
    ) {

    }

    public function setContent($content)
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                config('octiv.emails.noreply'),
                $this->fromName ?: 'Octiv'
            )
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            html: 'emails.crm-mail'
        );
    }
}
