<?php

namespace App\Mail;

use App\Models\Document;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class DocumentCheckingRequest extends DocumentMail
{
    public function __construct(public Document $document) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Action Required] Document Awaiting Checking: ' . $this->document->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.document-checking-request',
        );
    }
}