<?php

namespace App\Mail;

use App\Models\Document;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class DocumentRecalled extends DocumentMail
{
    public function __construct(public Document $document) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Recalled] Document Recalled: ' . $this->document->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.document-recalled',
        );
    }
}
