<?php

namespace App\Mail;

use App\Models\Document;
use App\Models\DocumentApprover;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class DocumentRejected extends DocumentMail
{
    public function __construct(
        public Document $document,
        public DocumentApprover $approver
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Rejected] Document Has Been Rejected: ' . $this->document->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.document-rejected',
        );
    }
}
