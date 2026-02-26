<?php

namespace App\Filament\App\Resources\DocumentResource\Pages;

use App\Enums\ApprovalStatus;
use App\Enums\DocumentStatus;
use App\Enums\DocumentActivityAction;
use App\Enums\StepType;

use App\Filament\App\Resources\DocumentResource;
use App\Mail\DocumentApproved;
use App\Mail\DocumentCheckingRequest;
use App\Mail\DocumentRejected;
use App\Mail\DocumentSubmitted;
use App\Models\DocumentActivityLog;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Mail;

class ViewDocument extends ViewRecord
{
    protected static string $resource = DocumentResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        $user = auth()->user();
        $viewType = $this->record->getViewType($user);

        if ($viewType === 'none') {
            abort(403);
        }

        $schema = [
            Infolists\Components\TextEntry::make('title'),
            Infolists\Components\TextEntry::make('creator.name'),
            Infolists\Components\TextEntry::make('division.name')->label('Division'),
            Infolists\Components\TextEntry::make('status')
                ->badge()
                ->color(fn($state) => $state->color()),
        ];

        if ($viewType === 'full') {

            $schema[] = Infolists\Components\ViewEntry::make('content')
                ->label('')
                ->view('filament.pages.view-document-content')
                ->columnSpanFull();

            $schema[] = Infolists\Components\RepeatableEntry::make('approvers')
               ->label('Workflow Steps')
                ->schema([
                   Infolists\Components\TextEntry::make('step_label')
                       ->label('Step')
                       ->state(fn($record) => $record->getStepLabel()),
                   Infolists\Components\TextEntry::make('approver.name')->label('Assigned To'),
                    Infolists\Components\TextEntry::make('status')
                        ->badge()
                        ->color(fn($state) => $state->color()),
                   Infolists\Components\TextEntry::make('approved_at')
                       ->label('Completed At')
                       ->dateTime('d/m/Y H:i')
                       ->visible(fn($record) => $record->approved_at !== null),
                    Infolists\Components\TextEntry::make('comment')
                        ->visible(fn($record) => !empty($record->comment)),
                ])
                ->columnSpanFull();


                $schema[] = Infolists\Components\Section::make('Activity Timeline')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('activityLogs')
                        ->label('')
                        ->state(fn($record) => $record->activityLogs)
                        ->schema([
                            Infolists\Components\TextEntry::make('action')
                                ->badge()
                                ->color(fn($state) => $state->color())
                                ->icon(fn($state) => $state->icon()),
                            Infolists\Components\TextEntry::make('actor_name')->label('By'),
                            Infolists\Components\TextEntry::make('performed_at')->dateTime('d/m/Y H:i')->label('When'),
                            Infolists\Components\TextEntry::make('comment')->visible(fn($record) => !empty($record->comment)),
                        ])
                        ->columns(4)
                ])
                ->collapsible()
                ->collapsed()
                ->columnSpanFull();
                
        } else {
            $schema[] = Infolists\Components\Section::make('Approvers')
                ->schema([
                    Infolists\Components\TextEntry::make('approver_list')
                        ->label('')
                        ->state(fn() => $this->record->approvers->pluck('approver.name')->join(', ')),
                ])
                ->columnSpanFull();
        }

        return $infolist->schema($schema);
    }

    protected function getHeaderActions(): array
    {
        $user = auth()->user();
        $actions = [];

        // Export PDF Button
        $actions[] = Actions\Action::make('export_pdf')
            ->label('Export PDF')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('primary')
            ->url(fn() => route('documents.export-pdf', ['document' => $this->record]))
            ->openUrlInNewTab();


        $actions[] = Actions\Action::make('export_excel')
            ->label('Export Excel')
            ->icon('heroicon-o-table-cells')
            ->color('success')
            ->url(fn() => route('documents.export-excel', ['document' => $this->record]))
            ->openUrlInNewTab();


        


        $currentApproval = $this->record->approvers()
            ->where('approver_id', $user->id)
            ->where('status', ApprovalStatus::PENDING)
            ->where('step_order', $this->record->current_step)
            ->first();

        if ($currentApproval) {

            $stepType = $currentApproval->step_type;
           $actionLabel = match($stepType) {
               StepType::PREPARE => 'Complete',
               StepType::CHECKING => 'Verified',
               default => 'Approve',
           };
           $modalHeading = match($stepType) {
               StepType::PREPARE => 'Complete Preparation',
               StepType::CHECKING => 'Verify Document',
               default => 'Approve Document',
           };

            $actions[] = Actions\Action::make('approve')
               ->label($actionLabel) 
                ->icon('heroicon-o-check-circle')
                ->color('success')
               ->modalHeading($modalHeading)
               ->modalDescription("Are you sure you want to {$actionLabel} this document?")
               ->modalSubmitActionLabel($actionLabel)
                ->form([
                    Forms\Components\Textarea::make('comment')
                        ->label('Comment (Optional)')
                        ->rows(3),
                ])
                ->action(function (array $data) use ($currentApproval) {
                    $currentApproval->update([
                        'status' => ApprovalStatus::APPROVED,
                        'approved_at' => now(),
                        'comment' => $data['comment'] ?? null,
                    ]);

                    if ($currentApproval->signature_cell) {
                        $parts = explode(':', $currentApproval->signature_cell);
                        if (count($parts) === 2) {
                            $this->record->setSignature($parts[0], $parts[1], $currentApproval->approver_id);
                            $this->record->save();
                        }
                    }

                    if ($currentApproval->approved_date_cell) {
                        $parts = explode(':', $currentApproval->approved_date_cell);
                        if (count($parts) === 2) {
                            
                            $this->record->setApprovedDate($parts[0], $parts[1]);
                            $this->record->save();
                        }
                    }

                    $nextStep = $this->record->current_step + 1;
                    $hasNextApprover = $this->record->approvers()
                        ->where('step_order', $nextStep)
                        ->exists();

                    if ($hasNextApprover) {
                        $this->record->update(['current_step' => $nextStep]);

                        $activityAction = match($currentApproval->step_type) {
                            StepType::PREPARE => DocumentActivityAction::PREPARED,
                            StepType::CHECKING => DocumentActivityAction::CHECKED,
                            default => DocumentActivityAction::APPROVED,
                        };

                        DocumentActivityLog::log($this->record, $activityAction, null, [
                            'step_order' => $currentApproval->step_order,
                            'comment' => $data['comment'] ?? null,
                        ]);

                         $nextApprover = $this->record->approvers()
                            ->where('step_order', $nextStep)
                            ->first();
                        
                        if ($nextApprover && $nextApprover->approver->email && $nextApprover->step_type?->shouldSendEmail()) {
                            if ($nextApprover->step_type === StepType::CHECKING) {
                                Mail::to($nextApprover->approver->email)
                                    ->queue(new DocumentCheckingRequest($this->record));
                            } else {
                                Mail::to($nextApprover->approver->email)
                                    ->queue(new DocumentSubmitted($this->record));
                            }

                        }

                        Notification::make()
                            ->success()
                            ->title('Document Approved')
                            ->body('The document has been approved and sent to the next approver.')
                            ->send();
                    } else {
                        $oldStatus = $this->record->status;

                        $this->record->update([
                            'status' => DocumentStatus::APPROVED,
                            'approved_at' => now(),
                        ]);

                        $activityAction = match($currentApproval->step_type) {
                            StepType::PREPARE => DocumentActivityAction::PREPARED,
                            StepType::CHECKING => DocumentActivityAction::CHECKED,
                            default => DocumentActivityAction::APPROVED,
                        };

                        DocumentActivityLog::log($this->record, $activityAction, null, [     
                            'old_status' => $oldStatus->value,
                            'new_status' => DocumentStatus::APPROVED->value,
                            'step_order' => $currentApproval->step_order,
                            'comment' => $data['comment'] ?? null,
                        ]);

                        if ($this->record->creator->email) {
                            Mail::to($this->record->creator->email)
                                ->queue(new DocumentApproved($this->record, $currentApproval, true));
                        }

                        Notification::make()
                            ->success()
                            ->title('Document Fully Approved')
                            ->body('The document has been approved by all approvers.')
                            ->send();
                    }

                    return redirect($this->getResource()::getUrl('index'));
                });


           if ($stepType === StepType::APPROVE) {
               $actions[] = Actions\Action::make('reject')
                   ->icon('heroicon-o-x-circle')
                   ->color('danger')
                   ->modalHeading('Reject Document')
                   ->modalDescription('Are you sure you want to reject this document?')
                   ->modalSubmitActionLabel('Reject')
                   ->form([
                       Forms\Components\Textarea::make('comment')
                           ->label('Reason for Rejection')
                           ->required()
                           ->rows(3),
                   ])
                   ->action(function (array $data) use ($currentApproval) {
                       $currentApproval->update([
                           'status' => ApprovalStatus::REJECTED,
                           'rejected_at' => now(),
                           'comment' => $data['comment'],
                       ]);

                       $oldStatus = $this->record->status;

                       $this->record->update([
                           'status' => DocumentStatus::REJECTED,
                       ]);

                       DocumentActivityLog::log($this->record, DocumentActivityAction::REJECTED, null, [
                           'old_status' => $oldStatus->value,
                           'new_status' => DocumentStatus::REJECTED->value,
                           'step_order' => $currentApproval->step_order,
                           'comment' => $data['comment'],
                       ]);

                       if ($this->record->creator->email) {
                           Mail::to($this->record->creator->email)
                               ->queue(new DocumentRejected($this->record, $currentApproval));
                       }

                       Notification::make()
                           ->success()
                           ->title('Document Rejected')
                           ->body('The document has been rejected.')
                           ->send();

                       return redirect($this->getResource()::getUrl('index'));
                   });
           }
        }

        $actions[] = Actions\Action::make('back')
            ->label('Return to List')
            ->icon('heroicon-o-arrow-left')
            ->color('gray')
            ->url($this->getResource()::getUrl('index'));

        return $actions;
    }
}