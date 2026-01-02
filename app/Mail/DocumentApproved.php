<?php

namespace App\Mail;

use App\Models\Document;
use App\Models\DocumentApprover;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class DocumentApproved extends DocumentMail
{
    public function __construct(
        public Document $document,
        public DocumentApprover $approver,
        public bool $isFinalApproval = false
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->isFinalApproval
            ? '[Approved] Document Fully Approved: ' . $this->document->title
            : '[Action Required] Document Approved - Your Turn: ' . $this->document->title;

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.document-approved',
        );
    }
}
