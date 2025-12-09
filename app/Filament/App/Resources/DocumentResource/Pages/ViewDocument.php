<?php

namespace App\Filament\App\Resources\DocumentResource\Pages;

use App\Enums\ApprovalStatus;
use App\Enums\DocumentStatus;
use App\Filament\App\Resources\DocumentResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

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
            Infolists\Components\TextEntry::make('department.name')
                ->label('Department'),
            Infolists\Components\TextEntry::make('status')
                ->badge()
                ->color(fn ($state) => $state->color()),
        ];

        if ($viewType === 'full') {
            $schema[] = Infolists\Components\TextEntry::make('content')
                ->columnSpanFull();
            
            $schema[] = Infolists\Components\RepeatableEntry::make('approvers')
                ->label('Approval Steps')
                ->schema([
                    Infolists\Components\TextEntry::make('step_order')
                        ->label('Step'),
                    Infolists\Components\TextEntry::make('approver.name')
                        ->label('Approver'),
                    Infolists\Components\TextEntry::make('status')
                        ->badge()
                        ->color(fn ($state) => $state->color()),
                    Infolists\Components\TextEntry::make('comment')
                        ->visible(fn ($record) => !empty($record->comment)),
                ])
                ->columnSpanFull();
        } else {
            $schema[] = Infolists\Components\TextEntry::make('approvers')
                ->label('Approvers')
                ->listWithLineBreaks()
                ->formatStateUsing(fn () => $this->record->approvers->pluck('approver.name')->join(', '))
                ->columnSpanFull();
        }

        return $infolist->schema($schema);
    }

    protected function getHeaderActions(): array
    {
        $user = auth()->user();
        $actions = [];

        $currentApproval = $this->record->approvers()
            ->where('approver_id', $user->id)
            ->where('status', ApprovalStatus::PENDING)
            ->where('step_order', $this->record->current_step)
            ->first();

        if ($currentApproval) {
            $actions[] = Actions\Action::make('approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->modalHeading('Approve Document')
                ->modalDescription('Are you sure you want to approve this document?')
                ->modalSubmitActionLabel('Approve')
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

                    $nextStep = $this->record->current_step + 1;
                    $hasNextApprover = $this->record->approvers()
                        ->where('step_order', $nextStep)
                        ->exists();

                    if ($hasNextApprover) {
                        $this->record->update([
                            'current_step' => $nextStep,
                        ]);
                        
                        Notification::make()
                            ->success()
                            ->title('Document Approved')
                            ->body('The document has been approved and sent to the next approver.')
                            ->send();
                    } else {
                        $this->record->update([
                            'status' => DocumentStatus::APPROVED,
                            'approved_at' => now(),
                        ]);
                        
                        Notification::make()
                            ->success()
                            ->title('Document Fully Approved')
                            ->body('The document has been approved by all approvers.')
                            ->send();
                    }
                    
                    return redirect($this->getResource()::getUrl('index'));
                });

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

                    $this->record->update([
                        'status' => DocumentStatus::REJECTED,
                        'current_step' => 0,
                    ]);
                    
                    Notification::make()
                        ->danger()
                        ->title('Document Rejected')
                        ->body('The document has been rejected and returned to the creator.')
                        ->send();
                    
                    return redirect($this->getResource()::getUrl('index'));
                });
        }
        
        $actions[] = Actions\Action::make('back')
            ->label('Return to List')
            ->icon('heroicon-o-arrow-left')
            ->color('gray')
            ->url($this->getResource()::getUrl('index'));

        return $actions;
    }
}