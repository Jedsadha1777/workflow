<?php

namespace App\Console\Commands;

use App\Enums\ApprovalStatus;
use App\Enums\DocumentStatus;
use App\Mail\OverdueApprovalReminder;
use App\Models\Document;
use App\Models\DocumentApprover;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SendOverdueApprovalReminder extends Command
{
    protected $signature = 'documents:send-overdue-reminders';
    protected $description = 'Send reminder emails for documents pending approval over 3 days';

    public function handle(): int
    {
        $overdueThreshold = now()->subDays(3);

        $overdueApprovers = DocumentApprover::query()
            ->select('document_approvers.*')
            ->join('documents', 'documents.id', '=', 'document_approvers.document_id')
            ->where('document_approvers.status', ApprovalStatus::PENDING->value)
            ->where('documents.status', DocumentStatus::PENDING->value)
            ->whereColumn('document_approvers.step_order', 'documents.current_step')
            ->whereNull('document_approvers.overdue_notified_at')
            ->get();

        $notifiedApprovers = [];

        foreach ($overdueApprovers as $approverRecord) {
            $waitingSince = $this->getWaitingSince($approverRecord);

            if (!$waitingSince || $waitingSince > $overdueThreshold) {
                continue;
            }

            $approverId = $approverRecord->approver_id;

            if (!isset($notifiedApprovers[$approverId])) {
                $notifiedApprovers[$approverId] = [
                    'approver' => $approverRecord->approver,
                    'overdue_documents' => collect(),
                    'approver_records' => collect(),
                ];
            }

            $notifiedApprovers[$approverId]['overdue_documents']->push($approverRecord->document);
            $notifiedApprovers[$approverId]['approver_records']->push($approverRecord);
        }

        $sentCount = 0;

        foreach ($notifiedApprovers as $approverId => $data) {
            $approver = $data['approver'];

            if (!$approver || !$approver->email) {
                continue;
            }

            $totalPendingCount = $this->getTotalPendingCount($approverId);

            Mail::to($approver->email)
                ->queue(new OverdueApprovalReminder(
                    $approver,
                    $data['overdue_documents'],
                    $totalPendingCount
                ));

            foreach ($data['approver_records'] as $record) {
                $record->update(['overdue_notified_at' => now()]);
            }

            $sentCount++;
            $this->info("Sent reminder to {$approver->name} ({$approver->email}) - {$data['overdue_documents']->count()} overdue, {$totalPendingCount} total pending");
        }

        $this->info("Completed. Sent {$sentCount} reminder(s).");

        return Command::SUCCESS;
    }

    private function getWaitingSince(DocumentApprover $approverRecord): ?\Carbon\Carbon
    {
        $document = $approverRecord->document;
        $stepOrder = $approverRecord->step_order;

        if ($stepOrder === 1) {
            return $document->submitted_at;
        }

        $previousApprover = DocumentApprover::where('document_id', $document->id)
            ->where('step_order', $stepOrder - 1)
            ->where('status', ApprovalStatus::APPROVED->value)
            ->first();

        return $previousApprover?->approved_at;
    }

    private function getTotalPendingCount(int $approverId): int
    {
        return DocumentApprover::query()
            ->join('documents', 'documents.id', '=', 'document_approvers.document_id')
            ->where('document_approvers.approver_id', $approverId)
            ->where('document_approvers.status', ApprovalStatus::PENDING->value)
            ->where('documents.status', DocumentStatus::PENDING->value)
            ->whereColumn('document_approvers.step_order', 'documents.current_step')
            ->count();
    }
}