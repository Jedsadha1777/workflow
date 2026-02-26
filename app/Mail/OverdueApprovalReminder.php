<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Collection;

class OverdueApprovalReminder extends DocumentMail
{
    public function __construct(
        public User $approver,
        public Collection $overdueDocuments,
        public int $totalPendingCount
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Reminder] You have ' . $this->totalPendingCount . ' document(s) awaiting your approval',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.overdue-approval-reminder',
        );
    }
}